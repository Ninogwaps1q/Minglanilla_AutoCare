<?php

use App\Models\Barangay;
use App\Models\Quotation;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('consumes stock for an active repair and records its source', function () {
    $fixture = inventoryConsumptionFixture();

    $this->actingAs($fixture['owner'])->post(route('shop.inventory.consume', $fixture['part']), [
        'repair_job_id' => $fixture['job']->id, 'quantity' => 3, 'notes' => 'Installed on vehicle',
    ])->assertRedirect();

    $this->assertDatabaseHas('spare_parts', ['id' => $fixture['part']->id, 'quantity' => 2]);
    $this->assertDatabaseHas('inventory_transactions', [
        'spare_part_id' => $fixture['part']->id, 'type' => 'repair_consumption', 'quantity' => -3,
        'reference_type' => 'repair_job', 'reference_id' => $fixture['job']->id,
        'created_by' => $fixture['owner']->id, 'notes' => 'Installed on vehicle',
    ]);
    $item = $fixture['job']->items()->sole();
    expect($item->subtotal)->toBe('450.00');
    expect($fixture['job']->fresh()->actual_cost)->toBe('450.00');
    $this->assertDatabaseHas('inventory_transactions', ['repair_job_item_id' => $item->id, 'spare_part_id' => $fixture['part']->id]);
    $this->actingAs($fixture['owner'])->get(route('shop.inventory.index'))->assertOk()->assertSee('1 part(s) are at or below minimum stock.');
});

it('rejects attempts to use more units than remain', function () {
    $fixture = inventoryConsumptionFixture();

    $this->actingAs($fixture['owner'])->post(route('shop.inventory.consume', $fixture['part']), [
        'repair_job_id' => $fixture['job']->id, 'quantity' => 6,
    ])->assertSessionHasErrors('quantity');

    $this->assertDatabaseHas('spare_parts', ['id' => $fixture['part']->id, 'quantity' => 5]);
    $this->assertDatabaseCount('inventory_transactions', 0);
});

it('deducts a quoted part once without changing the approved repair price', function () {
    $fixture = inventoryConsumptionFixture();
    $job = $fixture['job'];
    $quotation = Quotation::query()->create([
        'customer_id' => $fixture['customer']->id, 'shop_id' => $job->shop_id,
        'vehicle_id' => $job->vehicle_id, 'quotation_number' => 'QT-STOCK-001',
        'subtotal' => 400, 'total' => 400, 'status' => 'accepted',
    ]);
    $job->update(['quotation_id' => $quotation->id, 'actual_cost' => 400]);
    $item = $job->items()->create([
        'type' => 'part', 'description' => 'Brake pad', 'quantity' => 2,
        'unit_price' => 200, 'subtotal' => 400, 'spare_part_id' => $fixture['part']->id,
    ]);
    $payload = ['repair_job_id' => $job->id, 'repair_job_item_id' => $item->id,
        'spare_part_id' => $fixture['part']->id, 'quantity' => 2];

    $this->actingAs($fixture['owner'])->post(route('shop.repair-job-items.consume', $item), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('spare_parts', ['id' => $fixture['part']->id, 'quantity' => 3]);
    $this->assertDatabaseHas('inventory_transactions', ['repair_job_item_id' => $item->id, 'quantity' => -2]);
    expect($job->fresh()->actual_cost)->toBe('400.00');
    expect($item->fresh()->subtotal)->toBe('400.00');

    $this->post(route('shop.repair-job-items.consume', $item), $payload)
        ->assertSessionHasErrors(['repair_job_item_id' => 'Stock has already been used for this work item.']);
    $this->assertDatabaseHas('spare_parts', ['id' => $fixture['part']->id, 'quantity' => 3]);
    $this->assertDatabaseCount('inventory_transactions', 1);
});

it('rejects completed repairs and parts or repairs owned by another shop', function () {
    $fixture = inventoryConsumptionFixture();
    $fixture['job']->update(['status' => 'completed']);

    $this->actingAs($fixture['owner'])->post(route('shop.inventory.consume', $fixture['part']), [
        'repair_job_id' => $fixture['job']->id, 'quantity' => 1,
    ])->assertSessionHasErrors('repair_job_id');

    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    $otherShop = Shop::factory()->create(['owner_id' => $otherOwner->id, 'barangay_id' => Barangay::factory()->create()->id,
        'name' => 'Other inventory shop', 'phone' => '09170000001', 'email' => 'other-inventory@example.test', 'address' => 'Minglanilla']);
    $otherPart = $otherShop->spareParts()->create(['name' => 'Other filter', 'purchase_price' => 10, 'selling_price' => 20,
        'quantity' => 5, 'minimum_stock' => 1]);
    $fixture['job']->update(['status' => 'repairing']);

    $this->post(route('shop.inventory.consume', $otherPart), ['repair_job_id' => $fixture['job']->id, 'quantity' => 1])->assertNotFound();
    $this->actingAs($otherOwner)->post(route('shop.inventory.consume', $otherPart), ['repair_job_id' => $fixture['job']->id, 'quantity' => 1])->assertNotFound();
    $this->assertDatabaseCount('inventory_transactions', 0);
});

it('adds a recorded part to a draft estimate without deducting stock early', function () {
    $fixture = inventoryConsumptionFixture();
    $fixture['job']->update(['status' => 'diagnosis']);

    $this->actingAs($fixture['owner'])->post(route('shop.repair-job-items.store', $fixture['job']), [
        'type' => 'part', 'spare_part_id' => $fixture['part']->id, 'quantity' => 2,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('repair_job_items', [
        'repair_job_id' => $fixture['job']->id, 'spare_part_id' => $fixture['part']->id,
        'description' => 'Brake pad', 'quantity' => 2, 'unit_price' => 150, 'subtotal' => 300,
    ]);
    $this->assertDatabaseHas('spare_parts', ['id' => $fixture['part']->id, 'quantity' => 5]);
    $this->assertDatabaseCount('inventory_transactions', 0);
});

it('uses the saved inventory price instead of a submitted lower price', function () {
    $fixture = inventoryConsumptionFixture();
    $fixture['job']->update(['status' => 'diagnosis']);

    $this->actingAs($fixture['owner'])->post(route('shop.repair-job-items.store', $fixture['job']), [
        'type' => 'part', 'spare_part_id' => $fixture['part']->id, 'quantity' => 1,
        'description' => 'Forged description', 'unit_price' => 1,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('repair_job_items', [
        'repair_job_id' => $fixture['job']->id, 'description' => 'Brake pad',
        'unit_price' => 150, 'subtotal' => 150,
    ]);
});

it('carries a recorded part through estimate approval, repair, and invoicing', function () {
    $fixture = inventoryConsumptionFixture();
    $job = $fixture['job'];
    $job->update(['status' => 'diagnosis']);

    $this->actingAs($fixture['owner'])->post(route('shop.repair-job-items.store', $job), [
        'type' => 'part', 'spare_part_id' => $fixture['part']->id, 'quantity' => 2,
    ])->assertRedirect();
    $item = $job->items()->sole();
    $this->post(route('shop.quotations.store', $job), [
        'valid_until' => today()->addDays(7)->toDateString(), 'notes' => 'Brake pads',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $quote = $job->fresh()->quotation;
    expect($quote->total)->toBe('300.00');

    $this->actingAs($fixture['customer'])->patch(route('customer.quotations.update', $quote), [
        'status' => 'accepted',
    ])->assertRedirect();
    $this->actingAs($fixture['owner'])->patch(route('shop.repair-jobs.update', $job), [
        'status' => 'repairing',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('shop.repair-job-items.consume', $item), [
        'repair_job_id' => $job->id, 'repair_job_item_id' => $item->id,
        'spare_part_id' => $fixture['part']->id, 'quantity' => 2,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'completed'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('shop.invoices.store', $job))->assertRedirect();

    $this->assertDatabaseHas('spare_parts', ['id' => $fixture['part']->id, 'quantity' => 3]);
    $this->assertDatabaseHas('invoice_items', ['invoice_id' => $job->fresh()->invoice->id,
        'description' => 'Part: Brake pad', 'quantity' => 2, 'subtotal' => 300]);
    expect($job->fresh()->invoice->total)->toBe('300.00');
});

it('denies consumption to customers and validates positive quantities', function () {
    $fixture = inventoryConsumptionFixture();
    $payload = ['repair_job_id' => $fixture['job']->id, 'quantity' => 0];

    $this->actingAs($fixture['customer'])->post(route('shop.inventory.consume', $fixture['part']), $payload)->assertForbidden();
    $this->actingAs($fixture['owner'])->post(route('shop.inventory.consume', $fixture['part']), $payload)->assertSessionHasErrors('quantity');

    $this->assertDatabaseHas('spare_parts', ['id' => $fixture['part']->id, 'quantity' => 5]);
    $this->assertDatabaseCount('inventory_transactions', 0);
});

it('refuses a part from another shop on the repair item usage action', function () {
    $fixture = inventoryConsumptionFixture();
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    $otherShop = Shop::factory()->create([
        'owner_id' => $otherOwner->id, 'barangay_id' => Barangay::factory()->create()->id,
        'name' => 'Other parts shop', 'phone' => '09170000002',
        'email' => 'other-parts@example.test', 'address' => 'Minglanilla',
    ]);
    $otherPart = $otherShop->spareParts()->create([
        'name' => 'Other pad', 'purchase_price' => 100, 'selling_price' => 150,
        'quantity' => 5, 'minimum_stock' => 0,
    ]);
    $item = $fixture['job']->items()->create([
        'type' => 'part', 'description' => 'Brake pad', 'quantity' => 1,
        'unit_price' => 150, 'subtotal' => 150,
    ]);

    $this->actingAs($fixture['owner'])->post(route('shop.repair-job-items.consume', $item), [
        'repair_job_id' => $fixture['job']->id, 'repair_job_item_id' => $item->id,
        'spare_part_id' => $otherPart->id, 'quantity' => 1,
    ])->assertNotFound();

    $this->assertDatabaseHas('spare_parts', ['id' => $otherPart->id, 'quantity' => 5]);
    $this->assertDatabaseCount('inventory_transactions', 0);
});

it('rejects a new part charge after estimate approval', function () {
    $fixture = inventoryConsumptionFixture();
    $job = $fixture['job'];
    $quotation = Quotation::query()->create([
        'customer_id' => $fixture['customer']->id, 'shop_id' => $job->shop_id,
        'vehicle_id' => $job->vehicle_id, 'quotation_number' => 'QT-STOCK-002',
        'subtotal' => 500, 'total' => 500, 'status' => 'accepted',
    ]);
    $job->update(['quotation_id' => $quotation->id, 'actual_cost' => 500]);

    $this->actingAs($fixture['owner'])->post(route('shop.inventory.consume', $fixture['part']), [
        'repair_job_id' => $job->id, 'quantity' => 1,
    ])->assertSessionHasErrors('repair_job_item_id');

    $this->assertDatabaseHas('spare_parts', ['id' => $fixture['part']->id, 'quantity' => 5]);
    $this->assertDatabaseCount('inventory_transactions', 0);
    expect($job->fresh()->actual_cost)->toBe('500.00');
});

it('keeps a consumed part line for audit instead of deleting its stock history', function () {
    $fixture = inventoryConsumptionFixture();
    $this->actingAs($fixture['owner'])->post(route('shop.inventory.consume', $fixture['part']), [
        'repair_job_id' => $fixture['job']->id, 'quantity' => 1,
    ])->assertRedirect();
    $item = $fixture['job']->items()->sole();

    $this->delete(route('shop.repair-job-items.destroy', $item))->assertConflict();

    $this->assertModelExists($item);
    $this->assertDatabaseHas('spare_parts', ['id' => $fixture['part']->id, 'quantity' => 4]);
    $this->assertDatabaseHas('inventory_transactions', ['repair_job_item_id' => $item->id, 'quantity' => -1]);
});

it('reserves linked parts on approval and releases them when the repair is cancelled', function () {
    $fixture = inventoryConsumptionFixture();
    $job = $fixture['job'];
    $job->update(['status' => 'diagnosis']);
    $item = $job->items()->create([
        'type' => 'part', 'description' => 'Brake pad', 'quantity' => 3,
        'unit_price' => 150, 'subtotal' => 450, 'spare_part_id' => $fixture['part']->id,
    ]);
    $this->actingAs($fixture['owner'])->post(route('shop.quotations.store', $job), [])
        ->assertRedirect()->assertSessionHasNoErrors();
    $quote = $job->fresh()->quotation;

    $this->actingAs($fixture['customer'])->patch(route('customer.quotations.update', $quote), ['status' => 'accepted'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('inventory_reservations', [
        'repair_job_item_id' => $item->id, 'spare_part_id' => $fixture['part']->id,
        'quantity' => 3, 'status' => 'reserved',
    ]);
    expect($job->fresh()->status)->toBe('approved');
    expect($fixture['part']->fresh()->quantity)->toBe(5);
    $this->actingAs($fixture['owner'])->get(route('shop.inventory.index'))
        ->assertSee('3 reserved')->assertSee('2 available');
    $this->patch(route('shop.inventory.adjust', $fixture['part']), ['quantity' => -3])
        ->assertSessionHasErrors('quantity');

    $otherJob = RepairJob::query()->create([
        'customer_id' => $job->customer_id, 'shop_id' => $job->shop_id, 'vehicle_id' => $job->vehicle_id,
        'job_number' => 'JOB-PARTS-002', 'status' => 'repairing', 'description' => 'Second repair',
    ]);
    $this->post(route('shop.inventory.consume', $fixture['part']), ['repair_job_id' => $otherJob->id, 'quantity' => 3])
        ->assertSessionHasErrors('quantity');

    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'cancelled'])->assertRedirect();
    $this->assertDatabaseHas('inventory_reservations', ['repair_job_item_id' => $item->id, 'status' => 'released']);
    $this->post(route('shop.inventory.consume', $fixture['part']), ['repair_job_id' => $otherJob->id, 'quantity' => 3])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($fixture['part']->fresh()->quantity)->toBe(2);
});

it('waits for missing parts then reserves, consumes and returns unused units without double counting', function () {
    $fixture = inventoryConsumptionFixture();
    $job = $fixture['job'];
    $job->update(['status' => 'diagnosis']);
    $fixture['part']->update(['quantity' => 1]);
    $item = $job->items()->create([
        'type' => 'part', 'description' => 'Brake pad', 'quantity' => 2,
        'unit_price' => 150, 'subtotal' => 300, 'spare_part_id' => $fixture['part']->id,
    ]);
    $this->actingAs($fixture['owner'])->post(route('shop.quotations.store', $job), [])
        ->assertRedirect()->assertSessionHasNoErrors();

    $this->actingAs($fixture['customer'])->patch(route('customer.quotations.update', $job->fresh()->quotation), ['status' => 'accepted'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($job->fresh()->status)->toBe('waiting_parts');
    $this->assertDatabaseMissing('inventory_reservations', ['repair_job_item_id' => $item->id]);
    $this->actingAs($fixture['owner'])->patch(route('shop.repair-jobs.update', $job), ['status' => 'repairing'])
        ->assertSessionHasErrors('status');
    $this->post(route('shop.repair-job-items.reserve', $item))->assertSessionHasErrors('part');
    $this->patch(route('shop.inventory.adjust', $fixture['part']), ['quantity' => 2])->assertRedirect();
    $this->post(route('shop.repair-job-items.reserve', $item))->assertRedirect()->assertSessionHasNoErrors();
    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'repairing'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('shop.repair-job-items.consume', $item), [
        'repair_job_id' => $job->id, 'repair_job_item_id' => $item->id,
        'spare_part_id' => $fixture['part']->id, 'quantity' => 2,
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($fixture['part']->fresh()->quantity)->toBe(1);
    $this->assertDatabaseHas('inventory_reservations', ['repair_job_item_id' => $item->id, 'status' => 'consumed']);
    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'cancelled'])->assertRedirect();
    $this->post(route('shop.repair-job-items.return', $item), ['quantity' => 2, 'notes' => 'Unused unopened pack'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('shop.repair-job-items.return', $item), ['quantity' => 1, 'notes' => 'Repeat return'])
        ->assertSessionHasErrors('quantity');
    expect($fixture['part']->fresh()->quantity)->toBe(3);
    $this->assertDatabaseHas('inventory_transactions', [
        'repair_job_item_id' => $item->id, 'type' => 'repair_return', 'quantity' => 2,
    ]);
});

/** @return array{customer: User, owner: User, part: SparePart, job: RepairJob} */
function inventoryConsumptionFixture(): array
{
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::factory()->create(['owner_id' => $owner->id, 'barangay_id' => Barangay::factory()->create()->id,
        'name' => 'Inventory shop', 'phone' => '09170000000', 'email' => 'inventory@example.test', 'address' => 'Minglanilla',
        'verification_status' => 'approved', 'shop_status' => 'active']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $job = RepairJob::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'vehicle_id' => $vehicle->id,
        'job_number' => 'JOB-PARTS-001', 'status' => 'repairing', 'description' => 'Brake service']);
    $part = $shop->spareParts()->create(['name' => 'Brake pad', 'purchase_price' => 100, 'selling_price' => 150,
        'quantity' => 5, 'minimum_stock' => 2]);

    return compact('customer', 'owner', 'part', 'job');
}
