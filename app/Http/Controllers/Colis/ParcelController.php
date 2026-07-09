<?php

namespace App\Http\Controllers\Colis;

use App\Http\Controllers\Controller;
use App\Http\Resources\Colis\ParcelTrackResource;
use App\Models\Parcel;
use App\Services\Colis\ParcelPricingService;
use App\Services\Colis\ParcelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ParcelController extends Controller
{
    public function __construct(
        private readonly ParcelService $service,
        private readonly ParcelPricingService $pricing,
    ) {}

    // GET /api/v1/colis
    public function index(Request $request): JsonResponse
    {
        $query = Parcel::with(['departure.route', 'destinationStop', 'registeredBy', 'releasedBy']);

        if ($request->filled('status'))       $query->where('status', $request->status);
        if ($request->filled('departure_id')) $query->where('departure_id', $request->integer('departure_id'));
        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(fn ($q) => $q
                ->where('tracking_number', 'like', "%{$term}%")
                ->orWhere('sender_name', 'like', "%{$term}%")
                ->orWhere('recipient_name', 'like', "%{$term}%"));
        }

        $parcels = $query->latest('registered_at')->paginate($request->integer('per_page', 20));

        return response()->json($parcels);
    }

    // POST /api/v1/colis/quote — prévisualisation tarif, pas de duplication
    // de la formule côté frontend (voir ParcelPricingService).
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'weight_kg'           => 'required|numeric|min:0.1',
            'declared_value_fcfa' => 'required|numeric|min:0',
        ]);

        return response()->json(
            $this->pricing->calculate((float) $data['weight_kg'], (float) $data['declared_value_fcfa'])
        );
    }

    // POST /api/v1/colis
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'departure_id'           => 'required|exists:departures,id',
            'destination_stop_id'    => 'nullable|integer|exists:route_stops,id',
            'sender_name'            => 'required|string|max:150',
            'sender_phone'           => 'required|string|max:30',
            'recipient_name'         => 'required|string|max:150',
            'recipient_phone'        => 'required|string|max:30',
            'category'               => 'nullable|in:standard,fragile,perissable,liquide',
            'description'            => 'nullable|string|max:500',
            'weight_kg'              => 'required|numeric|min:0.1',
            'declared_value_fcfa'    => 'required|numeric|min:0',
            'payment_responsibility' => 'nullable|in:expediteur,destinataire',
        ]);

        try {
            $parcel = $this->service->register($data, $request->user()->id);

            return response()->json([
                'message' => 'Colis enregistré avec succès',
                'parcel'  => $parcel,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // GET /api/v1/colis/{parcel}
    public function show(Parcel $parcel): JsonResponse
    {
        return response()->json([
            'parcel' => $parcel->load(['departure.route', 'destinationStop', 'registeredBy', 'releasedBy', 'media']),
        ]);
    }

    // POST /api/v1/colis/scan-arrival
    public function scanArrival(Request $request): JsonResponse
    {
        $data = $request->validate(['tracking_number' => 'required|string']);

        try {
            $parcel = $this->service->scanArrival($data['tracking_number'], $request->user()->id);

            return response()->json(['message' => 'Colis marqué arrivé', 'parcel' => $parcel]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /api/v1/colis/{parcel}/release
    public function release(Request $request, Parcel $parcel): JsonResponse
    {
        $data = $request->validate(['pickup_code' => 'required|string|size:6']);

        try {
            $parcel = $this->service->release($parcel, $data['pickup_code'], $request->user()->id);

            return response()->json(['message' => 'Colis remis au destinataire', 'parcel' => $parcel]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // PATCH /api/v1/colis/{parcel}/cancel
    public function cancel(Request $request, Parcel $parcel): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5']);

        try {
            $parcel = $this->service->cancel($parcel, $data['reason']);

            return response()->json(['message' => 'Colis annulé', 'parcel' => $parcel]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // PATCH /api/v1/colis/{parcel}/exception — manager uniquement (voir routes)
    public function markException(Request $request, Parcel $parcel): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:retourne,perdu',
            'reason' => 'required|string|min:5',
        ]);

        try {
            $parcel = $this->service->markException($parcel, $data['status'], $data['reason'], $request->user()->id);

            return response()->json(['message' => 'Statut du colis mis à jour', 'parcel' => $parcel]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /api/v1/colis/{parcel}/media — même pattern que IncidentController::uploadMedia
    public function uploadMedia(Request $request, Parcel $parcel): JsonResponse
    {
        $data = $request->validate([
            'file_type'   => 'required|in:photo,document',
            'file'        => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'description' => 'nullable|string|max:200',
        ]);

        $path = $request->file('file')->store('colis-media', 'local');

        $media = $parcel->media()->create([
            'file_path'   => $path,
            'file_type'   => $data['file_type'],
            'description' => $data['description'] ?? null,
            'uploaded_by' => $request->user()->id,
            'uploaded_at' => now(),
        ]);

        return response()->json(['message' => 'Photo ajoutée', 'media' => $media], 201);
    }

    // GET /api/v1/colis/{parcel}/media/{media}/download
    public function downloadMedia(Parcel $parcel, int $media)
    {
        $record = $parcel->media()->findOrFail($media);

        abort_unless(Storage::disk('local')->exists($record->file_path), 404);

        return Storage::disk('local')->download($record->file_path);
    }

    // GET /api/v1/colis/track/{tracking_number} — PUBLIC, hors auth:sanctum
    public function publicTrack(string $trackingNumber): JsonResponse
    {
        $parcel = Parcel::with('departure.route')->where('tracking_number', $trackingNumber)->firstOrFail();

        return response()->json(['parcel' => new ParcelTrackResource($parcel)]);
    }
}
