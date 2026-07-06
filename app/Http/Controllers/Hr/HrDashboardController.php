<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DisciplinaryRecord;
use App\Models\DriverDocument;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class HrDashboardController extends Controller
{
    // GET /api/v1/hr/dashboard
    public function index(): JsonResponse
    {
        $users   = User::all();
        $drivers = Driver::all();
        $horizon = now()->addDays(30);

        $headcount = [
            'staff'     => $users->count(),
            'drivers'   => $drivers->count(),
            'by_status' => $drivers->groupBy('status')->map->count(),
        ];

        $contractsExpiring = $users->filter(fn (User $u) => $u->contract_end_date && $u->contract_end_date->lte($horizon))
            ->map(fn (User $u) => [
                'type' => 'user', 'id' => $u->id, 'name' => $u->name,
                'contract_end_date' => $u->contract_end_date->toDateString(),
            ])
            ->concat(
                $drivers->filter(fn (Driver $d) => $d->contract_end_date && $d->contract_end_date->lte($horizon))
                    ->map(fn (Driver $d) => [
                        'type' => 'driver', 'id' => $d->id, 'name' => $d->fullName(),
                        'contract_end_date' => $d->contract_end_date->toDateString(),
                    ])
            )
            ->sortBy('contract_end_date')->values();

        $documentsExpiring = DriverDocument::with('driver')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', $horizon)
            ->orderBy('expires_at')
            ->get();

        $leavesPending = LeaveRequest::with('employable')->pending()->latest('requested_at')->get();

        $today = now()->toDateString();
        $leavesActiveToday = LeaveRequest::with('employable')
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->get();

        $recentDisciplinary = DisciplinaryRecord::with(['employable', 'issuedBy'])
            ->latest('issued_at')
            ->limit(5)
            ->get();

        return response()->json([
            'headcount'           => $headcount,
            'contracts_expiring'  => $contractsExpiring,
            'documents_expiring'  => $documentsExpiring,
            'leaves_pending'      => $leavesPending,
            'leaves_active_today' => $leavesActiveToday,
            'recent_disciplinary' => $recentDisciplinary,
        ]);
    }
}
