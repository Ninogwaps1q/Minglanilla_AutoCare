<?php

use App\Models\MaintenanceRecommendation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the maintenance planner with the available service catalog', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $customer->vehicles()->create(['plate_number' => 'PLAN-001', 'make' => 'Toyota', 'model' => 'Vios', 'year' => 2022]);
    Service::factory()->create(['name' => 'Engine oil change']);

    $response = $this->actingAs($customer)->get(route('customer.reminders.index'));

    $response
        ->assertOk()
        ->assertSeeText('Maintenance planner')
        ->assertSeeText('Engine oil change');
});

it('calculates the next date and mileage for a customer maintenance schedule', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $vehicle = $customer->vehicles()->create(['plate_number' => 'PLAN-100', 'make' => 'Toyota', 'model' => 'Vios', 'year' => 2022]);

    $this->actingAs($customer)->post(route('customer.maintenance-schedules.store'), ['vehicle_id' => $vehicle->id, 'last_service_date' => '2026-01-01', 'last_service_mileage' => 10000, 'recommended_interval_days' => 180, 'recommended_interval_mileage' => 5000])->assertRedirect();

    $schedule = $vehicle->maintenanceSchedules()->sole();
    expect($schedule->next_service_date->toDateString())->toBe('2026-06-30')
        ->and($schedule->next_service_mileage)->toBe(15000);
});

it('does not let a customer change another customers maintenance data', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $other = User::factory()->create(['role' => 'customer']);
    $vehicle = $customer->vehicles()->create(['plate_number' => 'PLAN-200', 'make' => 'Honda', 'model' => 'City', 'year' => 2021]);
    $schedule = $vehicle->maintenanceSchedules()->create(['recommended_interval_days' => 90]);
    $recommendation = MaintenanceRecommendation::query()->create(['vehicle_id' => $vehicle->id, 'customer_id' => $customer->id, 'recommendation' => 'Change oil', 'reason' => 'Mileage interval', 'priority' => 'medium', 'source' => 'system', 'generated_at' => now()]);

    $this->actingAs($other)->delete(route('customer.maintenance-schedules.destroy', $schedule))->assertNotFound();
    $this->patch(route('customer.maintenance-recommendations.update', $recommendation), ['status' => 'completed'])->assertNotFound();
});

it('lets a customer update their own maintenance recommendation', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $vehicle = $customer->vehicles()->create(['plate_number' => 'PLAN-300', 'make' => 'Ford', 'model' => 'Ranger', 'year' => 2023]);
    $recommendation = MaintenanceRecommendation::query()->create(['vehicle_id' => $vehicle->id, 'customer_id' => $customer->id, 'recommendation' => 'Inspect brakes', 'reason' => 'Safety interval', 'priority' => 'high', 'source' => 'system', 'generated_at' => now()]);

    $this->actingAs($customer)->patch(route('customer.maintenance-recommendations.update', $recommendation), ['status' => 'accepted'])->assertRedirect();

    expect($recommendation->fresh()->status)->toBe('accepted');
});
