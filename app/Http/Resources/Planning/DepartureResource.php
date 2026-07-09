<?php

namespace App\Http\Resources\Planning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'is_manual' => $this->isManual(),

            // ── Ligne ─────────────────────────────────────────────────
            'route' => $this->whenLoaded('route', fn() => [
                'id'                    => $this->route->id,
                'code'                  => $this->route->code,
                'name'                  => $this->route->name,
                'origin_city'           => $this->route->origin_city,
                'destination_city'      => $this->route->destination_city,
                'distance_km'           => $this->route->distance_km,
                'estimated_duration_min'=> $this->route->estimated_duration_min,
                'base_fare'             => $this->route->base_fare,
            ]),

            // ── Véhicule ──────────────────────────────────────────────
            'vehicle' => $this->whenLoaded('vehicle', fn() => $this->vehicle ? [
                'id'           => $this->vehicle->id,
                'plate_number' => $this->vehicle->plate_number,
                'model'        => $this->vehicle->model,
                'capacity'     => $this->vehicle->capacity,
                'status'       => $this->vehicle->status,
            ] : null),

            // ── Chauffeur ─────────────────────────────────────────────
            'driver' => $this->whenLoaded('driver', fn() => $this->driver ? [
                'id'         => $this->driver->id,
                'full_name'  => "{$this->driver->first_name} {$this->driver->last_name}",
                'phone'      => $this->driver->phone,
            ] : null),

            // ── Horaires ──────────────────────────────────────────────
            'departure_datetime'  => $this->departure_datetime?->toIso8601String(),
            'estimated_arrival'   => $this->estimated_arrival?->toIso8601String(),
            'actual_departure'    => $this->actual_departure?->toIso8601String(),
            'actual_arrival'      => $this->actual_arrival?->toIso8601String(),
            'boarding_time'       => $this->boarding_time?->toIso8601String(),
            'boarding_due'        => $this->boarding_due,

            // ── Infos opérationnelles ─────────────────────────────────
            'boarding_gate'       => $this->boarding_gate,
            'seats_available'     => $this->seats_available,
            'cargo_capacity_kg'   => $this->cargo_capacity_kg,
            'cargo_used_kg'       => $this->cargo_used_kg,
            'status'              => $this->status,

            // ── Métriques calculées (disponibles après arrivée) ───────
            'delay_minutes'          => $this->delayMinutes(),
            'actual_duration_minutes'=> $this->actualDurationMinutes(),

            // ── Infos supplémentaires ─────────────────────────────────
            'cancellation_reason' => $this->when(
                $this->status === 'cancelled',
                $this->cancellation_reason
            ),
            'notes'               => $this->notes,
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
