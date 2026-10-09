<?php

use App\Models\Barangay;
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

it('denies consumption to customers and validates positive quantities', function () {
    $fixture = inventoryConsumptionFixture();
    $payload = ['repair_job_id' => $fixture['job']->id, 'quantity' => 0];

    $this->actingAs($fixture['customer'])->post(route('shop.inventory.consume', $fixture['part']), $payload)->assertForbidden();
    $this->actingAs($fixture['owner'])->post(route('shop.inventory.consume', $fixture['part']), $payload)->assertSessionHasErrors('quantity');

    $this->assertDatabaseHas('spare_parts', ['id' => $fixture['part']->id, 'quantity' => 5]);
    $this->assertDatabaseCount('inventory_transactions', 0);
});

/** @return array{customer: User, owner: User, part: SparePart, job: RepairJob} */
function inventoryConsumptionFixture(): array
{
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::factory()->create(['owner_id' => $owner->id, 'barangay_id' => Barangay::factory()->create()->id,
        'name' => 'Inventory shop', 'phone' => '09170000000', 'email' => 'inventory@example.test', 'address' => 'Minglanilla']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $job = RepairJob::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'vehicle_id' => $vehicle->id,
        'job_number' => 'JOB-PARTS-001', 'status' => 'repairing', 'description' => 'Brake service']);
    $part = $shop->spareParts()->create(['name' => 'Brake pad', 'purchase_price' => 100, 'selling_price' => 150,
        'quantity' => 5, 'minimum_stock' => 2]);

    return compact('customer', 'owner', 'part', 'job');
}
