<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\StoreDepartureRequest;
use App\Http\Requests\Planning\UpdateDepartureStatusRequest;
use App\Http\Resources\Planning\DepartureResource;
use App\Models\Departure;
use App\Services\Planning\DepartureService;
use App\Services\Planning\GateAssignmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DepartureController extends Controller
{
    public function __construct(
        private readonly DepartureService $departureService,
        private readonly GateAssignmentService $gateService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/planning/departures
    // ─────────────────────────────────────────────────────────────────────
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Departure::with(['route', 'vehicle', 'driver', 'gate'])
            ->notCancelled();

        // Filtres optionnels
        if ($request->filled('date')) {
            $query->forDate(Carbon::parse($request->date));
        }

        if ($request->filled('route_id')) {
            $query->where('route_id', $request->route_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        $departures = $query
            ->orderBy('departure_datetime')
            ->paginate($request->integer('per_page', 20));

        return DepartureResource::collection($departures);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/planning/departures/live
    // ─────────────────────────────────────────────────────────────────────
    public function live(): AnonymousResourceCollection
    {
        $departures = Departure::with(['route', 'vehicle', 'driver', 'gate'])
            ->live()
            ->orderBy('departure_datetime')
            ->get();

        return DepartureResource::collection($departures);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/planning/departures/{departure}
    // ─────────────────────────────────────────────────────────────────────
    public function show(Departure $departure): DepartureResource
    {
        $departure->load(['route.stops', 'vehicle', 'driver', 'scheduleTemplate', 'gate']);

        return new DepartureResource($departure);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/planning/departures
    // ─────────────────────────────────────────────────────────────────────
    public function store(StoreDepartureRequest $request): JsonResponse
    {
        try {
            $departure = $this->departureService->createManual($request->validated());

            return response()->json([
                'message'   => 'Départ créé avec succès',
                'departure' => new DepartureResource($departure),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // PATCH /api/v1/planning/departures/{departure}/status
    // ─────────────────────────────────────────────────────────────────────
    public function updateStatus(
        UpdateDepartureStatusRequest $request,
        Departure $departure
    ): JsonResponse {
        try {
            $departure = $this->departureService->updateStatus(
                $departure,
                $request->validated('status'),
                $request->validated()
            );

            return response()->json([
                'message'   => 'Statut mis à jour',
                'departure' => new DepartureResource($departure),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // DELETE /api/v1/planning/departures/{departure}
    // ─────────────────────────────────────────────────────────────────────
    public function destroy(Request $request, Departure $departure): JsonResponse
    {
        $request->validate([
            'cancellation_reason' => 'required|string|min:10',
        ]);

        try {
            $this->departureService->cancel($departure, $request->cancellation_reason);

            return response()->json(['message' => 'Départ annulé avec succès']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/planning/vehicles/available?departure_datetime=&estimated_arrival=
    // ─────────────────────────────────────────────────────────────────────
    public function availableVehicles(Request $request): JsonResponse
    {
        $request->validate([
            'departure_datetime' => 'required|date',
            'estimated_arrival'  => 'required|date|after:departure_datetime',
        ]);

        $vehicles = $this->departureService->getAvailableVehicles(
            Carbon::parse($request->departure_datetime),
            Carbon::parse($request->estimated_arrival)
        );

        return response()->json(['data' => $vehicles]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/planning/gates/available?at=
    // ─────────────────────────────────────────────────────────────────────
    public function availableGates(Request $request): JsonResponse
    {
        $request->validate([
            'at'         => 'required|date',
            'station_id' => 'nullable|integer|exists:stations,id',
        ]);

        $gates = $this->gateService->getAvailableGates(
            Carbon::parse($request->at),
            $request->integer('station_id') ?: null
        );

        return response()->json(['data' => $gates]);
    }
}
