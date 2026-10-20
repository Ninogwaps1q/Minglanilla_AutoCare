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

it('filters repair jobs by stage while keeping the next action visible', function () {
    $fixture = repairFixture();
    $fixture['repairJob']->update(['status' => 'waiting_approval']);
    RepairJob::query()->create([
        'customer_id' => $fixture['customer']->id,
        'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id,
        'job_number' => 'RJ-TEST-002',
        'description' => 'Brake repair',
        'status' => 'repairing',
    ]);

    $this->actingAs($fixture['owner'])->get(route('shop.repair-jobs.index', ['status' => 'waiting_approval']))
        ->assertOk()->assertSee('Prepare and send the estimate for customer approval.')
        ->assertSee('RJ-TEST-001')->assertDontSee('RJ-TEST-002')
        ->assertViewHas('repairJobs', fn ($jobs) => $jobs->total() === 1);
});

it('keeps older in-progress repairs visible in the shop stage filter', function () {
    $fixture = repairFixture();
    $fixture['repairJob']->update(['status' => 'in_progress']);

    $this->actingAs($fixture['owner'])->get(route('shop.repair-jobs.index', ['status' => 'in_progress']))
        ->assertOk()->assertSee($fixture['repairJob']->job_number)
        ->assertViewHas('repairJobs', fn ($jobs) => $jobs->total() === 1);
});

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

    $quotation = Quotation::query()->create([
        'customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id, 'quotation_number' => 'QT-BOOKED-REPAIR',
        'subtotal' => 500, 'total' => 500, 'status' => 'sent',
    ]);
    $job->update(['quotation_id' => $quotation->id]);
    $this->actingAs($fixture['customer'])->patch(route('customer.quotations.update', $quotation), ['status' => 'accepted'])->assertRedirect();
    $this->actingAs($fixture['owner']);

    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'repairing'])->assertRedirect()->assertSessionHasNoErrors();
    expect($job->fresh()->started_at)->not->toBeNull();
    expect($appointment->fresh()->status)->toBe('in_progress');

    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'completed', 'actual_cost' => 500])->assertRedirect();
    expect($appointment->fresh()->status)->toBe('completed');
    expect($job->fresh()->completed_at)->not->toBeNull();
    $this->assertDatabaseHas('vehicle_service_records', ['repair_job_id' => $job->id, 'total_cost' => 500]);
});

it('rejects a second repair job for an appointment that already has one', function () {
    $fixture = repairFixture();
    $appointment = Appointment::query()->create([
        'customer_id' => $fixture['customer']->id,
        'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id,
        'appointment_date' => today(),
        'appointment_time' => '09:00',
        'status' => 'confirmed',
    ]);
    $fixture['repairJob']->update(['appointment_id' => $appointment->id]);

    $this->actingAs($fixture['owner'])->post(route('shop.repair-jobs.store'), [
        'appointment_id' => $appointment->id,
        'description' => 'Duplicate repair',
    ])->assertSessionHasErrors('appointment_id');

    $this->assertDatabaseCount('repair_jobs', 1);
    $this->get(route('shop.repair-jobs.create', ['appointment_id' => $appointment->id]))
        ->assertSee('No confirmed appointments yet');
});

it('creates a vehicle service record when a shop owner completes a repair job', function () {
    $fixture = repairFixture();

    $quotation = Quotation::query()->create([
        'customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id, 'quotation_number' => 'QT-COMPLETED-REPAIR',
        'subtotal' => 1500, 'total' => 1500, 'status' => 'sent',
    ]);
    $fixture['repairJob']->update(['quotation_id' => $quotation->id]);
    $this->actingAs($fixture['customer'])->patch(route('customer.quotations.update', $quotation), ['status' => 'accepted'])->assertRedirect();

    $this->actingAs($fixture['owner'])->patch(route('shop.repair-jobs.update', $fixture['repairJob']), ['status' => 'repairing'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $response = $this->patch(route('shop.repair-jobs.update', $fixture['repairJob']), [
        'status' => 'completed',
        'actual_cost' => 1500,
        'technician_notes' => 'Work completed.',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('repair_jobs', ['id' => $fixture['repairJob']->id, 'status' => 'completed', 'actual_cost' => 1500]);
    $this->assertDatabaseHas('vehicle_service_records', ['repair_job_id' => $fixture['repairJob']->id, 'total_cost' => 1500]);
});

it('blocks repair work and manual cost changes until the customer approves an estimate', function () {
    $fixture = repairFixture();
    $this->actingAs($fixture['owner']);

    $this->patch(route('shop.repair-jobs.update', $fixture['repairJob']), ['status' => 'repairing', 'actual_cost' => 1500])
        ->assertSessionHasErrors('status');
    $this->patch(route('shop.repair-jobs.update', $fixture['repairJob']), ['status' => 'completed', 'actual_cost' => 1500])
        ->assertSessionHasErrors('status');
    $this->patch(route('shop.repair-jobs.update', $fixture['repairJob']), ['status' => 'diagnosis', 'actual_cost' => 1500])
        ->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('repair_jobs', ['id' => $fixture['repairJob']->id, 'status' => 'diagnosis', 'actual_cost' => null]);
    $this->assertDatabaseHas('repair_job_status_histories', ['repair_job_id' => $fixture['repairJob']->id, 'from_status' => 'pending', 'to_status' => 'diagnosis', 'changed_by' => $fixture['owner']->id]);
    $this->assertDatabaseMissing('vehicle_service_records', ['repair_job_id' => $fixture['repairJob']->id]);
    $this->actingAs($fixture['customer'])->get(route('customer.repairs.index'))
        ->assertOk()->assertSee('Workflow history')->assertSee('Diagnosis')->assertSee($fixture['owner']->name);
});

it('lets the shop update diagnosis notes without changing the stage or sending a status notification', function () {
    $fixture = repairFixture();
    $job = $fixture['repairJob'];
    $job->update(['status' => 'diagnosis']);

    $this->actingAs($fixture['owner'])->patch(route('shop.repair-jobs.update', $job), [
        'status' => 'diagnosis',
        'diagnosis' => 'Brake pads are worn.',
        'technician_notes' => 'Replace both front pads.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('repair_jobs', [
        'id' => $job->id, 'status' => 'diagnosis',
        'diagnosis' => 'Brake pads are worn.', 'technician_notes' => 'Replace both front pads.',
    ]);
    $this->assertDatabaseMissing('notifications', ['user_id' => $fixture['customer']->id, 'type' => 'repair_status']);
    $this->actingAs($fixture['customer'])->get(route('customer.repairs.index'))->assertOk()->assertSee('Brake pads are worn.');
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
    $this->assertDatabaseHas('repair_jobs', ['id' => $fixture['repairJob']->id, 'status' => 'approved', 'started_at' => null]);
    $this->assertDatabaseHas('repair_job_status_histories', ['repair_job_id' => $fixture['repairJob']->id, 'to_status' => 'approved', 'changed_by' => $fixture['customer']->id]);
});

it('does not let a shop owner update another shop repair job', function () {
    $fixture = repairFixture();
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);

    $this->actingAs($otherOwner)
        ->patch(route('shop.repair-jobs.update', $fixture['repairJob']), ['status' => 'completed'])
        ->assertNotFound();
});

it('itemizes repair work and isolates line items by shop', function () {
    $fixture = repairFixture();

    $this->actingAs($fixture['owner'])->post(route('shop.repair-job-items.store', $fixture['repairJob']), [
        'type' => 'part',
        'description' => 'Brake pad set',
        'quantity' => 2,
        'unit_price' => 750,
    ])->assertRedirect();

    $item = $fixture['repairJob']->items()->sole();
    expect((float) $item->subtotal)->toBe(1500.0);
    expect($fixture['repairJob']->fresh()->actual_cost)->toBe('1500.00');
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    $this->actingAs($otherOwner)->delete(route('shop.repair-job-items.destroy', $item))->assertNotFound();
    $this->assertModelExists($item);
});

it('recalculates actual cost when work items are added or removed and keeps itemized totals authoritative', function () {
    $fixture = repairFixture();
    $this->actingAs($fixture['owner']);

    $this->post(route('shop.repair-job-items.store', $fixture['repairJob']), ['type' => 'labor', 'description' => 'Brake labor', 'quantity' => 2, 'unit_price' => '250.50'])->assertRedirect();
    $this->post(route('shop.repair-job-items.store', $fixture['repairJob']), ['type' => 'part', 'description' => 'Brake pads', 'quantity' => 1, 'unit_price' => '750.00'])->assertRedirect();
    expect($fixture['repairJob']->fresh()->actual_cost)->toBe('1251.00');

    $this->patch(route('shop.repair-jobs.update', $fixture['repairJob']), ['status' => 'diagnosis', 'actual_cost' => 1])->assertRedirect();
    expect($fixture['repairJob']->fresh()->actual_cost)->toBe('1251.00');

    $labor = $fixture['repairJob']->items()->where('type', 'labor')->firstOrFail();
    $this->delete(route('shop.repair-job-items.destroy', $labor))->assertRedirect();
    expect($fixture['repairJob']->fresh()->actual_cost)->toBe('750.00');
    $remainingItem = $fixture['repairJob']->items()->sole();
    $quotation = Quotation::query()->create([
        'customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id, 'quotation_number' => 'QT-ITEMIZED-REPAIR',
        'subtotal' => 750, 'total' => 750, 'status' => 'sent',
    ]);
    $fixture['repairJob']->update(['quotation_id' => $quotation->id]);
    $this->actingAs($fixture['customer'])->patch(route('customer.quotations.update', $quotation), ['status' => 'accepted'])->assertRedirect();
    $this->actingAs($fixture['owner']);
    $this->patch(route('shop.repair-jobs.update', $fixture['repairJob']), ['status' => 'completed'])->assertRedirect();
    $this->delete(route('shop.repair-job-items.destroy', $remainingItem))->assertConflict();
    expect($fixture['repairJob']->fresh()->actual_cost)->toBe('750.00');
});

it('guides an itemized diagnosis through estimate approval, repair start, completion, and invoicing', function () {
    $fixture = repairFixture();
    $job = $fixture['repairJob'];
    $job->update(['status' => 'diagnosis']);
    $this->actingAs($fixture['owner']);
    $this->post(route('shop.repair-job-items.store', $job), ['type' => 'labor', 'description' => 'Brake labor', 'quantity' => 1, 'unit_price' => 500])->assertRedirect();
    $this->post(route('shop.repair-job-items.store', $job), ['type' => 'part', 'description' => 'Brake pads', 'quantity' => 1, 'unit_price' => 750])->assertRedirect();

    $this->get(route('shop.repair-jobs.index'))
        ->assertOk()->assertSee('Send estimate for approval')->assertSee('Prepare and send the estimate for customer approval.');
    $this->get(route('shop.quotations.create', $job))
        ->assertOk()->assertSee('Brake labor')->assertSee('Brake pads')->assertSee('PHP 1,250.00');
    $this->post(route('shop.quotations.store', $job), ['valid_until' => today()->addDays(7)->toDateString(), 'notes' => 'Parts and labor'])
        ->assertRedirect(route('shop.repair-jobs.index'))->assertSessionHasNoErrors();

    $quote = $job->fresh()->quotation;
    expect($quote->total)->toBe('1250.00');
    expect($quote->items()->pluck('description')->all())->toBe(['Brake labor', 'Brake pads']);
    expect($job->fresh()->status)->toBe('waiting_approval');
    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'repairing'])->assertSessionHasErrors('status');

    $this->actingAs($fixture['customer'])->get(route('customer.repairs.index'))->assertOk()->assertSee('Approve estimate');
    $this->patch(route('customer.quotations.update', $quote), ['status' => 'accepted'])->assertRedirect();
    expect($job->fresh()->status)->toBe('approved');
    expect($job->fresh()->started_at)->toBeNull();

    $this->actingAs($fixture['owner'])->get(route('shop.repair-jobs.index'))->assertOk()->assertSee('Start repair');
    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'completed'])->assertSessionHasErrors('status');
    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'repairing'])->assertRedirect()->assertSessionHasNoErrors();
    expect($job->fresh()->started_at)->not->toBeNull();
    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'completed'])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('shop.invoices.store', $job))->assertRedirect(route('shop.invoices.index'));

    expect($job->fresh()->invoice->total)->toBe('1250.00');
    expect($job->fresh()->invoice->status)->toBe('unpaid');
});

it('uses saved repair items instead of a different submitted price when sending an estimate', function () {
    $fixture = repairFixture();
    $job = $fixture['repairJob'];
    $job->items()->create(['type' => 'labor', 'description' => 'Brake labor', 'quantity' => 1, 'unit_price' => 500, 'subtotal' => 500]);

    $this->actingAs($fixture['owner'])->post(route('shop.quotations.store', $job), [
        'items' => [['description' => 'Different work', 'quantity' => 1, 'unit_price' => 1]],
    ])->assertRedirect(route('shop.repair-jobs.index'))->assertSessionHasNoErrors();

    expect($job->fresh()->quotation->total)->toBe('500.00');
    expect($job->fresh()->quotation->items()->sole()->description)->toBe('Brake labor');
});

it('shows the estimate approval on the repair page and prevents other customers approving it', function () {
    $fixture = repairFixture();
    $quotation = Quotation::query()->create(['customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id, 'vehicle_id' => $fixture['vehicle']->id, 'quotation_number' => 'QT-REPAIR-APPROVAL', 'subtotal' => 850, 'total' => 850, 'status' => 'sent']);
    $quotation->items()->create(['description' => 'Brake service', 'quantity' => 1, 'unit_price' => 850, 'subtotal' => 850]);
    $fixture['repairJob']->update(['quotation_id' => $quotation->id, 'status' => 'waiting_approval']);

    $this->actingAs($fixture['customer'])->get(route('customer.repairs.index'))->assertOk()->assertSee('Approve estimate')->assertSee('Brake service');
    $this->actingAs(User::factory()->create(['role' => 'customer']))->patch(route('customer.quotations.update', $quotation), ['status' => 'accepted'])->assertNotFound();
    $this->actingAs($fixture['customer'])->patch(route('customer.quotations.update', $quotation), ['status' => 'accepted'])->assertRedirect();

    expect($quotation->fresh()->status)->toBe('accepted');
    expect($fixture['repairJob']->fresh()->status)->toBe('approved');
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
