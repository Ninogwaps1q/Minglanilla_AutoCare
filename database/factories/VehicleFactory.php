<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => \App\Models\User::factory()->state(['role' => 'customer']),
            'plate_number' => fake()->unique()->bothify('???-####'),
            'make' => fake()->randomElement(['Toyota', 'Honda', 'Mitsubishi']),
            'model' => fake()->word(),
            'year' => fake()->numberBetween(2000, now()->year),
            'current_mileage' => fake()->numberBetween(0, 200000),
        ];
    }
}
