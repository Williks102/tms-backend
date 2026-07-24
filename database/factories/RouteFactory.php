<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Route>
 */
class RouteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code'                    => strtoupper(fake()->unique()->bothify('???-???')),
            'name'                    => 'Abidjan → Bouaké',
            'origin_city'             => 'Abidjan',
            'destination_city'        => 'Bouaké',
            'distance_km'             => 350,
            'estimated_duration_min'  => 300,
            'is_dynamic'              => false,
            'base_fare'               => 5000,
            'is_active'               => true,
        ];
    }
}
