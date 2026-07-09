<?php

namespace App\Services\Notifications;

use App\Models\AppNotification;
use Illuminate\Support\Facades\Log;

/**
 * Canal actif par défaut — aucun fournisseur SMS/email n'est configuré dans
 * ce projet. La notification est déjà persistée en base (visible dans
 * l'app via la cloche de notifications) ; ce canal se contente de tracer
 * l'envoi dans les logs, comme le ferait un vrai canal SMS pour audit.
 */
class LogChannel implements NotificationChannel
{
    public function send(AppNotification $notification): void
    {
        Log::info('Notification envoyée (canal interne)', [
            'id'      => $notification->id,
            'user_id' => $notification->user_id,
            'type'    => $notification->type,
            'title'   => $notification->title,
        ]);
    }
}
