<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/Incidents/IncidentController.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers\Incidents;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\IncidentQualityScore;
use App\Services\Incidents\IncidentService;
use App\Services\Incidents\QualityScoreService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function __construct(
        private readonly IncidentService    $incidentService,
        private readonly QualityScoreService $qualityService,
    ) {}

    // GET /api/v1/incidents
    public function index(Request $request): JsonResponse
    {
        $query = Incident::with(['vehicle', 'driver', 'departure.route', 'reportedBy']);

        if ($request->filled('status'))    $query->where('status', $request->status);
        if ($request->filled('severity'))  $query->where('severity', $request->severity);
        if ($request->filled('category'))  $query->where('category', $request->category);
        if ($request->filled('driver_id')) $query->where('driver_id', $request->driver_id);
        if ($request->filled('vehicle_id'))$query->where('vehicle_id', $request->vehicle_id);
        if ($request->filled('from'))      $query->where('occurred_at', '>=', $request->from);
        if ($request->filled('to'))        $query->where('occurred_at', '<=', $request->to);

        $incidents = $query->latest('occurred_at')->paginate(20);

        return response()->json($incidents);
    }

    // POST /api/v1/incidents
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'departure_id'          => 'nullable|exists:departures,id',
            'vehicle_id'            => 'required|exists:vehicles,id',
            'driver_id'             => 'required|exists:drivers,id',
            'category'              => 'required|in:mechanical,accident,passenger,road,driver,other',
            'severity'              => 'required|in:low,medium,high,critical',
            'title'                 => 'required|string|max:200',
            'description'           => 'required|string',
            'location'              => 'nullable|string|max:200',
            'occurred_at'           => 'required|date',
            'financial_impact_fcfa' => 'nullable|numeric|min:0',
        ]);

        try {
            $incident = $this->incidentService->create($data, $request->user()->id);
            return response()->json([
                'message'  => "Incident {$incident->reference} créé",
                'incident' => $incident,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /api/v1/incidents/mine — libre-service chauffeur : driver_id forcé
    // côté serveur (jamais celui du body, contrairement à store() qui reste
    // utilisé par un dispatcher/manager pour signaler au nom d'un autre chauffeur)
    public function storeMine(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        abort_unless($driver, 403, 'Ce compte n\'est lié à aucun profil chauffeur');

        $data = $request->validate([
            'departure_id'          => 'nullable|exists:departures,id',
            'vehicle_id'            => 'required|exists:vehicles,id',
            'category'              => 'required|in:mechanical,accident,passenger,road,driver,other',
            'severity'              => 'required|in:low,medium,high,critical',
            'title'                 => 'required|string|max:200',
            'description'           => 'required|string',
            'location'              => 'nullable|string|max:200',
            'occurred_at'           => 'required|date',
        ]);

        $data['driver_id'] = $driver->id;

        try {
            $incident = $this->incidentService->create($data, $request->user()->id);
            return response()->json([
                'message'  => "Incident {$incident->reference} créé",
                'incident' => $incident,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // GET /api/v1/incidents/{incident}
    public function show(Incident $incident): JsonResponse
    {
        $incident->load(['vehicle', 'driver', 'departure.route', 'actions.takenBy', 'media', 'reportedBy']);
        return response()->json($incident);
    }

    // PATCH /api/v1/incidents/{incident}/status
    public function updateStatus(Request $request, Incident $incident): JsonResponse
    {
        $data = $request->validate([
            'status'           => 'required|in:investigating,resolved,closed',
            'resolution_notes' => 'nullable|string|required_if:status,resolved,closed',
        ]);

        try {
            $incident = $this->incidentService->updateStatus($incident, $data['status'], $data);
            return response()->json(['message' => 'Statut mis à jour', 'incident' => $incident]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /api/v1/incidents/{incident}/actions
    public function addAction(Request $request, Incident $incident): JsonResponse
    {
        $data = $request->validate([
            'action_type' => 'required|in:repair,medical,police,tow,replacement_vehicle,passenger_support,other',
            'description' => 'required|string',
            'cost_fcfa'   => 'nullable|numeric|min:0',
        ]);

        $action = $incident->actions()->create(array_merge($data, [
            'taken_by' => $request->user()->id,
            'taken_at' => now(),
        ]));

        // Mise à jour du coût total de l'incident
        $totalCost = $incident->actions()->sum('cost_fcfa');
        $incident->update(['financial_impact_fcfa' => $totalCost]);

        return response()->json(['message' => 'Action enregistrée', 'action' => $action], 201);
    }

    // GET /api/v1/incidents/quality/drivers
    public function qualityDrivers(Request $request): JsonResponse
    {
        $month = Carbon::parse($request->get('month', now()->startOfMonth()));

        $scores = IncidentQualityScore::where('entity_type', 'driver')
            ->where('month', $month->toDateString())
            ->orderByDesc('quality_score')
            ->with('driver')
            ->get();

        return response()->json(['month' => $month->format('Y-m'), 'data' => $scores]);
    }

    // GET /api/v1/incidents/stats
    public function stats(): JsonResponse
    {
        return response()->json([
            'open_count'     => Incident::open()->count(),
            'critical_count' => Incident::critical()->where('status', '!=', 'closed')->count(),
            'this_month'     => Incident::whereMonth('occurred_at', now()->month)->count(),
            'by_category'    => Incident::selectRaw('category, COUNT(*) as count')
                ->groupBy('category')->pluck('count', 'category'),
            'by_severity'    => Incident::selectRaw('severity, COUNT(*) as count')
                ->groupBy('severity')->pluck('count', 'severity'),
            'avg_resolution_hours' => Incident::whereNotNull('resolved_at')
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (resolved_at - occurred_at))/3600) as avg')
                ->value('avg'),
        ]);
    }
}
