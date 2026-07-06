<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/hr/employees
    // Fusionne Users (staff) et Drivers en une liste unique — pas de table
    // "Employee" dédiée, chacun reste sa propre source de vérité.
    // ─────────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $users = User::all()->map(fn (User $u) => [
            'type'              => 'user',
            'id'                => $u->id,
            'name'              => $u->name,
            'contact'           => trim($u->email . ($u->phone ? " · {$u->phone}" : '')),
            'role_or_position'  => $u->role->label(),
            'status'            => null,
            'hired_at'          => $u->hired_at?->toDateString(),
            'contract_type'     => $u->contract_type,
            'contract_end_date' => $u->contract_end_date?->toDateString(),
        ]);

        $drivers = Driver::all()->map(fn (Driver $d) => [
            'type'              => 'driver',
            'id'                => $d->id,
            'name'              => $d->fullName(),
            'contact'           => $d->phone,
            'role_or_position'  => 'Chauffeur',
            'status'            => $d->status,
            'hired_at'          => $d->hired_at?->toDateString(),
            'contract_type'     => $d->contract_type,
            'contract_end_date' => $d->contract_end_date?->toDateString(),
        ]);

        $employees = $users->concat($drivers);

        if ($request->filled('search')) {
            $term = mb_strtolower($request->string('search'));
            $employees = $employees->filter(fn ($e) => str_contains(mb_strtolower($e['name']), $term));
        }

        if ($request->filled('type')) {
            $employees = $employees->where('type', $request->string('type'));
        }

        $employees = $employees->sortBy('name')->values();

        $perPage = $request->integer('per_page', 30);
        $page    = max(1, $request->integer('page', 1));
        $total   = $employees->count();

        return response()->json([
            'data'         => $employees->forPage($page, $perPage)->values(),
            'total'        => $total,
            'current_page' => $page,
            'last_page'    => (int) max(1, ceil($total / $perPage)),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/hr/employees/{type}/{id}   type = user|driver
    // ─────────────────────────────────────────────────────────────────────
    public function show(string $type, int $id): JsonResponse
    {
        if ($type === 'user') {
            $user = User::findOrFail($id);
            $user->load([
                'leaveRequests' => fn ($q) => $q->with('decidedBy')->latest('requested_at'),
                'disciplinaryRecords' => fn ($q) => $q->with('issuedBy')->latest('issued_at'),
            ]);

            return response()->json(['type' => 'user', 'employee' => $user]);
        }

        if ($type === 'driver') {
            $driver = Driver::findOrFail($id);
            $driver->load([
                'leaveRequests' => fn ($q) => $q->with('decidedBy')->latest('requested_at'),
                'disciplinaryRecords' => fn ($q) => $q->with('issuedBy')->latest('issued_at'),
                'documents',
            ]);

            return response()->json(['type' => 'driver', 'employee' => $driver]);
        }

        abort(404, "Type d'employé inconnu: {$type}");
    }
}
