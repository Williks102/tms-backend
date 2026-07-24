<?php

namespace Database\Factories;

use App\Models\Route;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Departure>
 */
class DepartureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'route_id'           => Route::factory(),
            'vehicle_id'         => Vehicle::factory(),
            'departure_datetime' => now()->addHours(2),
            'estimated_arrival'  => now()->addHours(7),
            'seats_available'    => 50,
            'status'             => 'boarding',
        ];
    }
}
