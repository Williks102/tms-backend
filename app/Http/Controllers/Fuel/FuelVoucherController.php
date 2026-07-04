<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/Fuel/FuelVoucherController.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers\Fuel;

use App\Http\Controllers\Controller;
use App\Models\FuelConsumptionLog;
use App\Models\FuelVoucher;
use App\Services\Fuel\FuelValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FuelVoucherController extends Controller
{
    public function __construct(
        private readonly FuelValidationService $fuelService,
    ) {}

    // GET /api/v1/fuel/vouchers
    public function index(Request $request): JsonResponse
    {
        $vouchers = FuelVoucher::with(['driver', 'vehicle', 'departure.route'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->vehicle_id, fn($q) => $q->where('vehicle_id', $request->vehicle_id))
            ->when($request->date, fn($q) => $q->whereDate('requested_at', $request->date))
            ->latest('requested_at')
            ->paginate(20);

        return response()->json($vouchers);
    }

    // POST /api/v1/fuel/vouchers
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'departure_id'    => 'required|exists:departures,id',
            'driver_id'       => 'required|exists:drivers,id',
            'vehicle_id'      => 'required|exists:vehicles,id',
            'requested_liters'=> 'required|numeric|min:1|max:300',
            'fuel_type'       => 'required|in:gasoil,essence,gpl',
            'price_per_liter' => 'required|numeric|min:0',
            'station_name'    => 'nullable|string|max:100',
        ]);

        $voucher = FuelVoucher::create(array_merge($data, [
            'requested_at' => now(),
            'status'       => 'pending',
        ]));

        // Validation automatique: calcul théorique + détection anomalie
        $validation = $this->fuelService->validate($voucher);

        return response()->json([
            'message'    => $validation['valid']
                ? 'Bon soumis — en attente d\'approbation'
                : 'Bon soumis — anomalie détectée, validation manuelle requise',
            'voucher'    => $voucher->fresh(['vehicle', 'driver']),
            'validation' => $validation,
        ], 201);
    }

    // PATCH /api/v1/fuel/vouchers/{voucher}/approve
    public function approve(Request $request, FuelVoucher $voucher): JsonResponse
    {
        if (!$voucher->isPending()) {
            return response()->json([
                'message' => 'Ce bon n\'est pas en attente (statut: ' . $voucher->status . ')',
                'errors'  => ['status' => ['Le bon n\'est pas en attente']],
            ], 422);
        }

        $data = $request->validate([
            'approved_liters' => 'required|numeric|min:1|max:300',
        ]);

        $voucher = $this->fuelService->approve(
            $voucher,
            $data['approved_liters'],
            $request->user()->id
        );

        return response()->json([
            'message' => 'Bon approuvé',
            'voucher' => $voucher,
        ]);
    }

    // PATCH /api/v1/fuel/vouchers/{voucher}/reject
    public function reject(Request $request, FuelVoucher $voucher): JsonResponse
    {
        if (!$voucher->isPending()) {
            return response()->json(['message' => 'Ce bon n\'est pas en attente'], 422);
        }

        $data = $request->validate([
            'rejection_reason' => 'required|string|min:10',
        ]);

        $voucher->update([
            'status'           => 'rejected',
            'rejection_reason' => $data['rejection_reason'],
            'approved_by'      => $request->user()->id,
            'approved_at'      => now(),
        ]);

        return response()->json(['message' => 'Bon refusé', 'voucher' => $voucher]);
    }

    // POST /api/v1/fuel/consumption
    public function recordConsumption(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id'       => 'required|exists:vehicles,id',
            'departure_id'     => 'required|exists:departures,id',
            'fuel_voucher_id'  => 'nullable|exists:fuel_vouchers,id',
            'liters_consumed'  => 'required|numeric|min:0.1',
            'distance_km'      => 'required|numeric|min:1',
            'mileage_before'   => 'required|numeric|min:0',
            'mileage_after'    => 'required|numeric|gt:mileage_before',
        ]);

        $consumption_per_100km = ($data['liters_consumed'] / $data['distance_km']) * 100;

        $log = FuelConsumptionLog::create(array_merge($data, [
            'consumption_per_100km' => round($consumption_per_100km, 2),
            'recorded_at'           => now(),
            'recorded_by'           => $request->user()->id,
        ]));

        // L'Observer FuelConsumptionObserver s'occupe du reste automatiquement

        return response()->json([
            'message'    => 'Consommation enregistrée',
            'log'        => $log,
            'l_per_100km'=> round($consumption_per_100km, 2),
        ], 201);
    }
}