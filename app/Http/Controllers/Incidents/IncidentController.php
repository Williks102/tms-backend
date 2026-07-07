<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/Incidents/IncidentController.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers\Incidents;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\IncidentQualityScore;
use App\Models\Route;
use App\Models\Vehicle;
use App\Services\Incidents\IncidentService;
use App\Services\Incidents\QualityScoreService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    // POST /api/v1/incidents/{incident}/media
    public function uploadMedia(Request $request, Incident $incident): JsonResponse
    {
        $data = $request->validate([
            'file_type'   => 'required|in:photo,video,document,police_report',
            'file'        => 'required|file|mimes:jpg,jpeg,png,mp4,pdf|max:10240', // 10 Mo
            'description' => 'nullable|string|max:200',
        ]);

        // Stocké sur le disque privé ("local"), jamais servi directement par le
        // webserver — même convention que les documents chauffeurs.
        $path = $request->file('file')->store('incident-media', 'local');

        $media = $incident->media()->create([
            'file_path'   => $path,
            'file_type'   => $data['file_type'],
            'description' => $data['description'] ?? null,
            'uploaded_by' => $request->user()->id,
            'uploaded_at' => now(),
        ]);

        return response()->json(['message' => 'Média enregistré', 'media' => $media], 201);
    }

    // GET /api/v1/incidents/{incident}/media/{media}/download
    public function downloadMedia(Incident $incident, IncidentMedia $media): StreamedResponse
    {
        abort_unless($media->incident_id === $incident->id, 404);
        abort_unless(Storage::disk('local')->exists($media->file_path), 404);

        return Storage::disk('local')->download($media->file_path);
    }

    // GET /api/v1/incidents/quality/drivers
    public function qualityDrivers(Request $request): JsonResponse
    {
        return $this->qualityScores('driver', $request, fn($ids) => Driver::whereIn('id', $ids)->get()->keyBy('id'));
    }

    // GET /api/v1/incidents/quality/vehicles
    public function qualityVehicles(Request $request): JsonResponse
    {
        return $this->qualityScores('vehicle', $request, fn($ids) => Vehicle::whereIn('id', $ids)->get()->keyBy('id'));
    }

    // GET /api/v1/incidents/quality/routes — lignes accidentogènes
    public function qualityRoutes(Request $request): JsonResponse
    {
        return $this->qualityScores('route', $request, fn($ids) => Route::whereIn('id', $ids)->get()->keyBy('id'));
    }

    // entity_type/entity_id sur IncidentQualityScore n'est pas un vrai morph
    // Eloquent (juste un enum + un id brut) — impossible d'y attacher une
    // relation ->with(). On recharge donc les entités à la main et on les
    // rattache sous une clé nommée d'après le type (driver/vehicle/route).
    private function qualityScores(string $entityType, Request $request, \Closure $loadEntities): JsonResponse
    {
        $month = Carbon::parse($request->get('month', now()->startOfMonth()))->startOfMonth();

        $scores = IncidentQualityScore::where('entity_type', $entityType)
            ->where('month', $month->toDateString())
            ->orderByDesc('quality_score')
            ->get();

        $entities = $loadEntities($scores->pluck('entity_id'));

        $data = $scores->map(fn($score) => [
            ...$score->toArray(),
            $entityType => $entities->get($score->entity_id),
        ]);

        return response()->json(['month' => $month->format('Y-m'), 'data' => $data]);
    }

    // GET /api/v1/incidents/{incident}/actions
    public function indexActions(Incident $incident): JsonResponse
    {
        return response()->json(['data' => $incident->actions()->with('takenBy')->get()]);
    }

    // DELETE /api/v1/incidents/{incident} — soft delete
    public function destroy(Incident $incident): JsonResponse
    {
        $incident->delete();
        return response()->json(['message' => 'Incident supprimé']);
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
