<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    // GET /api/v1/audit — role:manager,dg
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', "%{$request->string('action')}%"))
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->subject_type))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to));

        $logs = $query->latest()->paginate($request->integer('per_page', 30));

        return response()->json($logs);
    }
}
