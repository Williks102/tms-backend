<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Observers/TicketObserver.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Observers;

use App\Models\Ticket;
use Illuminate\Support\Facades\Log;

class TicketObserver
{
    public function created(Ticket $ticket): void
    {
        Log::info('Billet émis', [
            'reference'    => $ticket->reference,
            'departure_id' => $ticket->departure_id,
            'channel'      => $ticket->channel,
        ]);
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
    }
}
