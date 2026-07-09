<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Audit/ActivityLogger.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * Appel explicite depuis les Services aux points de mutation sensibles —
     * pas d'écoute globale des événements Eloquent (bruyant, et incohérent
     * avec le style du projet où chaque effet de bord est un appel explicite,
     * voir AlertDispatcher::create()/AccountingEntryService::post()).
     */
    public function log(string $action, ?Model $subject, string $description, array $metadata = [], ?int $userId = null): ActivityLog
    {
        return ActivityLog::create([
            'user_id'      => $userId,
            'action'       => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id'   => $subject?->getKey(),
            'description'  => $description,
            'metadata'     => $metadata,
        ]);
    }
}
