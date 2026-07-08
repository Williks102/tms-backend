<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Observers/TicketObserver.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Observers;

use App\Models\Ticket;
use App\Services\Accounting\AccountingEntryService;
use Illuminate\Support\Facades\Log;

class TicketObserver
{
    public function __construct(
        private readonly AccountingEntryService $accounting,
    ) {}

    public function created(Ticket $ticket): void
    {
        Log::info('Billet émis', [
            'reference'    => $ticket->reference,
            'departure_id' => $ticket->departure_id,
            'channel'      => $ticket->channel,
        ]);

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
}
