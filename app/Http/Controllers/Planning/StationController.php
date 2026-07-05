<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StationController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/planning/stations
    // ─────────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = Station::withCount('gates');

        if ($request->filled('city')) {
            $query->where('city', 'like', "%{$request->city}%");
        }

        $stations = $query->orderBy('name')->get();

        return response()->json(['data' => $stations]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/planning/stations
    // ─────────────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100|unique:stations,name',
            'city'      => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);

        $station = Station::create($data);

        return response()->json([
            'message' => 'Gare créée avec succès',
            'station' => $station,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PUT /api/v1/planning/stations/{station}
    // ─────────────────────────────────────────────────────────────────────
    public function update(Request $request, Station $station): JsonResponse
    {
        $data = $request->validate([
            'name'      => "string|max:100|unique:stations,name,{$station->id}",
            'city'      => 'string|max:100',
            'is_active' => 'boolean',
        ]);

        $station->update($data);

        return response()->json([
            'message' => 'Gare mise à jour',
            'station' => $station->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // DELETE /api/v1/planning/stations/{station}
    // ─────────────────────────────────────────────────────────────────────
    public function destroy(Station $station): JsonResponse
    {
        $gatesCount = $station->gates()->count();
        if ($gatesCount > 0) {
            return response()->json([
                'message' => "Impossible de supprimer: {$gatesCount} quai(x) rattaché(s) à cette gare. Supprimez-les d'abord.",
            ], 422);
        }

        $routesCount = $station->routes()->count();
        if ($routesCount > 0) {
            return response()->json([
                'message' => "Impossible de supprimer: {$routesCount} ligne(s) ont cette gare comme point de départ.",
            ], 422);
        }

        $station->delete();

        return response()->json(['message' => 'Gare supprimée avec succès']);
    }
}
