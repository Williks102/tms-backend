<?php

namespace App\Services\Hr;

use App\Models\DisciplinaryRecord;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Database\Eloquent\Model;

class DisciplinaryRecordService
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly NotificationService $notifications,
    ) {}

    private const TYPE_LABEL = [
        'avertissement_verbal' => 'Avertissement verbal',
        'avertissement_ecrit'  => 'Avertissement écrit',
        'mise_a_pied'          => 'Mise à pied',
        'autre'                => 'Autre',
    ];

    public function create(Model $employable, array $data, int $issuedBy): DisciplinaryRecord
    {
        $record = DisciplinaryRecord::create([
            'employable_type' => $employable::class,
            'employable_id'   => $employable->getKey(),
            'type'            => $data['type'],
            'description'     => $data['description'],
            'issued_by'       => $issuedBy,
            'issued_at'       => now(),
        ]);

        $this->activityLogger->log(
            'disciplinary.created',
            $record,
            "Enregistrement disciplinaire ({$data['type']}) créé",
            userId: $issuedBy,
        );

        $this->notifyEmployable($employable, $data['type'], $data['description']);

        return $record->fresh(['employable', 'issuedBy']);
    }

    /**
     * Notifie l'employé concerné — un `User` directement, un `Driver`
     * seulement s'il a un compte de connexion lié (drivers.user_id, voir
     * portail chauffeur libre-service). Silencieux sinon (pas de compte à notifier).
     * Même pattern que LeaveRequestService::notifyEmployable().
     */
    private function notifyEmployable(Model $employable, string $type, string $description): void
    {
        $userId = $employable instanceof User ? $employable->id : $employable->user_id;

        if ($userId) {
            $label = self::TYPE_LABEL[$type] ?? $type;
            $this->notifications->notify($userId, 'disciplinary.created', 'Enregistrement disciplinaire', "{$label} : {$description}");
        }
    }
}
