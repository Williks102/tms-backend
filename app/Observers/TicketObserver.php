<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Observers/TicketObserver.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Observers;

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Accounting\AccountingEntryService;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Log;

class TicketObserver
{
    public function __construct(
        private readonly AccountingEntryService $accounting,
        private readonly NotificationService $notifications,
    ) {}

    public function created(Ticket $ticket): void
    {
        Log::info('Billet émis', [
            'reference'    => $ticket->reference,
            'departure_id' => $ticket->departure_id,
            'channel'      => $ticket->channel,
        ]);

        // Vente en ligne uniquement : aucun caissier n'est impliqué dans le
        // flux (sold_by est null), le manager est donc le seul à devoir être
        // notifié qu'une vente vient d'arriver. Une vente guichet n'a pas
        // besoin de notification — le caissier vient de l'effectuer lui-même.
        if ($ticket->channel === 'online') {
            $this->notifyManagers(
                'ticket.online_sale',
                'Nouvelle vente en ligne',
                "Billet {$ticket->reference} vendu en ligne — {$ticket->passenger_name}",
            );
        }

        if ($ticket->status !== 'paid') {
            return;
        }

        // Un souci comptable ne doit jamais faire échouer la vente elle-même.
        try {
            $this->accounting->post(
                journalCode: 'VE',
                label: "Vente billet {$ticket->reference}",
                lines: [
                    ['account' => '571', 'debit' => (float) $ticket->price_fcfa],
                    ['account' => '706', 'credit' => (float) $ticket->price_fcfa],
                ],
                source: $ticket,
                userId: $ticket->sold_by,
            );
        } catch (\Throwable $e) {
            Log::error('Échec écriture comptable — vente billet', [
                'reference' => $ticket->reference,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    public function updated(Ticket $ticket): void
    {
        if (!$ticket->wasChanged('status')) {
            return;
        }

        Log::info('Statut billet modifié', [
            'reference' => $ticket->reference,
            'status'    => $ticket->status,
        ]);

        if (!in_array($ticket->status, ['cancelled', 'refunded'], true)) {
            return;
        }

        try {
            $this->accounting->post(
                journalCode: 'VE',
                label: sprintf(
                    '%s billet %s — %s',
                    $ticket->status === 'refunded' ? 'Remboursement' : 'Annulation',
                    $ticket->reference,
                    $ticket->cancellation_reason ?? 'motif non renseigné'
                ),
                lines: [
                    ['account' => '706', 'debit' => (float) $ticket->price_fcfa],
                    ['account' => '571', 'credit' => (float) $ticket->price_fcfa],
                ],
                source: $ticket,
                userId: $ticket->boarded_by,
            );
        } catch (\Throwable $e) {
            Log::error('Échec écriture comptable — annulation/remboursement billet', [
                'reference' => $ticket->reference,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function notifyManagers(string $type, string $title, string $body): void
    {
        $managerIds = User::where('role', Role::MANAGER->value)->pluck('id')->all();

        if ($managerIds) {
            $this->notifications->notify($managerIds, $type, $title, $body);
        }
    }
}
