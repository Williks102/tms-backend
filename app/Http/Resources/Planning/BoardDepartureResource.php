<?php

namespace App\Http\Resources\Planning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Version publique de DepartureResource pour l'écran de gare (endpoint non
 * authentifié) — expose uniquement les champs sans caractère personnel
 * (pas de chauffeur, pas de notes internes, pas de motif d'annulation).
 */
class BoardDepartureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'route' => $this->whenLoaded('route', fn () => [
                'code'             => $this->route->code,
                'name'             => $this->route->name,
                'origin_city'      => $this->route->origin_city,
                'destination_city' => $this->route->destination_city,
            ]),

            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? [
                'plate_number' => $this->vehicle->plate_number,
            ] : null),

            'departure_datetime' => $this->departure_datetime?->toIso8601String(),
            'estimated_arrival'  => $this->estimated_arrival?->toIso8601String(),
            'actual_departure'   => $this->actual_departure?->toIso8601String(),
            'actual_arrival'     => $this->actual_arrival?->toIso8601String(),

            'boarding_gate' => $this->boarding_gate,
            'status'        => $this->status,
            'delay_minutes' => $this->delayMinutes(),
        ];
    }
}
