<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Fret/FreightShipmentService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Fret;

use App\Enums\Role;
use App\Models\FreightClient;
use App\Models\FreightShipment;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Accounting\AccountingEntryService;
use App\Services\Audit\ActivityLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FreightShipmentService
{
    private const VALID_TRANSITIONS = [
        'assigned'   => ['in_transit'],
        'in_transit' => ['delivered'],
    ];

    public function __construct(
        private readonly FreightPricingService $pricing,
        private readonly AccountingEntryService $accounting,
        private readonly NotificationService $notifications,
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * Crée une expédition — tarif toujours recalculé serveur (jamais accepté
     * du client, même logique que TicketService::issue()).
     */
    public function create(array $data, int $userId): FreightShipment
    {
        $client     = FreightClient::findOrFail($data['freight_client_id']);
        $weightTons = (float) $data['weight_tons'];
        $distanceKm = (float) $data['distance_km'];
        $pricing    = $this->pricing->calculate($weightTons, $distanceKm);

        return DB::transaction(function () use ($data, $client, $weightTons, $distanceKm, $pricing, $userId) {
            // Placeholder unique le temps de connaître l'id auto-incrémenté —
            // même correctif que TicketService::issue() (runbook sécurité v3,
            // point 1) : jamais de référence dérivée d'un count()+1, qui
            // collisionne sous ventes/enregistrements concurrents.
            $shipment = FreightShipment::create([
                'reference'          => 'PENDING-' . Str::random(24),
                'freight_client_id'  => $client->id,
                'origin_city'        => $data['origin_city'],
                'destination_city'   => $data['destination_city'],
                'distance_km'        => $distanceKm,
                'weight_tons'        => $weightTons,
                'cargo_description'  => $data['cargo_description'] ?? null,
                'price_fcfa'         => $pricing['price_fcfa'],
                'status'             => 'pending',
                'payment_status'     => 'pending',
                'scheduled_at'       => $data['scheduled_at'] ?? null,
                'created_by'         => $userId,
            ]);

            $shipment->update(['reference' => sprintf('FRT-%s-%06d', now()->format('Y'), $shipment->id)]);

            return $shipment->fresh(['client']);
        });
    }

    /**
     * Affecte un camion (vehicles.type = truck) et éventuellement un
     * chauffeur — refuse un camion déjà affecté à une autre expédition en cours.
     *
     * @throws \RuntimeException si l'expédition n'est plus affectable, si le
     *                            véhicule n'est pas un camion, ou s'il est déjà pris
     */
    public function assign(FreightShipment $shipment, int $vehicleId, ?int $driverId = null): FreightShipment
    {
        if (!in_array($shipment->status, ['pending', 'assigned'], true)) {
            throw new \RuntimeException("Cette expédition ne peut plus être affectée (statut: {$shipment->status})");
        }

        $vehicle = Vehicle::findOrFail($vehicleId);

        if ($vehicle->type !== 'truck') {
            throw new \RuntimeException("Ce véhicule n'est pas un camion (type: {$vehicle->type})");
        }

        $alreadyAssigned = FreightShipment::where('vehicle_id', $vehicleId)
            ->whereIn('status', ['assigned', 'in_transit'])
            ->where('id', '!=', $shipment->id)
            ->exists();

        if ($alreadyAssigned) {
            throw new \RuntimeException('Ce camion est déjà affecté à une autre expédition en cours');
        }

        $shipment->update([
            'vehicle_id' => $vehicle->id,
            'driver_id'  => $driverId,
            'status'     => 'assigned',
        ]);

        return $shipment->fresh(['client', 'vehicle', 'driver']);
    }

    /**
     * Fait progresser le statut (assigned → in_transit → delivered).
     * L'annulation passe par cancel(), pas par cette méthode (motif obligatoire).
     *
     * À la livraison : constate le revenu par une créance client (débit 411 /
     * crédit 7072) — jamais un encaissement immédiat, voir markPaid().
     *
     * @throws \RuntimeException si la transition n'est pas autorisée
     */
    public function updateStatus(FreightShipment $shipment, string $newStatus, ?int $userId = null): FreightShipment
    {
        $allowed = self::VALID_TRANSITIONS[$shipment->status] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            throw new \RuntimeException(
                "Transition invalide : {$shipment->status} → {$newStatus}. "
                . 'Autorisé : ' . (implode(', ', $allowed) ?: 'aucune')
            );
        }

        return DB::transaction(function () use ($shipment, $newStatus, $userId) {
            $updates = ['status' => $newStatus];

            if ($newStatus === 'in_transit') {
                $updates['departed_at'] = now();
            }

            if ($newStatus === 'delivered') {
                $updates['delivered_at'] = now();
            }

            $shipment->update($updates);

            if ($newStatus === 'delivered') {
                $this->accounting->post(
                    journalCode: 'VE',
                    label: "Fret {$shipment->reference} — livré ({$shipment->client->company_name})",
                    lines: [
                        ['account' => '411', 'debit' => (float) $shipment->price_fcfa],
                        ['account' => '7072', 'credit' => (float) $shipment->price_fcfa],
                    ],
                    source: $shipment,
                    userId: $userId,
                );
                $shipment->update(['payment_status' => 'invoiced']);

                $managerIds = User::where('role', Role::MANAGER->value)->pluck('id')->all();
                if ($managerIds) {
                    $this->notifications->notify(
                        $managerIds,
                        'freight.delivered',
                        'Expédition fret livrée',
                        "Expédition {$shipment->reference} livrée — facture à établir ({$shipment->client->company_name})",
                    );
                }
            }

            return $shipment->fresh(['client', 'vehicle', 'driver']);
        });
    }

    /**
     * Annule une expédition non encore livrée — aucune contre-passation
     * nécessaire : le revenu n'est constaté qu'à la livraison (voir
     * updateStatus()), donc rien n'a encore été porté au compte de résultat.
     *
     * @throws \RuntimeException si l'expédition n'est plus annulable
     */
    public function cancel(FreightShipment $shipment, string $reason, ?int $userId = null): FreightShipment
    {
        if (!$shipment->canBeCancelled()) {
            throw new \RuntimeException("Cette expédition ne peut plus être annulée (statut: {$shipment->status})");
        }

        $shipment->update(['status' => 'cancelled', 'cancellation_reason' => $reason]);

        $this->activityLogger->log(
            'freight.cancelled',
            $shipment,
            "Expédition {$shipment->reference} annulée — {$reason}",
            userId: $userId,
        );

        return $shipment->fresh();
    }

    /**
     * Encaisse une expédition facturée (411) — solde la créance client.
     *
     * @throws \RuntimeException si l'expédition n'est pas en attente d'encaissement
     */
    public function markPaid(FreightShipment $shipment, string $depositAccount, ?int $userId = null): FreightShipment
    {
        if ($shipment->payment_status !== 'invoiced') {
            throw new \RuntimeException(
                "Cette expédition n'est pas en attente d'encaissement (statut paiement: {$shipment->payment_status})"
            );
        }

        if (!in_array($depositAccount, ['571', '521'], true)) {
            throw new \RuntimeException('Compte de dépôt invalide (571 caisse ou 521 banque uniquement)');
        }

        return DB::transaction(function () use ($shipment, $depositAccount, $userId) {
            $this->accounting->post(
                journalCode: $depositAccount === '571' ? 'CA' : 'BQ',
                label: "Encaissement fret {$shipment->reference} — {$shipment->client->company_name}",
                lines: [
                    ['account' => $depositAccount, 'debit' => (float) $shipment->price_fcfa],
                    ['account' => '411', 'credit' => (float) $shipment->price_fcfa],
                ],
                source: $shipment,
                userId: $userId,
            );

            $shipment->update(['payment_status' => 'paid']);

            $this->activityLogger->log(
                'freight.paid',
                $shipment,
                "Expédition {$shipment->reference} encaissée (compte {$depositAccount})",
                userId: $userId,
            );

            return $shipment->fresh(['client', 'vehicle', 'driver']);
        });
    }
}
