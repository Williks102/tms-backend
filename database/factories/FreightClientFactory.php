<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\FreightClient>
 */
class FreightClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'contact_name' => fake()->name(),
            'phone'        => '07' . fake()->numerify('########'),
            'email'        => fake()->unique()->companyEmail(),
            'address'      => fake()->address(),
        ];
    }
}
