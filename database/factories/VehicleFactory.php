<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plate_number'                => strtoupper(fake()->unique()->bothify('??-####-??')),
            'model'                       => 'Mercedes Tourismo',
            'capacity'                   => 50,
            'fuel_consumption_per_100km'  => 28,
            'current_mileage_km'         => 10000,
            'last_maintenance_km'        => 5000,
            'maintenance_interval_km'    => 10000,
            'status'                      => 'available',
        ];
    }
}
