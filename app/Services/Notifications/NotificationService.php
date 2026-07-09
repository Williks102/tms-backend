<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Notifications/NotificationService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Notifications;

use App\Models\AppNotification;

class NotificationService
{
    public function __construct(
        private readonly NotificationChannel $channel,
    ) {}

    /**
     * @param int|int[] $userIds
     */
    public function notify(int|array $userIds, string $type, string $title, string $body, array $data = []): void
    {
        foreach ((array) $userIds as $userId) {
            $notification = AppNotification::create([
                'user_id' => $userId,
                'type'    => $type,
                'title'   => $title,
                'body'    => $body,
                'data'    => $data,
            ]);

            $this->channel->send($notification);
        }
    }
}
