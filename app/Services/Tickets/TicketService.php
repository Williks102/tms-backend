<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Tickets/TicketService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Tickets;

use App\Models\Departure;
use App\Models\RouteStop;
use App\Models\Ticket;
use App\Services\Fuel\AlertDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketService
{
    public function __construct(
        private readonly AlertDispatcher $alertDispatcher,
    ) {}

    /**
     * Vente au guichet — payée immédiatement par le caissier.
     */
    public function purchasePhysical(array $data, int $cashierId): Ticket
    {
        return $this->issue($data, channel: 'physical', soldBy: $cashierId);
    }

    /**
     * Achat en ligne — payé au moment de la requête (paiement déjà confirmé
     * par la billetterie web/mobile qui appelle cet endpoint public).
     */
    public function purchaseOnline(array $data): Ticket
    {
        return $this->issue($data, channel: 'online', soldBy: null);
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

            $ticket = Ticket::create([
                'reference'           => $ref,
                'departure_id'        => $departure->id,
                'destination_stop_id' => $destinationStop?->id,
                'passenger_name'      => $data['passenger_name'],
                'passenger_phone'     => $data['passenger_phone'] ?? null,
                'seat_number'         => $data['seat_number'] ?? null,
                'channel'             => $channel,
                'payment_method'      => $data['payment_method'],
                'price_fcfa'          => $data['price_fcfa'] ?? $defaultFare,
                'status'              => 'paid',
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

    private const VALID_TRANSITIONS = [
        'pending'   => ['paid', 'cancelled'],
        'paid'      => ['boarded', 'cancelled', 'refunded'],
        'boarded'   => [],
        'cancelled' => [],
        'refunded'  => [],
    ];

    public function updateStatus(Ticket $ticket, string $newStatus, array $data = []): Ticket
    {
        $allowed = self::VALID_TRANSITIONS[$ticket->status] ?? [];

        if (!in_array($newStatus, $allowed)) {
            throw new \Exception(
                "Transition invalide : {$ticket->status} → {$newStatus}. "
                . "Autorisé : " . (implode(', ', $allowed) ?: 'aucune')
            );
        }

        return DB::transaction(function () use ($ticket, $newStatus, $data) {
            $updates = ['status' => $newStatus];

            if ($newStatus === 'boarded') {
                $departure = $ticket->departure;
                if (!in_array($departure->status, ['boarding', 'departed'])) {
                    throw new \Exception("Embarquement impossible : le départ est {$departure->status}");
                }
                $updates['boarded_at'] = now();
            }

            if (in_array($newStatus, ['cancelled', 'refunded'])) {
                $updates['cancellation_reason'] = $data['cancellation_reason']
                    ?? throw new \Exception('Le motif est obligatoire');

                // Libère la place et résout une éventuelle alerte de surbooking
                Departure::where('id', $ticket->departure_id)->increment('seats_available');
                $this->alertDispatcher->resolveFor($ticket->departure, 'overbooking');
            }

            $ticket->update($updates);

            Log::info("Billet {$ticket->reference} → {$newStatus}");

            return $ticket->fresh(['departure.route', 'departure.gate', 'destinationStop', 'soldBy']);
        });
    }
}
