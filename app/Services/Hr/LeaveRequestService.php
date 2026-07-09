<?php

namespace App\Services\Hr;

use App\Models\Driver;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Notifications\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class LeaveRequestService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ActivityLogger $activityLogger,
    ) {}
    /**
     * Crée une demande de congé pour un employé (User ou Driver).
     *
     * @throws \Exception si les dates sont invalides ou chevauchent une
     *                     demande déjà en attente/approuvée pour cette personne
     */
    public function request(Model $employable, array $data): LeaveRequest
    {
        $start = Carbon::parse($data['start_date']);
        $end   = Carbon::parse($data['end_date']);

        if ($end->lt($start)) {
            throw new \Exception('La date de fin doit être après la date de début');
        }

        $overlap = LeaveRequest::where('employable_type', $employable::class)
            ->where('employable_id', $employable->getKey())
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->exists();

        if ($overlap) {
            throw new \Exception('Une demande de congé chevauchante existe déjà pour cette personne');
        }

        return LeaveRequest::create([
            'employable_type' => $employable::class,
            'employable_id'   => $employable->getKey(),
            'type'            => $data['type'],
            'start_date'      => $start,
            'end_date'        => $end,
            'reason'          => $data['reason'] ?? null,
            'status'          => 'pending',
            'requested_at'    => now(),
        ]);
    }

    /**
     * Approuve une demande de congé. Si l'employé est un chauffeur et que la
     * période couvre aujourd'hui, son statut passe immédiatement à "on_leave"
     * (effet ponctuel — aucun job planifié ne le remettra "available" à la
     * fin du congé, cela reste une action manuelle du RH/manager).
     *
     * @throws \Exception si la demande n'est plus "pending"
     */
    public function approve(LeaveRequest $leave, int $decidedBy): LeaveRequest
    {
        if ($leave->status !== 'pending') {
            throw new \Exception("Cette demande n'est plus en attente (statut: {$leave->status})");
        }

        $leave->update([
            'status'      => 'approved',
            'decided_by'  => $decidedBy,
            'decided_at'  => now(),
        ]);

        $leave = $leave->fresh();
        $employable = $leave->employable;

        if ($employable instanceof Driver && $leave->overlapsToday()) {
            $employable->update(['status' => 'on_leave']);
        }

        $this->notifyEmployable($employable, 'leave.approved', 'Congé approuvé', "Votre demande de congé du {$leave->start_date->format('d/m/Y')} au {$leave->end_date->format('d/m/Y')} a été approuvée.");
        $this->activityLogger->log('leave.approved', $leave, "Congé #{$leave->id} approuvé", userId: $decidedBy);

        return $leave;
    }

    /**
     * Refuse une demande de congé. Le motif est obligatoire.
     *
     * @throws \Exception si la demande n'est plus "pending"
     */
    public function reject(LeaveRequest $leave, int $decidedBy, string $notes): LeaveRequest
    {
        if ($leave->status !== 'pending') {
            throw new \Exception("Cette demande n'est plus en attente (statut: {$leave->status})");
        }

        $leave->update([
            'status'         => 'rejected',
            'decided_by'     => $decidedBy,
            'decided_at'     => now(),
            'decision_notes' => $notes,
        ]);

        $leave = $leave->fresh();
        $this->notifyEmployable($leave->employable, 'leave.rejected', 'Congé refusé', "Votre demande de congé du {$leave->start_date->format('d/m/Y')} au {$leave->end_date->format('d/m/Y')} a été refusée : {$notes}");
        $this->activityLogger->log('leave.rejected', $leave, "Congé #{$leave->id} refusé — {$notes}", userId: $decidedBy);

        return $leave;
    }

    /**
     * Notifie l'employé de la décision — un `User` directement, un `Driver`
     * seulement s'il a un compte de connexion lié (drivers.user_id, voir
     * portail chauffeur libre-service). Silencieux sinon (pas de compte à notifier).
     */
    private function notifyEmployable(Model $employable, string $type, string $title, string $body): void
    {
        $userId = $employable instanceof User ? $employable->id : $employable->user_id;

        if ($userId) {
            $this->notifications->notify($userId, $type, $title, $body);
        }
    }
}
