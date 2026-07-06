<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Hr\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveRequestService $service,
    ) {}

    // GET /api/v1/hr/leaves
    public function index(Request $request): JsonResponse
    {
        $query = LeaveRequest::with(['employable', 'decidedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employable_type') && $request->filled('employable_id')) {
            $modelClass = $request->employable_type === 'driver' ? Driver::class : User::class;
            $query->where('employable_type', $modelClass)->where('employable_id', $request->employable_id);
        }

        $leaves = $query->latest('requested_at')->paginate($request->integer('per_page', 20));

        return response()->json($leaves);
    }

    // POST /api/v1/hr/leaves
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employable_type' => 'required|in:user,driver',
            'employable_id'   => 'required|integer',
            'type'            => 'required|in:conge_paye,maladie,sans_solde,autre',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date',
            'reason'          => 'nullable|string|max:500',
        ]);

        $modelClass = $data['employable_type'] === 'driver' ? Driver::class : User::class;
        $employable = $modelClass::findOrFail($data['employable_id']);

        try {
            $leave = $this->service->request($employable, $data);

            return response()->json([
                'message' => 'Demande de congé créée',
                'leave'   => $leave->load('employable'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // PATCH /api/v1/hr/leaves/{leave}/approve
    public function approve(Request $request, LeaveRequest $leave): JsonResponse
    {
        try {
            $leave = $this->service->approve($leave, $request->user()->id);

            return response()->json(['message' => 'Congé approuvé', 'leave' => $leave->load('employable')]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // PATCH /api/v1/hr/leaves/{leave}/reject
    public function reject(Request $request, LeaveRequest $leave): JsonResponse
    {
        $request->validate(['decision_notes' => 'required|string|min:5']);

        try {
            $leave = $this->service->reject($leave, $request->user()->id, $request->decision_notes);

            return response()->json(['message' => 'Congé refusé', 'leave' => $leave->load('employable')]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
