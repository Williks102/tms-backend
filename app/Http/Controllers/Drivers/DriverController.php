<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/Drivers/DriverController.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers\Drivers;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Services\Drivers\RestComplianceService;
use App\Services\Drivers\EcoScoreService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
