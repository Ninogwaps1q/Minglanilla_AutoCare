<?php

namespace Database\Factories;

use App\Models\RepairJob;
use App\Models\RepairWarranty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairWarranty>
 */
class RepairWarrantyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repair_job_id' => RepairJob::factory(),
            'coverage' => 'Parts and labor listed on the repair invoice',
            'valid_from' => today(),
            'valid_until' => today()->addDays(90),
        ];
    }
}
