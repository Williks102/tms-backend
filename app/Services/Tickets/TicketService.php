<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Tickets/TicketService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Tickets;

use App\Models\Departure;
use App\Models\RouteStop;
use App\Models\Ticket;
use App\Services\Audit\ActivityLogger;
use App\Services\Fuel\AlertDispatcher;
use App\Services\Payments\PaiementProService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TicketService
{
    // Durée pendant laquelle un billet en ligne "pending" retient son siège
    // en attendant la confirmation de paiement — voir ExpireOnlinePendingTickets.
    private const PAYMENT_HOLD_MINUTES = 30;

    public function __construct(
        private readonly AlertDispatcher $alertDispatcher,
        private readonly ActivityLogger $activityLogger,
        private readonly PaiementProService $paiementPro,
    ) {}

    /**
     * Vente au guichet — payée immédiatement par le caissier.
     */
    public function purchasePhysical(array $data, int $cashierId): Ticket
    {
        return $this->issue($data, channel: 'physical', soldBy: $cashierId);
    }

    /**
     * Achat en ligne — crée un billet "pending" (siège réservé, place
     * décomptée) puis initialise une session de paiement PaiementPro. Le
     * billet ne passe "paid" qu'à la confirmation par webhook (voir
     * confirmOnlinePayment()) — jamais à cet appel, contrairement à l'ancien
     * comportement qui simulait un paiement déjà validé.
     *
     * Si l'initialisation PaiementPro échoue (API indisponible, merchantId
     * invalide...), le billet pending est immédiatement annulé pour libérer
     * le siège plutôt que de laisser une réservation fantôme — le client
     * n'a jamais reçu d'URL de paiement, il n'y a donc rien à laisser en
     * attente.
     *
     * @return array{ticket: Ticket, payment_url: string}
     */
    public function purchaseOnline(array $data): array
    {
        $ticket = $this->issue($data, channel: 'online', soldBy: null);

        try {
            $init = $this->paiementPro->initPayment($ticket, $data['payment_channel']);
        } catch (\Throwable $e) {
            $this->updateStatus($ticket, 'cancelled', [
                'cancellation_reason' => 'Échec initialisation du paiement : ' . $e->getMessage(),
            ]);

            throw new \Exception('Impossible de démarrer le paiement pour le moment. Réessayez dans un instant.');
        }

        $ticket->update([
            'payment_channel'    => $data['payment_channel'],
            'payment_session_id' => $init['session_id'],
        ]);

        return ['ticket' => $ticket->fresh(['departure.route', 'departure.gate', 'destinationStop']), 'payment_url' => $init['url']];
    }

    /**
     * Confirme le paiement d'un billet en ligne suite à la notification
     * PaiementPro (voir PaiementProWebhookController). Idempotent : un
     * billet qui n'est plus "pending" (déjà payé ou déjà expiré/annulé)
     * ne change pas de statut — évite un double crédit sur notification
     * dupliquée, chose fréquente chez les passerelles de paiement qui
     * retentent l'appel jusqu'à recevoir un 200.
     */
    public function confirmOnlinePayment(Ticket $ticket, array $rawPayload): Ticket
    {
        if ($ticket->status !== 'pending') {
            Log::info('Notification PaiementPro ignorée — billet plus en attente', [
                'reference' => $ticket->reference,
                'status'    => $ticket->status,
            ]);

            return $ticket;
        }

        $ticket->update([
            'status'               => 'paid',
            'payment_confirmed_at' => now(),
        ]);

        $this->activityLogger->log(
            'payment.confirmed',
            $ticket,
            "Paiement PaiementPro confirmé pour {$ticket->reference}",
            metadata: ['raw_notification' => $rawPayload],
        );

        return $ticket->fresh(['departure.route', 'departure.gate', 'destinationStop']);
    }

    private function issue(array $data, string $channel, ?int $soldBy): Ticket
    {
        // Vérifiée hors transaction : si on lève une exception une fois la transaction
        // ouverte, elle annule tout ce qui a été écrit dans la même transaction —
        // y compris l'alerte de surbooking qu'on veut justement conserver.
        $departure = Departure::findOrFail($data['departure_id']);

        if (!$departure->isOpenForTicketSale()) {
            throw new \Exception("Vente impossible : ce départ est {$departure->status}");
        }

        // Billet vers un arrêt intermédiaire (ligne dynamique) plutôt que le
        // terminus de la ligne — le tarif vient alors de route_stops.fare_from_origin.
        $destinationStop = null;
        if (!empty($data['destination_stop_id'])) {
            $destinationStop = RouteStop::find($data['destination_stop_id']);

            if (!$destinationStop || $destinationStop->route_id !== $departure->route_id) {
                throw new \Exception("Cet arrêt n'appartient pas à la ligne de ce départ");
            }
        }

        if (!$departure->hasAvailableSeats()) {
            $this->alertDispatcher->create(
                type:     'overbooking',
                severity: 'warning',
                entity:   $departure,
                message:  "Aucune place disponible sur le départ #{$departure->id} — vente refusée",
                metadata: ['route_id' => $departure->route_id, 'channel' => $channel],
            );

            throw new \Exception('Aucune place disponible sur ce départ');
        }

        return DB::transaction(function () use ($data, $channel, $soldBy, $destinationStop) {
            // Reverrouillée ici pour empêcher une vente concurrente de dépasser le stock
            $departure = Departure::lockForUpdate()->findOrFail($data['departure_id']);

            if (!$departure->hasAvailableSeats()) {
                throw new \Exception('Aucune place disponible sur ce départ');
            }

            $year  = now()->format('Y');
            $count = Ticket::whereYear('created_at', $year)->count() + 1;
            $ref   = sprintf('TCK-%s-%06d', $year, $count);

            $defaultFare = $destinationStop?->fare_from_origin ?? $departure->route->base_fare;
            $isOnline    = $channel === 'online';

            $ticket = Ticket::create([
                'reference'           => $ref,
                'departure_id'        => $departure->id,
                'destination_stop_id' => $destinationStop?->id,
                'passenger_name'      => $data['passenger_name'],
                'passenger_phone'     => $data['passenger_phone'] ?? null,
                'passenger_email'     => $data['passenger_email'] ?? null,
                // Premier arrivé, premier servi : si aucun siège n'est fourni
                // explicitement (cas normal en achat en ligne — voir /billets),
                // on attribue automatiquement le premier siège libre.
                'seat_number'         => $data['seat_number'] ?? $this->assignSeat($departure),
                'channel'             => $channel,
                // Guichet : moyen fourni par le caissier (cash/mobile_money/card).
                // En ligne : toujours 'online' — le détail de l'opérateur
                // (CARD/OMCIV2/MOMOCI/WAVECI/MOOVCI) vit dans payment_channel.
                'payment_method'      => $isOnline ? 'online' : $data['payment_method'],
                // Référence opaque pour PaiementPro (pending uniquement) — jamais
                // la référence publique, séquentielle et donc devinable.
                'payment_token'       => $isOnline ? Str::random(40) : null,
                'payment_expires_at'  => $isOnline ? now()->addMinutes(self::PAYMENT_HOLD_MINUTES) : null,
                'price_fcfa'          => $data['price_fcfa'] ?? $defaultFare,
                // En ligne : reste "pending" jusqu'à confirmation webhook
                // (voir confirmOnlinePayment()) — au guichet, payé immédiatement
                // par le caissier au moment de la vente.
                'status'              => $isOnline ? 'pending' : 'paid',
                'sold_by'             => $soldBy,
                'purchased_at'        => now(),
            ]);

            $departure->decrement('seats_available');

            Log::info('Billet émis', [
                'reference' => $ref,
                'channel'   => $channel,
                'departure' => $departure->id,
            ]);

            return $ticket->fresh(['departure.route', 'departure.gate', 'destinationStop', 'soldBy']);
        });
    }

    /**
     * Premier siège libre (1..capacité du véhicule), dans l'ordre — "premier
     * arrivé, premier servi". Appelée à l'intérieur de la transaction de
     * issue(), après verrouillage du départ (lockForUpdate) : deux achats
     * concurrents sur le même départ se sérialisent sur ce verrou, donc pas
     * de risque que deux billets se voient attribuer le même siège.
     * Retourne null si aucun véhicule n'est affecté (capacité inconnue) —
     * le billet reste alors sans siège assigné, comme avant.
     */
    private function assignSeat(Departure $departure): ?string
    {
        $capacity = $departure->vehicle?->capacity;

        if (!$capacity) {
            return null;
        }

        $taken = Ticket::forDeparture($departure->id)
            ->active()
            ->whereNotNull('seat_number')
            ->pluck('seat_number')
            ->map(fn ($s) => (int) $s)
            ->all();

        for ($seat = 1; $seat <= $capacity; $seat++) {
            if (!in_array($seat, $taken, true)) {
                return (string) $seat;
            }
        }

        return null; // improbable si hasAvailableSeats() est déjà passé, filet de sécurité
    }

    private const VALID_TRANSITIONS = [
        'pending'   => ['paid', 'cancelled'],
        'paid'      => ['boarded', 'cancelled', 'refunded'],
        'boarded'   => [],
        'cancelled' => [],
        'refunded'  => [],
    ];

    public function updateStatus(Ticket $ticket, string $newStatus, array $data = [], ?int $actingUserId = null): Ticket
    {
        $allowed = self::VALID_TRANSITIONS[$ticket->status] ?? [];

        if (!in_array($newStatus, $allowed)) {
            throw new \Exception(
                "Transition invalide : {$ticket->status} → {$newStatus}. "
                . "Autorisé : " . (implode(', ', $allowed) ?: 'aucune')
            );
        }

        return DB::transaction(function () use ($ticket, $newStatus, $data, $actingUserId) {
            $updates = ['status' => $newStatus];

            if ($newStatus === 'boarded') {
                $departure = $ticket->departure;
                if (!in_array($departure->status, ['boarding', 'departed'])) {
                    throw new \Exception("Embarquement impossible : le départ est {$departure->status}");
                }
                $updates['boarded_at'] = now();
                // Qui a marqué l'embarquement — scan contrôleur ou bouton manuel
                // manager/caissier dans le manifeste, les deux chemins passent ici.
                $updates['boarded_by'] = $actingUserId;
            }

            if (in_array($newStatus, ['cancelled', 'refunded'])) {
                $updates['cancellation_reason'] = $data['cancellation_reason']
                    ?? throw new \Exception('Le motif est obligatoire');

                // Libère la place et résout une éventuelle alerte de surbooking
                Departure::where('id', $ticket->departure_id)->increment('seats_available');
                $this->alertDispatcher->resolveFor($ticket->departure, 'overbooking');

                $this->activityLogger->log(
                    $newStatus === 'refunded' ? 'ticket.refunded' : 'ticket.cancelled',
                    $ticket,
                    "Billet {$ticket->reference} — {$updates['cancellation_reason']}",
                    userId: $actingUserId,
                );
            }

            $ticket->update($updates);

            Log::info("Billet {$ticket->reference} → {$newStatus}");

            return $ticket->fresh(['departure.route', 'departure.gate', 'destinationStop', 'soldBy']);
        });
    }

    /**
     * Embarquement par scan (QR ou saisie manuelle de la référence) — résout
     * le billet par référence plutôt que par id, puis réutilise la même
     * validation de transition/statut du départ que updateStatus().
     */
    public function scanBoard(string $reference, int $actingUserId): Ticket
    {
        $ticket = Ticket::where('reference', $reference)->first();

        if (!$ticket) {
            throw new \Exception("Aucun billet trouvé pour la référence {$reference}");
        }

        return $this->updateStatus($ticket, 'boarded', [], $actingUserId);
    }
}
