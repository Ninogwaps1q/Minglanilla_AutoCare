<?php

use App\Models\Barangay;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication and the correct role for inspection pages', function () {
    $fixture = inspectionFixture();
    $this->get(route('customer.inspections.index'))->assertRedirect('/login');
    $this->get(route('shop.inspections.create', $fixture['job']))->assertRedirect('/login');
    $this->actingAs($fixture['customer'])->post(route('shop.inspections.store', $fixture['job']), inspectionPayload())->assertForbidden();
    $this->actingAs($fixture['owner'])->get(route('customer.inspections.index'))->assertForbidden();
});

it('records an inspection with checklist items for the repair vehicle', function () {
    $fixture = inspectionFixture();
    $this->actingAs($fixture['owner'])->get(route('shop.inspections.create', $fixture['job']))->assertOk();
    $payload = inspectionPayload();
    $payload['items'][] = ['category' => 'Tires', 'item_name' => 'Tread', 'condition' => 'Good', 'recommendation' => null];
    $payload['vehicle_id'] = 999999;
    $payload['shop_id'] = 999999;
    $this->post(route('shop.inspections.store', $fixture['job']), $payload)
        ->assertRedirect(route('shop.repair-jobs.index'))->assertSessionHas('status', 'Vehicle inspection saved.');

    $this->assertDatabaseHas('vehicle_inspections', ['vehicle_id' => $fixture['vehicle']->id, 'shop_id' => $fixture['shop']->id, 'overall_condition' => 'Needs Attention']);
    $this->assertDatabaseHas('inspection_items', ['item_name' => 'Brake pads', 'condition' => 'Needs Attention']);
    $this->assertDatabaseCount('inspection_items', 2);
});

it('prevents another shop owner from inspecting a private repair job', function () {
    $fixture = inspectionFixture();
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    $this->actingAs($otherOwner)->get(route('shop.inspections.create', $fixture['job']))->assertNotFound();
    $this->post(route('shop.inspections.store', $fixture['job']), inspectionPayload())->assertNotFound();
    $this->assertDatabaseCount('vehicle_inspections', 0);
});

it('rejects invalid inspection conditions without partial records', function () {
    $fixture = inspectionFixture();
    $payload = inspectionPayload();
    $payload['items'][0]['condition'] = 'Unknown';
    $this->actingAs($fixture['owner'])->post(route('shop.inspections.store', $fixture['job']), $payload)
        ->assertSessionHasErrors('items.0.condition');
    $this->assertDatabaseCount('vehicle_inspections', 0);
    $this->assertDatabaseCount('inspection_items', 0);
});

it('shows inspection results only to the vehicle owner and escapes notes', function () {
    $fixture = inspectionFixture();
    $this->actingAs($fixture['owner'])->post(route('shop.inspections.store', $fixture['job']), inspectionPayload())->assertRedirect();
    $this->actingAs($fixture['customer'])->get(route('customer.inspections.index'))->assertOk()
        ->assertSee('Brake pads')->assertSee('Replace worn pads')
        ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($otherCustomer)->get(route('customer.inspections.index'))->assertOk()->assertDontSee('Brake pads')->assertSee('No vehicle inspections recorded yet.');
});

/** @return array{owner: User, customer: User, shop: Shop, vehicle: Vehicle, job: RepairJob} */
function inspectionFixture(): array
{
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $customer = User::factory()->create(['role' => 'customer']);
    $shop = Shop::query()->create(['owner_id' => $owner->id, 'barangay_id' => Barangay::factory()->create()->id, 'name' => 'Demo inspection shop', 'phone' => '09170000000', 'email' => 'inspection@example.test', 'address' => 'Demo Street']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $job = RepairJob::query()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'job_number' => 'RJ-INSPECTION', 'status' => 'pending']);

    return compact('owner', 'customer', 'shop', 'vehicle', 'job');
}

/** @return array<string, mixed> */
function inspectionPayload(): array
{
    return [
        'inspection_date' => today()->toDateString(), 'overall_condition' => 'Needs Attention',
        'notes' => '<script>alert(1)</script>',
        'items' => [['category' => 'Brakes', 'item_name' => 'Brake pads', 'condition' => 'Needs Attention', 'recommendation' => 'Replace worn pads']],
    ];
}
