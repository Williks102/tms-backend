<?php

namespace App\Services\Planning;

use App\Models\Departure;
use App\Models\BoardingGate;
use Carbon\Carbon;

class GateAssignmentService
{
    /**
     * Fenêtre de blocage autour d'un départ (en minutes).
     * Un quai est considéré occupé 30 min avant et après un départ.
     */
    private const BUFFER_MINUTES = 30;

    /**
     * Trouve et retourne l'id du premier quai disponible pour un départ.
     * Si la ligne a une gare de départ définie (route.origin_station_id),
     * seuls les quais de cette gare sont considérés — sinon (ligne sans gare
     * assignée), la recherche reste globale, comme avant.
     * Retourne null si aucun quai n'est libre.
     */
    public function assignGate(Departure $departure): ?int
    {
        $departureAt = Carbon::parse($departure->departure_datetime);
        $windowStart = $departureAt->copy()->subMinutes(self::BUFFER_MINUTES);
        $windowEnd   = $departureAt->copy()->addMinutes(self::BUFFER_MINUTES);

        // Récupère tous les quais occupés sur cette fenêtre horaire
        $busyGateIds = Departure::where('id', '!=', $departure->id)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($windowStart, $windowEnd) {
                // Chevauche la fenêtre si: departure < fin ET estimated_arrival > début
                $query->where('departure_datetime', '<', $windowEnd)
                      ->where('estimated_arrival', '>', $windowStart);
            })
            ->whereNotNull('boarding_gate_id')
            ->pluck('boarding_gate_id')
            ->toArray();

        // Cherche le premier quai actif non occupé, dans la gare de la ligne si connue
        $query = BoardingGate::active()->whereNotIn('id', $busyGateIds);

        $stationId = $departure->route?->origin_station_id;
        if ($stationId) {
            $query->where('station_id', $stationId);
        }

        $availableGate = $query->orderBy('gate_code')->first(); // Attribution dans l'ordre (Q1, Q2...)

        return $availableGate?->id;
    }

    /**
     * Retourne tous les quais disponibles à un moment donné, optionnellement
     * filtrés sur une gare précise.
     * Utilisé par l'endpoint GET /api/v1/planning/gates/available
     */
    public function getAvailableGates(Carbon $at, ?int $stationId = null): \Illuminate\Support\Collection
    {
        $windowStart = $at->copy()->subMinutes(self::BUFFER_MINUTES);
        $windowEnd   = $at->copy()->addMinutes(self::BUFFER_MINUTES);

        $busyGateIds = Departure::where('status', '!=', 'cancelled')
            ->where('departure_datetime', '<', $windowEnd)
            ->where('estimated_arrival', '>', $windowStart)
            ->whereNotNull('boarding_gate_id')
            ->pluck('boarding_gate_id')
            ->toArray();

        $query = BoardingGate::active()->whereNotIn('id', $busyGateIds);

        if ($stationId) {
            $query->where('station_id', $stationId);
        }

        return $query->orderBy('gate_code')->get();
    }
}
