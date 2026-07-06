<?php

namespace App\Services\Planning;

use App\Models\Departure;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Services\Drivers\RestComplianceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DepartureService
{
    public function __construct(
        private readonly GateAssignmentService $gateService,
        private readonly RestComplianceService $restService,
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

    /**
     * Affecte un chauffeur à un départ existant.
     *
     * @throws \Exception si chauffeur non conforme (statut, documents, repos)
     */
    public function assignDriver(Departure $departure, int $driverId): Departure
    {
        $driver = Driver::findOrFail($driverId);

        $check = $this->restService->canDrive($driver, Carbon::parse($departure->departure_datetime));

        if (!$check['compliant']) {
            throw new \Exception("Chauffeur indisponible: {$check['message']}");
        }

        $departure->update(['driver_id' => $driverId]);

        return $departure->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Modification d'un départ existant
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Champs de planification — horaires prévus, véhicule, chauffeur, quai,
     * places. Modifiables uniquement tant que le départ n'a pas encore eu
     * lieu ("scheduled"), car ils impactent la disponibilité des ressources.
     */
    private const SCHEDULING_FIELDS = ['departure_datetime', 'estimated_arrival', 'vehicle_id', 'driver_id', 'boarding_gate_id', 'seats_available'];

    /**
     * Modifie un départ existant.
     * - `notes`, `actual_departure`, `actual_arrival` : toujours modifiables
     *   (correction a posteriori d'une heure réelle mal saisie ou oubliée).
     * - horaires prévus / véhicule / chauffeur / quai / places : uniquement
     *   tant que le départ est "scheduled" (impacte la disponibilité des
     *   ressources, n'a plus de sens une fois le départ en cours ou terminé).
     * La ligne (route_id) n'est volontairement pas modifiable — un trajet
     * erroné doit être annulé et recréé (impacte tarifs/arrêts/billets déjà émis).
     *
     * @throws \Exception si un champ de planification est modifié alors que
     *                     le départ n'est plus "scheduled", ou si le
     *                     véhicule/chauffeur proposé n'est pas disponible
     */
    public function update(Departure $departure, array $data): Departure
    {
        $wantsSchedulingChange = count(array_intersect(self::SCHEDULING_FIELDS, array_keys($data))) > 0;

        if ($wantsSchedulingChange && $departure->status !== 'scheduled') {
            throw new \Exception("Modification impossible : ce départ est déjà {$departure->status}.");
        }

        return DB::transaction(function () use ($departure, $data) {
            foreach (['actual_departure', 'actual_arrival'] as $field) {
                if (array_key_exists($field, $data)) {
                    $departure->update([$field => $data[$field] ? Carbon::parse($data[$field]) : null]);
                }
            }

            if (array_key_exists('notes', $data)) {
                $departure->update(['notes' => $data['notes']]);
            }

            if (array_key_exists('departure_datetime', $data) || array_key_exists('estimated_arrival', $data)) {
                $departureAt      = Carbon::parse($data['departure_datetime'] ?? $departure->departure_datetime);
                $estimatedArrival = Carbon::parse($data['estimated_arrival'] ?? $departure->estimated_arrival);

                if (!$estimatedArrival->gt($departureAt)) {
                    throw new \Exception('L\'heure d\'arrivée doit être après l\'heure de départ');
                }

                if ($departure->vehicle_id) {
                    $check = $this->isVehicleAvailable($departure->vehicle_id, $departureAt, $estimatedArrival, $departure->id);
                    if (!$check['available']) {
                        throw new \Exception("Véhicule indisponible sur ce nouveau créneau: {$check['reason']}");
                    }
                }

                $departure->update([
                    'departure_datetime' => $departureAt,
                    'estimated_arrival'  => $estimatedArrival,
                ]);
            }

            foreach (['seats_available', 'boarding_gate_id'] as $field) {
                if (array_key_exists($field, $data)) {
                    $departure->update([$field => $data[$field]]);
                }
            }

            if (array_key_exists('vehicle_id', $data)) {
                if ($data['vehicle_id']) {
                    $this->assignVehicle($departure->fresh(), $data['vehicle_id']);
                } else {
                    $departure->update(['vehicle_id' => null]);
                }
            }

            if (array_key_exists('driver_id', $data)) {
                if ($data['driver_id']) {
                    $this->assignDriver($departure->fresh(), $data['driver_id']);
                } else {
                    $departure->update(['driver_id' => null]);
                }
            }

            return $departure->fresh(['route', 'vehicle', 'driver']);
        });
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
    public function getAvailableVehicles(Carbon $at, Carbon $estimatedReturn, ?int $excludeDepartureId = null): \Illuminate\Support\Collection
    {
        // IDs des véhicules occupés sur cette plage
        $busyVehicleIds = Departure::where('status', '!=', 'cancelled')
            ->when($excludeDepartureId, fn ($q) => $q->where('id', '!=', $excludeDepartureId))
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
