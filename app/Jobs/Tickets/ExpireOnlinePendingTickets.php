<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Jobs/Tickets/ExpireOnlinePendingTickets.php
// Libère les sièges retenus par des achats en ligne jamais payés (abandon,
// échec, notification jamais reçue) — voir TicketService::PAYMENT_HOLD_MINUTES
// et routes/console.php pour la planification.
// ══════════════════════════════════════════════════════════════════════════
namespace App\Jobs\Tickets;

use App\Models\Ticket;
use App\Services\Tickets\TicketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireOnlinePendingTickets implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TicketService $ticketService): void
    {
        $expired = Ticket::where('channel', 'online')
            ->where('status', 'pending')
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<', now())
            ->get();

        foreach ($expired as $ticket) {
            // Recharge l'état réel juste avant d'agir : une confirmation de
            // paiement a pu arriver entre la requête ci-dessus et ce point.
            $ticket->refresh();
            if ($ticket->status !== 'pending') {
                continue;
            }

            try {
                // Réutilise la transition cancelled existante — libère le
                // siège et résout l'alerte overbooking comme toute annulation.
                $ticketService->updateStatus($ticket, 'cancelled', [
                    'cancellation_reason' => 'Paiement en ligne non confirmé dans le délai imparti',
                ]);
            } catch (\Throwable $e) {
                Log::error('Échec expiration billet en attente', [
                    'reference' => $ticket->reference,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }
}
