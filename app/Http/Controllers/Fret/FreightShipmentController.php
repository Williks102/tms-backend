<?php

namespace App\Http\Controllers\Fret;

use App\Http\Controllers\Controller;
use App\Models\FreightShipment;
use App\Services\Fret\FreightPricingService;
use App\Services\Fret\FreightShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FreightShipmentController extends Controller
{
    public function __construct(
        private readonly FreightShipmentService $service,
        private readonly FreightPricingService $pricing,
    ) {}

    // GET /api/v1/fret/shipments
    public function index(Request $request): JsonResponse
    {
        $query = FreightShipment::with(['client', 'vehicle', 'driver']);

        if ($request->filled('status'))            $query->where('status', $request->string('status'));
        if ($request->filled('freight_client_id'))  $query->where('freight_client_id', $request->integer('freight_client_id'));
        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(fn ($q) => $q
                ->where('reference', 'like', "%{$term}%")
                ->orWhere('origin_city', 'like', "%{$term}%")
                ->orWhere('destination_city', 'like', "%{$term}%"));
        }

        return response()->json($query->latest()->paginate($request->integer('per_page', 20)));
    }

    // POST /api/v1/fret/quote — prévisualisation tarif, formule uniquement côté serveur
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'weight_tons' => 'required|numeric|min:0.1',
            'distance_km' => 'required|numeric|min:1',
        ]);

        return response()->json(
            $this->pricing->calculate((float) $data['weight_tons'], (float) $data['distance_km'])
        );
    }

    // POST /api/v1/fret/shipments
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'freight_client_id' => 'required|exists:freight_clients,id',
            'origin_city'       => 'required|string|max:100',
            'destination_city'  => 'required|string|max:100',
            'distance_km'       => 'required|numeric|min:1',
            'weight_tons'       => 'required|numeric|min:0.1',
            'cargo_description' => 'nullable|string|max:500',
            'scheduled_at'      => 'nullable|date',
        ]);

        try {
            $shipment = $this->service->create($data, $request->user()->id);

            return response()->json(['message' => "Expédition {$shipment->reference} créée", 'shipment' => $shipment], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // GET /api/v1/fret/shipments/{shipment}
    public function show(FreightShipment $shipment): JsonResponse
    {
        return response()->json([
            'shipment' => $shipment->load(['client', 'vehicle', 'driver', 'createdBy']),
        ]);
    }

    // PATCH /api/v1/fret/shipments/{shipment}/assign
    public function assign(Request $request, FreightShipment $shipment): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id'  => 'nullable|exists:drivers,id',
        ]);

        try {
            $shipment = $this->service->assign($shipment, (int) $data['vehicle_id'], $data['driver_id'] ?? null);

            return response()->json(['message' => 'Camion affecté', 'shipment' => $shipment]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // PATCH /api/v1/fret/shipments/{shipment}/status
    public function updateStatus(Request $request, FreightShipment $shipment): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:in_transit,delivered']);

        try {
            $shipment = $this->service->updateStatus($shipment, $data['status'], $request->user()->id);

            return response()->json(['message' => 'Statut mis à jour', 'shipment' => $shipment]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // PATCH /api/v1/fret/shipments/{shipment}/cancel
    public function cancel(Request $request, FreightShipment $shipment): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5']);

        try {
            $shipment = $this->service->cancel($shipment, $data['reason'], $request->user()->id);

            return response()->json(['message' => 'Expédition annulée', 'shipment' => $shipment]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /api/v1/fret/shipments/{shipment}/pay
    public function markPaid(Request $request, FreightShipment $shipment): JsonResponse
    {
        $data = $request->validate(['deposit_account' => 'required|in:571,521']);

        try {
            $shipment = $this->service->markPaid($shipment, $data['deposit_account'], $request->user()->id);

            return response()->json(['message' => 'Expédition encaissée', 'shipment' => $shipment]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
