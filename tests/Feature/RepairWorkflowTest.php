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

it('keeps an appointment in progress until its repair is completed', function () {
    $fixture = repairFixture();
    $appointment = Appointment::query()->create([
        'customer_id' => $fixture['customer']->id,
        'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id,
        'appointment_date' => today(),
        'appointment_time' => '09:00',
        'status' => 'confirmed',
    ]);

    $this->actingAs($fixture['owner'])->post(route('shop.repair-jobs.store'), [
        'appointment_id' => $appointment->id,
        'description' => 'Brake repair',
    ])->assertRedirect(route('shop.repair-jobs.index'));

    $job = RepairJob::query()->where('appointment_id', $appointment->id)->firstOrFail();
    expect($appointment->fresh()->status)->toBe('in_progress');

    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'in_progress'])->assertRedirect();
    expect($job->fresh()->started_at)->not->toBeNull();
    expect($appointment->fresh()->status)->toBe('in_progress');

    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'completed', 'actual_cost' => 500])->assertRedirect();
    expect($appointment->fresh()->status)->toBe('completed');
    expect($job->fresh()->completed_at)->not->toBeNull();
    $this->assertDatabaseHas('vehicle_service_records', ['repair_job_id' => $job->id, 'total_cost' => 500]);
});

it('creates a vehicle service record when a shop owner completes a repair job', function () {
    $fixture = repairFixture();

    $response = $this->actingAs($fixture['owner'])->patch(route('shop.repair-jobs.update', $fixture['repairJob']), [
        'status' => 'completed',
        'actual_cost' => 1500,
        'technician_notes' => 'Work completed.',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('repair_jobs', ['id' => $fixture['repairJob']->id, 'status' => 'completed', 'actual_cost' => 1500]);
    $this->assertDatabaseHas('vehicle_service_records', ['repair_job_id' => $fixture['repairJob']->id, 'total_cost' => 1500]);
});

it('lets a customer approve their sent quotation', function () {
    $fixture = repairFixture();
    $quotation = Quotation::query()->create([
        'customer_id' => $fixture['customer']->id,
        'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id,
        'quotation_number' => 'QT-TEST-001',
        'subtotal' => 1200,
        'total' => 1200,
        'status' => 'sent',
    ]);
    $fixture['repairJob']->update(['quotation_id' => $quotation->id]);

    $response = $this->actingAs($fixture['customer'])
        ->patch(route('customer.quotations.update', $quotation), ['status' => 'approved']);

    $response->assertRedirect();
    $this->assertDatabaseHas('quotations', ['id' => $quotation->id, 'status' => 'approved']);
    $this->assertDatabaseHas('quotation_approval_records', [
        'quotation_id' => $quotation->id,
        'customer_id' => $fixture['customer']->id,
        'decision' => 'approved',
    ]);
    $this->assertDatabaseHas('repair_jobs', ['id' => $fixture['repairJob']->id, 'status' => 'in_progress']);
});

it('does not let a shop owner update another shop repair job', function () {
    $fixture = repairFixture();
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);

    $this->actingAs($otherOwner)
        ->patch(route('shop.repair-jobs.update', $fixture['repairJob']), ['status' => 'completed'])
        ->assertNotFound();
});

it('records inventory adjustments for the shop that owns the part', function () {
    $fixture = repairFixture();

    $this->actingAs($fixture['owner'])->post(route('shop.inventory.store'), [
        'name' => 'Engine Oil',
        'purchase_price' => 300,
        'selling_price' => 450,
        'quantity' => 5,
        'minimum_stock' => 2,
    ])->assertRedirect();

    $part = $fixture['shop']->spareParts()->firstOrFail();

    $this->actingAs($fixture['owner'])->patch(route('shop.inventory.adjust', $part), ['quantity' => 3])
        ->assertRedirect();

    $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'quantity' => 8]);
    $this->assertDatabaseHas('inventory_transactions', ['spare_part_id' => $part->id, 'quantity' => 3, 'type' => 'restock']);
});

it('shows low stock warnings and blocks another shop from changing inventory', function () {
    $fixture = repairFixture();
    $this->actingAs($fixture['owner'])->post(route('shop.inventory.store'), ['name' => 'Low Stock Filter', 'purchase_price' => 10, 'selling_price' => 20, 'quantity' => 2, 'minimum_stock' => 3])->assertRedirect();
    $this->actingAs($fixture['owner'])->get(route('shop.inventory.index'))->assertOk()->assertSee('1 part(s) are at or below minimum stock.');
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    $this->actingAs($otherOwner)->patch(route('shop.inventory.adjust', $fixture['shop']->spareParts()->first()), ['quantity' => 1])->assertNotFound();
});

it('rejects invalid inventory prices and excessive adjustments', function () {
    $fixture = repairFixture();
    $this->actingAs($fixture['owner'])->post(route('shop.inventory.store'), ['name' => 'Invalid part', 'purchase_price' => -1, 'selling_price' => 20, 'quantity' => 0, 'minimum_stock' => 0])->assertSessionHasErrors('purchase_price');
    $part = $fixture['shop']->spareParts()->create(['name' => 'Filter', 'purchase_price' => 10, 'selling_price' => 20, 'quantity' => 1, 'minimum_stock' => 0]);
    $this->patch(route('shop.inventory.adjust', $part), ['quantity' => -2])->assertStatus(422);
    expect($part->fresh()->quantity)->toBe(1);
});

/**
 * @return array{customer: User, owner: User, shop: Shop, vehicle: Vehicle, repairJob: RepairJob}
 */
function repairFixture(): array
{
    $barangay = Barangay::factory()->create();
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::query()->create(['owner_id' => $owner->id, 'name' => 'Repair Shop', 'phone' => '09170000000', 'email' => 'repair@example.test', 'address' => 'Demo Street', 'barangay_id' => $barangay->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $vehicle = $customer->vehicles()->create(['plate_number' => 'REPAIR-001', 'make' => 'Toyota', 'model' => 'Vios', 'year' => 2020]);
    $repairJob = RepairJob::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'vehicle_id' => $vehicle->id, 'job_number' => 'RJ-TEST-001', 'description' => 'Engine inspection', 'status' => 'pending']);

    return compact('customer', 'owner', 'shop', 'vehicle', 'repairJob');
}
