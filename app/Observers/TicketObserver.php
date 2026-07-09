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

        // Un billet en ligne naît "pending" (paiement pas encore confirmé,
        // voir TicketService::purchaseOnline()) — pas de notification ni
        // d'écriture comptable ici, ça serait annoncer une vente qui n'a pas
        // encore eu lieu. Voir updated() pour la confirmation réelle.
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

        // Confirmation de paiement en ligne (webhook PaiementPro, voir
        // TicketService::confirmOnlinePayment()) — symétrique de la vente
        // guichet dans created() : écriture comptable + notification manager,
        // mais seulement maintenant que l'argent est réellement encaissé.
        if ($ticket->status === 'paid' && $ticket->getOriginal('status') === 'pending') {
            $this->notifyManagers(
                'ticket.online_sale',
                'Paiement en ligne confirmé',
                "Billet {$ticket->reference} payé en ligne — {$ticket->passenger_name}",
            );

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
                Log::error('Échec écriture comptable — paiement en ligne confirmé', [
                    'reference' => $ticket->reference,
                    'error'     => $e->getMessage(),
                ]);
            }

            return;
        }

        if (!in_array($ticket->status, ['cancelled', 'refunded'], true)) {
            return;
        }

        // Contre-passation uniquement si une vente avait réellement été
        // enregistrée (le billet était "paid") — un billet en ligne jamais
        // payé qui expire (pending → cancelled, voir
        // ExpireOnlinePendingTickets) n'a JAMAIS eu d'écriture de vente,
        // il n'y a donc rien à contre-passer.
        if ($ticket->getOriginal('status') !== 'paid') {
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
