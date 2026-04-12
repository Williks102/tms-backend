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
     * Trouve et retourne le code du premier quai disponible pour un départ.
     * Retourne null si aucun quai n'est libre.
     */
    public function assignGate(Departure $departure): ?string
    {
        $departureAt = Carbon::parse($departure->departure_datetime);
        $windowStart = $departureAt->copy()->subMinutes(self::BUFFER_MINUTES);
        $windowEnd   = $departureAt->copy()->addMinutes(self::BUFFER_MINUTES);

        // Récupère tous les quais occupés sur cette fenêtre horaire
        $busyGates = Departure::where('id', '!=', $departure->id)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($windowStart, $windowEnd) {
                // Chevauche la fenêtre si: departure < fin ET estimated_arrival > début
                $query->where('departure_datetime', '<', $windowEnd)
                      ->where('estimated_arrival', '>', $windowStart);
            })
            ->whereNotNull('boarding_gate')
            ->pluck('boarding_gate')
            ->toArray();

        // Cherche le premier quai actif non occupé
        $availableGate = BoardingGate::active()
            ->whereNotIn('gate_code', $busyGates)
            ->orderBy('gate_code') // Attribution dans l'ordre (Q1, Q2...)
            ->first();

        return $availableGate?->gate_code;
    }

    /**
     * Retourne tous les quais disponibles à un moment donné.
     * Utilisé par l'endpoint GET /api/v1/planning/gates/available
     */
    public function getAvailableGates(Carbon $at): \Illuminate\Support\Collection
    {
        $windowStart = $at->copy()->subMinutes(self::BUFFER_MINUTES);
        $windowEnd   = $at->copy()->addMinutes(self::BUFFER_MINUTES);

        $busyGates = Departure::where('status', '!=', 'cancelled')
            ->where('departure_datetime', '<', $windowEnd)
            ->where('estimated_arrival', '>', $windowStart)
            ->whereNotNull('boarding_gate')
            ->pluck('boarding_gate')
            ->toArray();

        return BoardingGate::active()
            ->whereNotIn('gate_code', $busyGates)
            ->orderBy('gate_code')
            ->get();
    }
}
