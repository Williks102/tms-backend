<?php

namespace Database\Factories;

use App\Models\FreightClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\FreightShipment>
 */
class FreightShipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference'         => 'FRT-' . now()->year . '-' . Str::padLeft((string) fake()->unique()->randomNumber(6), 6, '0'),
            'freight_client_id' => FreightClient::factory(),
            'origin_city'       => 'Abidjan',
            'destination_city'  => 'Bouaké',
            'distance_km'       => 350,
            'weight_tons'       => 5,
            'price_fcfa'        => 5 * 350 * 150,
            'status'            => 'pending',
            'payment_status'    => 'pending',
            'created_by'        => User::factory(),
        ];
    }
}
