<?php

namespace App\Http\Resources\Colis;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Version publique d'un colis pour le suivi client (endpoint non authentifié,
 * /colis/track/{tracking_number}) — n'expose JAMAIS pickup_code (secret de
 * retrait) ni les téléphones complets. Même philosophie que BoardDepartureResource.
 */
class ParcelTrackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tracking_number' => $this->tracking_number,
            'status'          => $this->status,
            'category'        => $this->category,

            'route' => $this->whenLoaded('departure', fn () => $this->departure?->route ? [
                'origin_city'      => $this->departure->route->origin_city,
                'destination_city' => $this->departure->route->destination_city,
            ] : null),

            'sender_name'    => $this->sender_name,
            'recipient_name' => $this->recipient_name,
            'recipient_phone_masked' => $this->maskPhone($this->recipient_phone),

            'registered_at' => $this->registered_at?->toIso8601String(),
            'arrived_at'    => $this->arrived_at?->toIso8601String(),
            'released_at'   => $this->released_at?->toIso8601String(),
            'returned_at'   => $this->returned_at?->toIso8601String(),
        ];
    }

    private function maskPhone(?string $phone): ?string
    {
        if (!$phone || strlen($phone) < 4) {
            return $phone;
        }

        return str_repeat('*', strlen($phone) - 4) . substr($phone, -4);
    }
}
