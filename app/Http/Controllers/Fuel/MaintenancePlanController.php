<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/Fuel/MaintenancePlanController.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers\Fuel;

use App\Http\Controllers\Controller;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenancePlanController extends Controller
{
    // GET /api/v1/maintenance/plans
    public function index(Request $request): JsonResponse
    {
        $query = MaintenancePlan::with('vehicle')
            ->when($request->vehicle_id, fn($q) => $q->where('vehicle_id', $request->vehicle_id))
            ->when($request->status,     fn($q) => $q->where('status', $request->status))
            ->orderByRaw('trigger_km IS NULL, trigger_km ASC');

        return response()->json($query->paginate(20));
    }

    // POST /api/v1/maintenance/plans
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id'       => 'required|exists:vehicles,id',
            'type'             => 'required|in:oil_change,tire,brake,full_service,other',
            'trigger_km'       => 'nullable|numeric|min:0',
            'trigger_date'     => 'nullable|date',
            'interval_km'      => 'nullable|numeric|min:0',
            'interval_days'    => 'nullable|integer|min:1',
            'alert_km_before'  => 'nullable|numeric|min:0',
            'estimated_cost'   => 'nullable|numeric|min:0',
            'notes'            => 'nullable|string',
        ]);

        $plan = MaintenancePlan::create($data);

        return response()->json([
            'message' => 'Plan de maintenance créé',
            'plan'    => $plan->load('vehicle'),
        ], 201);
    }

    // GET /api/v1/maintenance/due
    public function due(): JsonResponse
    {
        $plans = MaintenancePlan::with('vehicle')
            ->active()  // upcoming + due seulement
            ->get()
            ->map(function ($plan) {
                if (!$plan->vehicle) return null;
                
                $remaining = $plan->kmRemaining($plan->vehicle);
                
                // Mettre à jour le statut basé sur le kilométrage réel
                if ($remaining <= 0) {
                    $plan->status = 'overdue';
                } elseif ($remaining <= $plan->alert_km_before) {
                    $plan->status = 'due';
                }
                
                return $plan;
            })
            ->filter()  // Retirer les null
            ->sortBy(function ($plan) {
                // Trier overdue d'abord, puis due
                return $plan->status === 'overdue' ? 0 : 1;
            });

        return response()->json(['data' => $plans->values()]);
    }

    // POST /api/v1/maintenance/records
    public function record(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id'          => 'required|exists:vehicles,id',
            'maintenance_plan_id' => 'nullable|exists:maintenance_plans,id',
            'type'                => 'required|in:oil_change,tire,brake,full_service,other',
            'performed_at'        => 'required|date',
            'mileage_at_service'  => 'required|numeric|min:0',
            'garage_name'         => 'required|string|max:100',
            'cost_fcfa'           => 'required|numeric|min:0',
            'parts_replaced'      => 'nullable|array',
            'invoice_path'        => 'nullable|string',
        ]);

        $record = MaintenanceRecord::create(array_merge($data, [
            'performed_by' => $request->user()->id,
        ]));

        // Met à jour le kilométrage de la dernière maintenance sur le véhicule
        $vehicle = Vehicle::findOrFail($data['vehicle_id']);
        $vehicle->update([
            'last_maintenance_km' => $data['mileage_at_service'],
            'status'              => 'available',
        ]);

        // Marque le plan comme completed si lié
        if ($data['maintenance_plan_id']) {
            $plan = MaintenancePlan::find($data['maintenance_plan_id']);
            if ($plan) {
                $plan->update(['status' => 'completed']);

                // Crée un nouveau plan pour le prochain intervalle
                if ($plan->interval_km) {
                    MaintenancePlan::create([
                        'vehicle_id'      => $data['vehicle_id'],
                        'type'            => $plan->type,
                        'trigger_km'      => $data['mileage_at_service'] + $plan->interval_km,
                        'interval_km'     => $plan->interval_km,
                        'alert_km_before' => $plan->alert_km_before,
                        'status'          => 'upcoming',
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Intervention enregistrée',
            'record'  => $record,
        ], 201);
    }

    // GET /api/v1/maintenance/records/vehicle/{vehicle}
    public function vehicleHistory(Vehicle $vehicle): JsonResponse
    {
        $records = MaintenanceRecord::where('vehicle_id', $vehicle->id)
            ->with('maintenancePlan')
            ->latest('performed_at')
            ->get();

        return response()->json([
            'vehicle' => $vehicle->only(['id', 'plate_number', 'model', 'current_mileage_km']),
            'records' => $records,
        ]);
    }
}
