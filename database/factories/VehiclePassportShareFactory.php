<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehiclePassportShare;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehiclePassportShare>
 */
class VehiclePassportShareFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'expires_at' => now()->addDay(),
        ];
    }
}
