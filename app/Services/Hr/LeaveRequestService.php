<?php

namespace App\Services\Hr;

use App\Models\Driver;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class LeaveRequestService
{
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

        return $leave->fresh();
    }
}
