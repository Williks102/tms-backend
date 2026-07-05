<?php

namespace App\Http\Resources\Planning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'code'                   => $this->code,
            'name'                   => $this->name,
            'origin_city'            => $this->origin_city,
            'origin_station'         => $this->whenLoaded('originStation', fn() => $this->originStation ? [
                'id'   => $this->originStation->id,
                'name' => $this->originStation->name,
                'city' => $this->originStation->city,
            ] : null),
            'destination_city'       => $this->destination_city,
            'distance_km'            => (float) $this->distance_km,
            'estimated_duration_min' => $this->estimated_duration_min,
            'estimated_duration_h'   => round($this->estimated_duration_min / 60, 1),
            'base_fare'              => (float) $this->base_fare,
            'is_dynamic'             => $this->is_dynamic,
            'is_active'              => $this->is_active,

            // Arrêts intermédiaires — inclus uniquement si chargés
            'stops' => $this->whenLoaded('stops', fn() =>
                $this->stops->map(fn($s) => [
                    'id'                      => $s->id,
                    'city_name'               => $s->city_name,
                    'stop_order'              => $s->stop_order,
                    'distance_from_origin_km' => (float) $s->distance_from_origin_km,
                    'fare_from_origin'        => (float) $s->fare_from_origin,
                ])
            ),

            // Compteurs utiles pour le dashboard
            'upcoming_departures_count' => $this->whenCounted('departures'),

            'created_at' => $this->created_at?->toDateString(),
        ];
    }
}
