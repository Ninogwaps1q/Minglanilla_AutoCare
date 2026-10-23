<?php

namespace Database\Factories;

use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairJob>
 */
class RepairJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => User::factory()->state(['role' => 'customer']),
            'shop_id' => Shop::factory(),
            'vehicle_id' => Vehicle::factory(),
            'job_number' => 'RJ-'.fake()->unique()->bothify('##########'),
            'description' => 'Brake inspection and repair',
            'status' => 'pending',
        ];
    }

    public function forVehicle(Vehicle $vehicle): static
    {
        return $this->state(fn (): array => [
            'customer_id' => $vehicle->customer_id,
            'vehicle_id' => $vehicle->id,
        ]);
    }
}
