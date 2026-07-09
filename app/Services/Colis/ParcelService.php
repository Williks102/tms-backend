<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Colis/ParcelService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Colis;

use App\Models\Departure;
use App\Models\Parcel;
use App\Services\Accounting\AccountingEntryService;
use App\Services\Fuel\AlertDispatcher;
use Illuminate\Support\Facades\DB;

class ParcelService
{
    public function __construct(
        private readonly ParcelPricingService $pricing,
        private readonly AccountingEntryService $accounting,
        private readonly AlertDispatcher $alertDispatcher,
    ) {}

    /**
     * Enregistre un colis sur un départ.
     *
     * Vérification de capacité faite deux fois, comme TicketService::issue() :
     * une première fois HORS transaction (si elle échoue, l'alerte cargo_overflow
     * doit être conservée — si elle était émise à l'intérieur d'une transaction
     * qui échoue ensuite, le rollback annulerait aussi l'alerte), puis une
     * seconde fois DANS la transaction avec lockForUpdate() pour la garantie
     * de concurrence réelle.
     *
     * @throws \RuntimeException si le véhicule n'a pas de capacité fret configurée
     *                            ou si elle est dépassée
     */
    public function register(array $data, int $registeredBy): Parcel
    {
        $weight = (float) $data['weight_kg'];

        // Vérifiée hors transaction — voir docblock.
        $departure = Departure::findOrFail($data['departure_id']);
        $this->assertCargoCapacity($departure, $weight);

        return DB::transaction(function () use ($data, $registeredBy, $weight) {
            $departure = Departure::lockForUpdate()->findOrFail($data['departure_id']);
            $this->assertCargoCapacity($departure, $weight, dispatchAlert: false);

            $newUsed = (float) $departure->cargo_used_kg + $weight;

            $fees = $this->pricing->calculate($weight, (float) $data['declared_value_fcfa']);
            $paymentResponsibility = $data['payment_responsibility'] ?? 'expediteur';
            $isPaidNow = $paymentResponsibility === 'expediteur';

            $parcel = Parcel::create([
                'tracking_number'        => $this->nextTrackingNumber(),
                'pickup_code'            => $this->generatePickupCode(),
                'departure_id'           => $departure->id,
                'destination_stop_id'    => $data['destination_stop_id'] ?? null,
                'sender_name'            => $data['sender_name'],
                'sender_phone'           => $data['sender_phone'],
                'recipient_name'         => $data['recipient_name'],
                'recipient_phone'        => $data['recipient_phone'],
                'category'               => $data['category'] ?? 'standard',
                'description'            => $data['description'] ?? null,
                'weight_kg'              => $weight,
                'declared_value_fcfa'    => $data['declared_value_fcfa'],
                'transport_fee_fcfa'     => $fees['transport_fee_fcfa'],
                'insurance_fee_fcfa'     => $fees['insurance_fee_fcfa'],
                'total_fee_fcfa'         => $fees['total_fee_fcfa'],
                'payment_responsibility' => $paymentResponsibility,
                'payment_status'         => $isPaidNow ? 'paid' : 'pending',
                'status'                 => 'enregistre',
                'registered_by'          => $registeredBy,
                'registered_at'          => now(),
            ]);

            $departure->update(['cargo_used_kg' => $newUsed]);

            // Port payé : la recette est encaissée et reconnue immédiatement.
            // Port dû : aucune écriture avant le retrait (voir release()) —
            // la créance n'existe comptablement qu'une fois le colis livré.
            if ($isPaidNow) {
                $entry = $this->accounting->post(
                    journalCode: 'VE',
                    label: "Fret {$parcel->tracking_number} — {$parcel->sender_name} → {$parcel->recipient_name}",
                    lines: [
                        ['account' => '571', 'debit' => $fees['total_fee_fcfa']],
                        ['account' => '7071', 'credit' => $fees['total_fee_fcfa']],
                    ],
                    source: $parcel,
                    userId: $registeredBy,
                );
                $parcel->update(['accounting_entry_id' => $entry->id]);
            }

            return $parcel->fresh(['departure', 'destinationStop']);
        });
    }

    /**
     * @throws \RuntimeException si le colis n'est plus en attente d'arrivée
     */
    public function scanArrival(string $trackingNumber, int $userId): Parcel
    {
        $parcel = Parcel::where('tracking_number', $trackingNumber)->firstOrFail();

        if (!$parcel->isPending()) {
            throw new \RuntimeException("Ce colis n'est plus en transit (statut: {$parcel->status})");
        }

        $parcel->update(['status' => 'arrive', 'arrived_at' => now()]);

        return $parcel->fresh();
    }

    /**
     * Remet le colis au destinataire — vérifie le code de retrait (secret
     * partagé qui remplace la vérification d'identité) et encaisse le port
     * dû s'il n'a pas déjà été payé à l'enregistrement.
     *
     * @throws \RuntimeException si le colis n'est pas arrivé ou si le code est incorrect
     */
    public function release(Parcel $parcel, string $providedCode, int $releasedBy): Parcel
    {
        if (!$parcel->canBeReleased()) {
            throw new \RuntimeException("Ce colis n'est pas prêt pour le retrait (statut: {$parcel->status})");
        }

        if ($providedCode !== $parcel->pickup_code) {
            throw new \RuntimeException('Code de retrait incorrect');
        }

        return DB::transaction(function () use ($parcel, $releasedBy) {
            if ($parcel->payment_responsibility === 'destinataire' && $parcel->payment_status === 'pending') {
                $entry = $this->accounting->post(
                    journalCode: 'VE',
                    label: "Fret {$parcel->tracking_number} — encaissé au retrait (port dû)",
                    lines: [
                        ['account' => '571', 'debit' => (float) $parcel->total_fee_fcfa],
                        ['account' => '7071', 'credit' => (float) $parcel->total_fee_fcfa],
                    ],
                    source: $parcel,
                    userId: $releasedBy,
                );
                $parcel->update(['payment_status' => 'paid', 'accounting_entry_id' => $entry->id]);
            }

            $parcel->update([
                'status'      => 'retire',
                'released_by' => $releasedBy,
                'released_at' => now(),
            ]);

            return $parcel->fresh();
        });
    }

    /**
     * Annule un colis avant que son départ ne soit parti — libère la capacité
     * fret réservée et contre-passe l'écriture comptable si déjà payé (port payé).
     *
     * @throws \RuntimeException si le colis n'est plus annulable
     */
    public function cancel(Parcel $parcel, string $reason): Parcel
    {
        $parcel->loadMissing('departure');

        if ($parcel->status !== 'enregistre' || !in_array($parcel->departure->status, ['scheduled', 'boarding'], true)) {
            throw new \RuntimeException('Ce colis ne peut plus être annulé (départ déjà en cours ou colis déjà traité)');
        }

        return DB::transaction(function () use ($parcel, $reason) {
            Departure::where('id', $parcel->departure_id)->decrement('cargo_used_kg', (float) $parcel->weight_kg);

            if ($parcel->payment_status === 'paid') {
                $this->accounting->post(
                    journalCode: 'VE',
                    label: "Annulation fret {$parcel->tracking_number} — {$reason}",
                    lines: [
                        ['account' => '7071', 'debit' => (float) $parcel->total_fee_fcfa],
                        ['account' => '571', 'credit' => (float) $parcel->total_fee_fcfa],
                    ],
                    source: $parcel,
                );
            }

            $parcel->update(['status' => 'annule', 'exception_reason' => $reason]);

            return $parcel->fresh();
        });
    }

    /**
     * Marque un colis perdu ou retourné (non réclamé) — manager uniquement.
     * Pas de contre-passation automatique : si le port était payé, la
     * recette de transport reste acquise (service presté), le risque étant
     * couvert par la prime d'assurance encaissée séparément.
     *
     * @throws \RuntimeException si le statut cible n'est pas une exception valide
     */
    public function markException(Parcel $parcel, string $status, string $reason): Parcel
    {
        if (!in_array($status, ['retourne', 'perdu'], true)) {
            throw new \RuntimeException("Statut d'exception invalide");
        }

        $parcel->update([
            'status'           => $status,
            'exception_reason' => $reason,
            'returned_at'      => $status === 'retourne' ? now() : $parcel->returned_at,
        ]);

        return $parcel->fresh();
    }

    private function nextTrackingNumber(): string
    {
        $year  = (int) now()->year;
        $count = Parcel::whereYear('registered_at', $year)->count();

        return sprintf('COL-%d-%06d', $year, $count + 1);
    }

    private function generatePickupCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @throws \RuntimeException si le véhicule n'a pas de capacité fret ou si elle est dépassée
     */
    private function assertCargoCapacity(Departure $departure, float $weight, bool $dispatchAlert = true): void
    {
        if ($departure->cargo_capacity_kg === null) {
            throw new \RuntimeException("Ce véhicule n'a pas de capacité fret configurée — contactez un manager.");
        }

        $newUsed = (float) $departure->cargo_used_kg + $weight;

        if ($newUsed > (float) $departure->cargo_capacity_kg) {
            if ($dispatchAlert) {
                $this->alertDispatcher->create(
                    type:     'cargo_overflow',
                    severity: 'warning',
                    entity:   $departure,
                    message:  "Capacité fret dépassée sur le départ #{$departure->id} — enregistrement refusé",
                    metadata: [
                        'requested_kg' => $weight,
                        'available_kg' => (float) $departure->cargo_capacity_kg - (float) $departure->cargo_used_kg,
                    ],
                );
            }
            throw new \RuntimeException('Capacité fret insuffisante sur ce départ');
        }
    }
}
