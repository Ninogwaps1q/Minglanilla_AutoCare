<?php

namespace Database\Factories;

use App\Models\RepairJobStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairJobStatusHistory>
 */
class RepairJobStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_status' => 'pending',
            'to_status' => 'diagnosis',
            'changed_at' => now(),
        ];
    }
}
