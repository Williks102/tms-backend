<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/Drivers/DriverController.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers\Drivers;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Services\Drivers\RestComplianceService;
use App\Services\Drivers\EcoScoreService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverController extends Controller
{
    public function __construct(
        private readonly RestComplianceService $restService,
        private readonly EcoScoreService $ecoService,
    ) {}

    // GET /api/v1/drivers
    public function index(Request $request): JsonResponse
    {
        $query = Driver::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $drivers = $query->orderBy('last_name')->paginate(20);

        return response()->json($drivers);
    }

    // POST /api/v1/drivers
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_number'     => 'required|string|unique:drivers',
            'first_name'          => 'required|string|max:100',
            'last_name'           => 'required|string|max:100',
            'phone'               => 'required|string|max:20',
            'license_number'      => 'required|string|unique:drivers',
            'license_category'    => 'required|string|max:10',
            'license_expires_at'  => 'required|date|after:today',
            'medical_expires_at'  => 'required|date|after:today',
            'hired_at'            => 'required|date',
        ]);

        $driver = Driver::create($data);

        return response()->json([
            'message' => 'Chauffeur créé avec succès',
            'driver'  => $driver,
        ], 201);
    }

    public function updateStatus(Request $request, Driver $driver): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:available,on_duty,resting,on_leave,suspended',
        ]);

        $driver->update(['status' => $data['status']]);

        return response()->json([
            'message' => 'Statut mis à jour',
            'driver'  => $driver->fresh(),
        ]);
    }

    // POST /api/v1/drivers/{driver}/documents
    public function uploadDocument(Request $request, Driver $driver): JsonResponse
    {
        $data = $request->validate([
            'type'       => 'required|in:license,medical,contract,other',
            'file'       => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5 Mo
            'expires_at' => 'nullable|date',
        ]);

        // Stocké sur le disque privé ("local") — jamais servi directement par le
        // webserver ; le nom de fichier est généré par Storage, jamais fourni par le client.
        $path = $request->file('file')->store('driver-documents', 'local');

        $document = DriverDocument::create([
            'driver_id'   => $driver->id,
            'type'        => $data['type'],
            'file_path'   => $path,
            'expires_at'  => $data['expires_at'] ?? null,
            'uploaded_at' => now(),
        ]);

        return response()->json([
            'message' => 'Document enregistré',
            'document' => $document,
        ], 201);
    }

    // GET /api/v1/drivers/{driver}/documents/{document}/download
    public function downloadDocument(Driver $driver, DriverDocument $document): StreamedResponse
    {
        abort_unless($document->driver_id === $driver->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path);
    }

    // GET /api/v1/drivers/{driver}
    public function show(Driver $driver): JsonResponse
    {
        $driver->load([
            'restLogs'     => fn($q) => $q->latest('duty_start')->limit(10),
            'tripStats'    => fn($q) => $q->latest('recorded_at')->limit(20),
            'documents',
            'monthlyScores'=> fn($q) => $q->limit(6),
        ]);

        return response()->json([
            'driver'      => $driver,
            'recent_score'=> $this->ecoService->recentAverage($driver->id),
            'score_level' => $this->ecoService->recentAverage($driver->id)
                ? $this->ecoService->getLevel($this->ecoService->recentAverage($driver->id))
                : null,
        ]);
    }

    // GET /api/v1/drivers/available?departure_datetime=&estimated_arrival=
    public function available(Request $request): JsonResponse
    {
        $request->validate([
            'departure_datetime' => 'required|date',
        ]);

        $at = Carbon::parse($request->departure_datetime);

        $drivers = $this->restService->getAvailableDrivers($at);

        return response()->json(['data' => $drivers]);
    }

    // GET /api/v1/drivers/{driver}/rest/check?departure_datetime=
    public function checkRest(Request $request, Driver $driver): JsonResponse
    {
        $request->validate(['departure_datetime' => 'required|date']);

        $result = $this->restService->canDrive(
            $driver,
            Carbon::parse($request->departure_datetime)
        );

        return response()->json($result, $result['compliant'] ? 200 : 422);
    }

    // POST /api/v1/drivers/{driver}/rest/end
    public function endRest(Driver $driver): JsonResponse
    {
        try {
            $log = $this->restService->endRest($driver);
            return response()->json([
                'message'          => 'Repos terminé — chauffeur disponible',
                'rest_duration_min'=> $log->rest_duration_min,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // GET /api/v1/drivers/scores/monthly
    public function monthlyScores(Request $request): JsonResponse
    {
        $month = $request->filled('month')
            ? Carbon::parse($request->month)->startOfMonth()
            : now()->startOfMonth();

        $scores = \App\Models\DriverMonthlyScore::with('driver')
            ->where('month', $month->toDateString())
            ->orderBy('rank')
            ->get();

        return response()->json(['data' => $scores, 'month' => $month->format('Y-m')]);
    }

    // GET /api/v1/drivers/documents/expiring?days=30
    public function expiringDocuments(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);

        $documents = DriverDocument::with('driver')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', now()->addDays($days))
            ->orderBy('expires_at')
            ->get();

        return response()->json(['data' => $documents]);
    }
}
