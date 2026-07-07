<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/BoardController.php
// Écran public "Départs / Arrivées" (panneau de gare) — endpoint SANS
// auth:sanctum (voir routes/api.php), consommé sans compte utilisateur.
// Ne jamais y exposer de donnée personnelle (chauffeur, notes internes...).
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers;

use App\Http\Resources\Planning\BoardDepartureResource;
use App\Models\Station;
use App\Services\Planning\BoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    public function __construct(
        private readonly BoardService $boardService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/board/live?station_id=
    // ─────────────────────────────────────────────────────────────────────
    public function live(Request $request): JsonResponse
    {
        $result = $this->boardService->forStation($request->integer('station_id') ?: null);

        return response()->json([
            'station'      => $result['station']?->only(['id', 'name', 'city']),
            'departures'   => BoardDepartureResource::collection($result['departures']),
            'arrivals'     => BoardDepartureResource::collection($result['arrivals']),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/board/stations
    // ─────────────────────────────────────────────────────────────────────
    public function stations(): JsonResponse
    {
        $stations = Station::active()
            ->orderBy('name')
            ->get(['id', 'name', 'city']);

        return response()->json(['data' => $stations]);
    }
}
