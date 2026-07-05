<?php

namespace App\Services\Planning;

use App\Models\Departure;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DepartureService
{
    public function __construct(
        private readonly GateAssignmentService $gateService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // Création d'un départ manuel
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Crée un départ manuel après validation des contraintes.
     *
     * @throws \Exception si véhicule indisponible ou aucun quai libre
     */
    public function createManual(array $data): Departure
    {
        $departureAt      = Carbon::parse($data['departure_datetime']);
        $estimatedArrival = Carbon::parse($data['estimated_arrival']);

        // 1. Vérifie disponibilité du véhicule si fourni
        if (!empty($data['vehicle_id'])) {
            $check = $this->isVehicleAvailable(
                $data['vehicle_id'],
                $departureAt,
                $estimatedArrival
            );

            if (!$check['available']) {
                throw new \Exception(
                    "Véhicule indisponible: {$check['reason']}"
                );
            }
        }

        return DB::transaction(function () use ($data, $departureAt) {
            $departure = Departure::create([
                'route_id'           => $data['route_id'],
                'vehicle_id'         => $data['vehicle_id'] ?? null,
                'driver_id'          => $data['driver_id'] ?? null,
                'departure_datetime' => $data['departure_datetime'],
                'estimated_arrival'  => $data['estimated_arrival'],
                'seats_available'    => $data['seats_available'] ?? 0,
                'status'             => 'scheduled',
                'notes'              => $data['notes'] ?? null,
                // schedule_template_id restera null (départ manuel)
            ]);

            // 2. Attribution automatique du quai
            $gateId = $this->gateService->assignGate($departure);
            if ($gateId) {
                $departure->update(['boarding_gate_id' => $gateId]);
            }
            // Si aucun quai dispo, on laisse null et le gestionnaire assignera manuellement

            return $departure->fresh(['route', 'vehicle']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Affectation véhicule / chauffeur
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Affecte un véhicule à un départ existant.
     *
     * @throws \Exception si véhicule indisponible
     */
    public function assignVehicle(Departure $departure, int $vehicleId): Departure
    {
        $check = $this->isVehicleAvailable(
            $vehicleId,
            $departure->departure_datetime,
            $departure->estimated_arrival
        );

        if (!$check['available']) {
            throw new \Exception("Véhicule indisponible: {$check['reason']}");
        }

        $vehicle = Vehicle::findOrFail($vehicleId);

        $departure->update([
            'vehicle_id'      => $vehicleId,
            'seats_available' => $vehicle->capacity,
        ]);

        return $departure->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Changement de statut
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Met à jour le statut d'un départ avec les validations métier associées.
     *
     * @throws \Exception si transition non autorisée ou données manquantes
     */
    public function updateStatus(Departure $departure, string $newStatus, array $data = []): Departure
    {
        $this->validateStatusTransition($departure->status, $newStatus);

        $updates = ['status' => $newStatus];

        match ($newStatus) {
            'boarding'  => null, // Pas de données supplémentaires
            'departed'  => $updates['actual_departure'] = $data['actual_departure'] ?? now(),
            'arrived'   => $updates['actual_arrival']   = $data['actual_arrival'] ?? now(),
            'cancelled' => $updates['cancellation_reason'] = $data['cancellation_reason']
                ?? throw new \Exception('Le motif d\'annulation est obligatoire'),
            default     => null,
        };

        $departure->update($updates);

        return $departure->fresh();
    }

    /**
     * Annule un départ.
     */
    public function cancel(Departure $departure, string $reason): Departure
    {
        if ($departure->isCancelled()) {
            throw new \Exception('Ce départ est déjà annulé');
        }

        if (in_array($departure->status, ['departed', 'arrived'])) {
            throw new \Exception('Impossible d\'annuler un départ déjà effectué');
        }

        $departure->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $reason,
        ]);

        return $departure->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Disponibilité véhicule
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Vérifie si un véhicule est disponible sur une plage horaire.
     *
     * @return array{available: bool, reason?: string}
     */
    public function isVehicleAvailable(
        int $vehicleId,
        Carbon $departureAt,
        Carbon $estimatedArrival,
        ?int $excludeDepartureId = null
    ): array {
        $vehicle = Vehicle::findOrFail($vehicleId);

        // Vérifie le statut du véhicule
        if (!$vehicle->isAvailable() && $vehicle->status !== 'on_trip') {
            return [
                'available' => false,
                'reason'    => "Véhicule en statut: {$vehicle->status}",
            ];
        }

        // Vérifie les conflits de planning
        $conflict = Departure::where('vehicle_id', $vehicleId)
            ->where('status', '!=', 'cancelled')
            ->when($excludeDepartureId, fn($q) => $q->where('id', '!=', $excludeDepartureId))
            ->where(function ($query) use ($departureAt, $estimatedArrival) {
                // Chevauchement: A commence avant que B finisse ET A finit après que B commence
                $query->where('departure_datetime', '<', $estimatedArrival)
                      ->where('estimated_arrival', '>', $departureAt);
            })
            ->first();

        if ($conflict) {
            return [
                'available' => false,
                'reason'    => sprintf(
                    'Conflit avec départ #%d (%s → %s)',
                    $conflict->id,
                    $conflict->departure_datetime->format('d/m H:i'),
                    $conflict->estimated_arrival->format('d/m H:i')
                ),
            ];
        }

        return ['available' => true];
    }

    /**
     * Retourne les véhicules disponibles à une datetime donnée.
     */
    public function getAvailableVehicles(Carbon $at, Carbon $estimatedReturn): \Illuminate\Support\Collection
    {
        // IDs des véhicules occupés sur cette plage
        $busyVehicleIds = Departure::where('status', '!=', 'cancelled')
            ->where('departure_datetime', '<', $estimatedReturn)
            ->where('estimated_arrival', '>', $at)
            ->whereNotNull('vehicle_id')
            ->pluck('vehicle_id');

        return Vehicle::available()
            ->whereNotIn('id', $busyVehicleIds)
            ->orderBy('plate_number')
            ->get();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Privé
    // ─────────────────────────────────────────────────────────────────────

    private const VALID_TRANSITIONS = [
        'scheduled' => ['boarding', 'cancelled'],
        'boarding'  => ['departed', 'cancelled'],
        'departed'  => ['arrived'],
        'arrived'   => [],
        'cancelled' => [],
    ];

    private function validateStatusTransition(string $from, string $to): void
    {
        $allowed = self::VALID_TRANSITIONS[$from] ?? [];

        if (!in_array($to, $allowed, true)) {
            throw new \Exception(
                "Transition de statut invalide: {$from} → {$to}. "
                . "Transitions autorisées: " . implode(', ', $allowed)
            );
        }
    }
}
