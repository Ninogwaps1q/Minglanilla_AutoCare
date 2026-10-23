<?php

namespace Database\Factories;

use App\Models\ServiceCase;
use App\Models\ServiceCaseEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCaseEvent>
 */
class ServiceCaseEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_case_id' => ServiceCase::factory(),
            'to_status' => 'open',
            'note' => 'Request submitted',
        ];
    }
}
