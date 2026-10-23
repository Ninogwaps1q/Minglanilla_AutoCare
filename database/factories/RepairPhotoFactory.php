<?php

namespace Database\Factories;

use App\Models\RepairJob;
use App\Models\RepairPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairPhoto>
 */
class RepairPhotoFactory extends Factory
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
            'stage' => 'before',
            'path' => 'repair-evidence/example.png',
            'caption' => 'Vehicle condition before work',
        ];
    }
}
