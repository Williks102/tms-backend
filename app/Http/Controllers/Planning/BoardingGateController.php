<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Models\BoardingGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardingGateController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/planning/gates
    // ─────────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = BoardingGate::with('station');

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->station_id);
        }

        $gates = $query->get()
            ->sortBy(fn (BoardingGate $g) => $g->station->name . $g->gate_code)
            ->values();

        return response()->json(['data' => $gates]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/planning/gates
    // ─────────────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'station_id' => 'required|integer|exists:stations,id',
            'gate_code'  => 'required|string|max:10',
            'is_active'  => 'boolean',
        ]);

        $exists = BoardingGate::where('station_id', $data['station_id'])
            ->where('gate_code', $data['gate_code'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => "Le quai {$data['gate_code']} existe déjà pour cette gare.",
            ], 422);
        }

        $gate = BoardingGate::create($data);

        return response()->json([
            'message' => 'Quai créé avec succès',
            'gate'    => $gate->load('station'),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PUT /api/v1/planning/gates/{gate}
    // ─────────────────────────────────────────────────────────────────────
    public function update(Request $request, BoardingGate $gate): JsonResponse
    {
        $data = $request->validate([
            'station_id' => 'integer|exists:stations,id',
            'gate_code'  => 'string|max:10',
            'is_active'  => 'boolean',
        ]);

        $stationId = $data['station_id'] ?? $gate->station_id;
        $code      = $data['gate_code'] ?? $gate->gate_code;

        $conflict = BoardingGate::where('station_id', $stationId)
            ->where('gate_code', $code)
            ->where('id', '!=', $gate->id)
            ->exists();

        if ($conflict) {
            return response()->json([
                'message' => "Le quai {$code} existe déjà pour cette gare.",
            ], 422);
        }

        $gate->update($data);

        return response()->json([
            'message' => 'Quai mis à jour',
            'gate'    => $gate->fresh('station'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // DELETE /api/v1/planning/gates/{gate}
    // ─────────────────────────────────────────────────────────────────────
    public function destroy(BoardingGate $gate): JsonResponse
    {
        $futureDepartures = $gate->departures()
            ->where('departure_datetime', '>', now())
            ->whereNotIn('status', ['cancelled'])
            ->count();

        if ($futureDepartures > 0) {
            return response()->json([
                'message' => "Impossible de supprimer: {$futureDepartures} départ(s) futur(s) assigné(s) à ce quai. Désactivez-le plutôt.",
            ], 422);
        }

        $gate->delete();

        return response()->json(['message' => 'Quai supprimé avec succès']);
    }
}
