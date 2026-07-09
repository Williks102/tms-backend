<?php

namespace App\Services\Notifications;

use App\Models\AppNotification;

/**
 * Un seul point d'extension : brancher un vrai SMS/email plus tard = une
 * nouvelle classe qui implémente cette interface + un changement de binding
 * dans AppServiceProvider, jamais un changement des appelants de NotificationService.
 */
interface NotificationChannel
{
    public function send(AppNotification $notification): void;
}
