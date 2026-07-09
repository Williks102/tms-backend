<?php

namespace App\Http\Controllers\Vehicles;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/vehicles
    // ─────────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = Vehicle::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(fn ($q) => $q
                ->where('plate_number', 'like', "%{$term}%")
                ->orWhere('model', 'like', "%{$term}%"));
        }

        $vehicles = $query->orderBy('plate_number')->paginate($request->integer('per_page', 30));

        return response()->json($vehicles);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/vehicles
    // ─────────────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plate_number'               => 'required|string|max:20|unique:vehicles,plate_number',
            'model'                      => 'required|string|max:100',
            'capacity'                   => 'required|integer|min:1',
            'fuel_consumption_per_100km' => 'required|numeric|min:0',
            'current_mileage_km'         => 'nullable|numeric|min:0',
            'maintenance_interval_km'    => 'nullable|numeric|min:0',
            'status'                     => 'nullable|in:available,on_trip,boarding,maintenance,inactive',
            'notes'                      => 'nullable|string',
            'cargo_capacity_kg'          => 'nullable|numeric|min:0',
        ]);

        $vehicle = Vehicle::create($data);

        return response()->json([
            'message' => 'Véhicule créé avec succès',
            'vehicle' => $vehicle,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/vehicles/{vehicle}
    // ─────────────────────────────────────────────────────────────────────
    public function show(Vehicle $vehicle): JsonResponse
    {
        $vehicle->load([
            'maintenancePlans'    => fn ($q) => $q->orderByRaw('trigger_km IS NULL, trigger_km ASC'),
            'maintenanceRecords'  => fn ($q) => $q->latest('performed_at')->limit(10),
            'incidents'           => fn ($q) => $q->latest('occurred_at')->limit(10),
            'fuelConsumptionLogs' => fn ($q) => $q->latest('recorded_at')->limit(10),
        ]);

        return response()->json([
            'vehicle'               => $vehicle,
            'needs_maintenance'     => $vehicle->needsMaintenance(),
            'km_before_maintenance' => $vehicle->kmBeforeNextMaintenance(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PUT /api/v1/vehicles/{vehicle}
    // ─────────────────────────────────────────────────────────────────────
    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->validate([
            'plate_number'               => "string|max:20|unique:vehicles,plate_number,{$vehicle->id}",
            'model'                      => 'string|max:100',
            'capacity'                   => 'integer|min:1',
            'fuel_consumption_per_100km' => 'numeric|min:0',
            'current_mileage_km'         => 'numeric|min:0',
            'maintenance_interval_km'    => 'numeric|min:0',
            'status'                     => 'in:available,on_trip,boarding,maintenance,inactive',
            'notes'                      => 'nullable|string',
            'cargo_capacity_kg'          => 'nullable|numeric|min:0',
        ]);

        $vehicle->update($data);

        return response()->json([
            'message' => 'Véhicule mis à jour',
            'vehicle' => $vehicle->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // DELETE /api/v1/vehicles/{vehicle}  (soft delete)
    // ─────────────────────────────────────────────────────────────────────
    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $activeDepartures = $vehicle->departures()
            ->whereNotIn('status', ['cancelled', 'arrived'])
            ->count();

        if ($activeDepartures > 0) {
            return response()->json([
                'message' => "Impossible de supprimer : {$activeDepartures} départ(s) actif(s) ou à venir sur ce véhicule. Passez-le en maintenance/inactif plutôt.",
            ], 422);
        }

        $vehicle->update(['status' => 'inactive']);
        $vehicle->delete();

        return response()->json(['message' => 'Véhicule désactivé avec succès']);
    }
}
