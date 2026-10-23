<?php

namespace Database\Factories;

use App\Models\RepairJob;
use App\Models\ServiceCase;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCase>
 */
class ServiceCaseFactory extends Factory
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
            'customer_id' => User::factory()->state(['role' => 'customer']),
            'shop_id' => Shop::factory(),
            'type' => 'follow_up',
            'subject' => 'Repair needs another look',
            'description' => 'The issue returned after the completed repair.',
            'status' => 'open',
        ];
    }

    public function forRepair(RepairJob $repairJob): static
    {
        return $this->state(fn (): array => [
            'repair_job_id' => $repairJob->id,
            'customer_id' => $repairJob->customer_id,
            'shop_id' => $repairJob->shop_id,
        ]);
    }
}
