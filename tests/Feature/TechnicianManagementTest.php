<?php

use App\Models\Appointment;
use App\Models\Barangay;
use App\Models\Quotation;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('manages technicians and assigns them to a shop repair job', function () {
    $fixture = technicianFixture();
    $this->actingAs($fixture['owner'])->get(route('shop.technicians.index'))->assertOk();
    $this->post(route('shop.technicians.store'), ['name' => 'Ana Cruz', 'phone' => '09170000000', 'specialization' => 'Brakes', 'experience_years' => 5, 'status' => 'active'])->assertRedirect()->assertSessionHas('status', 'Technician added.');
    $technician = $fixture['shop']->technicians()->where('name', 'Ana Cruz')->firstOrFail();
    $this->post(route('shop.technicians.assign', $fixture['job']), ['technician_id' => $technician->id, 'notes' => 'Brake inspection'])->assertRedirect()->assertSessionHas('status', 'Ana Cruz assigned to '.$fixture['job']->job_number.'.');
    $this->assertDatabaseHas('technician_assignments', ['repair_job_id' => $fixture['job']->id, 'technician_id' => $technician->id, 'notes' => 'Brake inspection']);
    expect($technician->fresh()->activeAssignments()->count())->toBe(1);
    $this->delete(route('shop.technicians.unassign', [$fixture['job'], $technician]))->assertRedirect()->assertSessionHas('status', 'Technician assignment closed.');
    expect($technician->fresh()->activeAssignments()->count())->toBe(0);
    $this->post(route('shop.technicians.assign', $fixture['job']), ['technician_id' => $technician->id])->assertRedirect();
    expect($technician->fresh()->activeAssignments()->count())->toBe(1);
    $this->get(route('shop.repair-jobs.index'))->assertOk()->assertSee('Ana Cruz')->assertSee('Unassign');
    $this->actingAs($fixture['job']->customer)->get(route('customer.repairs.index'))->assertOk()->assertSee('Technician:')->assertSee('Ana Cruz');
    $this->actingAs($fixture['owner'])->patch(route('shop.repair-jobs.update', $fixture['job']), ['status' => 'completed'])->assertRedirect();
    expect($technician->fresh()->activeAssignments()->count())->toBe(0);
});

it('isolates technicians and repair jobs between shops', function () {
    $fixture = technicianFixture();
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    $this->actingAs($otherOwner)->get(route('shop.technicians.index'))->assertNotFound();
    $otherShop = Shop::query()->create(['owner_id' => $otherOwner->id, 'barangay_id' => $fixture['shop']->barangay_id, 'name' => 'Other Shop', 'phone' => '09170000001', 'email' => 'other-tech@example.test', 'address' => 'Other Street']);
    $otherTechnician = $otherShop->technicians()->create(['name' => 'Other tech', 'experience_years' => 1, 'status' => 'active']);
    $this->actingAs($otherOwner)->post(route('shop.technicians.assign', $fixture['job']), ['technician_id' => $otherTechnician->id])->assertNotFound();
});

it('rejects inactive or invalid technicians for assignment', function () {
    $fixture = technicianFixture();
    $inactive = $fixture['shop']->technicians()->create(['name' => 'Inactive tech', 'experience_years' => 2, 'status' => 'inactive']);
    $this->actingAs($fixture['owner'])->post(route('shop.technicians.assign', $fixture['job']), ['technician_id' => $inactive->id])->assertNotFound();
    $this->actingAs($fixture['owner'])->post(route('shop.technicians.store'), ['name' => '', 'experience_years' => 81, 'status' => 'unknown'])->assertSessionHasErrors(['name', 'experience_years', 'status']);
});

it('prevents a technician from taking overlapping future repair appointments', function () {
    $fixture = technicianFixture();
    $shop = $fixture['shop'];
    $job = $fixture['job'];
    $technician = $shop->technicians()->create([
        'name' => 'Ria Santos', 'experience_years' => 4,
        'status' => 'active', 'max_active_jobs' => 3,
    ]);
    $date = today()->addDays(2)->toDateString();
    $firstAppointment = Appointment::query()->create([
        'customer_id' => $job->customer_id, 'shop_id' => $shop->id,
        'vehicle_id' => $job->vehicle_id, 'appointment_date' => $date,
        'appointment_time' => '10:00', 'duration_minutes' => 90, 'status' => 'confirmed',
    ]);
    $job->update(['appointment_id' => $firstAppointment->id]);
    $otherAppointment = Appointment::query()->create([
        'customer_id' => $job->customer_id, 'shop_id' => $shop->id,
        'vehicle_id' => $job->vehicle_id, 'appointment_date' => $date,
        'appointment_time' => '11:00', 'duration_minutes' => 60, 'status' => 'confirmed',
    ]);
    $otherJob = $shop->repairJobs()->create([
        'customer_id' => $job->customer_id, 'vehicle_id' => $job->vehicle_id,
        'appointment_id' => $otherAppointment->id, 'job_number' => 'RJ-TECH-OVERLAP',
        'description' => 'Second inspection', 'status' => 'repairing',
    ]);
    $this->actingAs($fixture['owner'])->post(route('shop.technicians.assign', $job), [
        'technician_id' => $technician->id,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('shop.technicians.assign', $otherJob), [
        'technician_id' => $technician->id,
    ])->assertSessionHasErrors('technician_id');
    $this->assertDatabaseMissing('technician_assignments', [
        'technician_id' => $technician->id, 'repair_job_id' => $otherJob->id,
    ]);
    $otherAppointment->update(['appointment_time' => '12:00']);
    $this->post(route('shop.technicians.assign', $otherJob), [
        'technician_id' => $technician->id,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->get(route('shop.technicians.index'))->assertOk()->assertSee('12:00 PM');
});

/** @return array{owner: User, shop: Shop, job: RepairJob} */
function technicianFixture(): array
{
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $customer = User::factory()->create(['role' => 'customer']);
    $shop = Shop::query()->create(['owner_id' => $owner->id, 'barangay_id' => Barangay::factory()->create()->id, 'name' => 'Technician Shop', 'phone' => '09170000000', 'email' => 'tech@example.test', 'address' => 'Demo Street']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $quotation = Quotation::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'vehicle_id' => $vehicle->id, 'quotation_number' => 'QT-TECH-001', 'subtotal' => 500, 'total' => 500, 'status' => 'accepted']);
    $job = RepairJob::query()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'quotation_id' => $quotation->id, 'job_number' => 'RJ-TECH-001', 'description' => 'Inspection', 'status' => 'repairing']);

    return compact('owner', 'shop', 'job');
}
