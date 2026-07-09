<?php

namespace App\Http\Controllers\Vehicles;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Audit\ActivityLogger;
use App\Services\Export\CsvExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly CsvExportService $csv,
    ) {}

    // GET /api/v1/vehicles/export
    public function export(): StreamedResponse
    {
        $rows = Vehicle::all()->map(fn (Vehicle $v) => [
            $v->plate_number, $v->model, $v->capacity, $v->status,
            $v->current_mileage_km, $v->cargo_capacity_kg,
            $v->insurance_expires_at?->toDateString(), $v->controle_technique_expires_at?->toDateString(),
        ]);

        return $this->csv->stream(
            ['Plaque', 'Modèle', 'Capacité', 'Statut', 'Kilométrage', 'Capacité fret (kg)', 'Assurance expire', 'Contrôle technique expire'],
            $rows,
            'vehicules-' . now()->format('Y-m-d') . '.csv',
        );
    }
    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/vehicles
    // ─────────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = Vehicle::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(fn ($q) => $q
                ->where('plate_number', 'like', "%{$term}%")
                ->orWhere('model', 'like', "%{$term}%"));
        }

        $vehicles = $query->orderBy('plate_number')->paginate($request->integer('per_page', 30));

        return response()->json($vehicles);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/vehicles
    // ─────────────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plate_number'               => 'required|string|max:20|unique:vehicles,plate_number',
            'model'                      => 'required|string|max:100',
            'capacity'                   => 'required|integer|min:1',
            'fuel_consumption_per_100km' => 'required|numeric|min:0',
            'current_mileage_km'         => 'nullable|numeric|min:0',
            'maintenance_interval_km'    => 'nullable|numeric|min:0',
            'status'                     => 'nullable|in:available,on_trip,boarding,maintenance,inactive',
            'notes'                      => 'nullable|string',
            'cargo_capacity_kg'          => 'nullable|numeric|min:0',
        ]);

        $vehicle = Vehicle::create($data);

        return response()->json([
            'message' => 'Véhicule créé avec succès',
            'vehicle' => $vehicle,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/vehicles/{vehicle}
    // ─────────────────────────────────────────────────────────────────────
    public function show(Vehicle $vehicle): JsonResponse
    {
        $vehicle->load([
            'maintenancePlans'    => fn ($q) => $q->orderByRaw('trigger_km IS NULL, trigger_km ASC'),
            'maintenanceRecords'  => fn ($q) => $q->latest('performed_at')->limit(10),
            'incidents'           => fn ($q) => $q->latest('occurred_at')->limit(10),
            'fuelConsumptionLogs' => fn ($q) => $q->latest('recorded_at')->limit(10),
            'documents',
        ]);

        return response()->json([
            'vehicle'               => $vehicle,
            'needs_maintenance'     => $vehicle->needsMaintenance(),
            'km_before_maintenance' => $vehicle->kmBeforeNextMaintenance(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PUT /api/v1/vehicles/{vehicle}
    // ─────────────────────────────────────────────────────────────────────
    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->validate([
            'plate_number'               => "string|max:20|unique:vehicles,plate_number,{$vehicle->id}",
            'model'                      => 'string|max:100',
            'capacity'                   => 'integer|min:1',
            'fuel_consumption_per_100km' => 'numeric|min:0',
            'current_mileage_km'         => 'numeric|min:0',
            'maintenance_interval_km'    => 'numeric|min:0',
            'status'                     => 'in:available,on_trip,boarding,maintenance,inactive',
            'notes'                      => 'nullable|string',
            'cargo_capacity_kg'          => 'nullable|numeric|min:0',
        ]);

        $vehicle->update($data);

        return response()->json([
            'message' => 'Véhicule mis à jour',
            'vehicle' => $vehicle->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // DELETE /api/v1/vehicles/{vehicle}  (soft delete)
    // ─────────────────────────────────────────────────────────────────────
    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $activeDepartures = $vehicle->departures()
            ->whereNotIn('status', ['cancelled', 'arrived'])
            ->count();

        if ($activeDepartures > 0) {
            return response()->json([
                'message' => "Impossible de supprimer : {$activeDepartures} départ(s) actif(s) ou à venir sur ce véhicule. Passez-le en maintenance/inactif plutôt.",
            ], 422);
        }

        $vehicle->update(['status' => 'inactive']);
        $vehicle->delete();

        return response()->json(['message' => 'Véhicule désactivé avec succès']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/vehicles/{vehicle}/documents — copie du pattern
    // DriverController::uploadDocument()
    // ─────────────────────────────────────────────────────────────────────
    public function uploadDocument(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->validate([
            'type'       => 'required|in:assurance,controle_technique,vignette,other',
            'file'       => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'expires_at' => 'nullable|date',
        ]);

        // Stocké sur le disque privé ("local") — jamais servi directement par
        // le webserver ; le nom de fichier est généré par Storage, jamais fourni par le client.
        $path = $request->file('file')->store('vehicle-documents', 'local');

        $document = VehicleDocument::create([
            'vehicle_id'  => $vehicle->id,
            'type'        => $data['type'],
            'file_path'   => $path,
            'expires_at'  => $data['expires_at'] ?? null,
            'uploaded_at' => now(),
        ]);

        // Un document "assurance"/"controle_technique" renouvelé fait aussi
        // office de mise en conformité — sans ça, isVehicleAvailable()
        // continuerait de bloquer le véhicule sur l'ancienne date (voir
        // Vehicle::hasExpiredDocuments(), même piège déjà rencontré côté chauffeurs).
        if ($data['type'] === 'assurance' && !empty($data['expires_at'])) {
            $vehicle->update(['insurance_expires_at' => $data['expires_at']]);
        } elseif ($data['type'] === 'controle_technique' && !empty($data['expires_at'])) {
            $vehicle->update(['controle_technique_expires_at' => $data['expires_at']]);
        }

        $this->activityLogger->log(
            'vehicle_document.uploaded',
            $vehicle,
            "Document {$data['type']} ajouté pour {$vehicle->plate_number}",
            userId: $request->user()->id,
        );

        return response()->json([
            'message'  => 'Document enregistré',
            'document' => $document,
        ], 201);
    }

    // GET /api/v1/vehicles/{vehicle}/documents/{document}/download
    public function downloadDocument(Vehicle $vehicle, VehicleDocument $document): StreamedResponse
    {
        abort_unless($document->vehicle_id === $vehicle->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path);
    }
}
