<?php

use App\Models\Barangay;
use App\Models\EmergencyRequest;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assigns a customer emergency request to the nearest eligible shop', function () {
    $fixture = emergencyFixture();
    $nearbyShop = createEmergencyShop($fixture['barangay'], 10.2400, 123.7800);
    createEmergencyShop($fixture['barangay'], 10.2800, 123.8200);

    $this->actingAs($fixture['customer'])->post(route('customer.emergency-requests.store'), [
        'vehicle_id' => $fixture['vehicle']->id,
        'emergency_type' => 'breakdown',
        'latitude' => 10.2410,
        'longitude' => 123.7810,
        'address' => 'Minglanilla town center',
    ])->assertRedirect();

    $this->assertDatabaseHas('emergency_requests', [
        'customer_id' => $fixture['customer']->id,
        'assigned_shop_id' => $nearbyShop->id,
        'status' => 'pending',
    ]);
    $this->assertDatabaseHas('notifications', ['user_id' => $nearbyShop->owner_id, 'type' => 'emergency_request']);
});

it('does not allow a shop to update another shop emergency request', function () {
    $fixture = emergencyFixture();
    $assignedShop = createEmergencyShop($fixture['barangay'], 10.2400, 123.7800);
    $otherShop = createEmergencyShop($fixture['barangay'], 10.2600, 123.8000);
    $request = EmergencyRequest::query()->create([
        'customer_id' => $fixture['customer']->id,
        'vehicle_id' => $fixture['vehicle']->id,
        'assigned_shop_id' => $assignedShop->id,
        'emergency_type' => 'flat_tire',
        'latitude' => 10.24,
        'longitude' => 123.78,
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $this->actingAs($otherShop->owner)
        ->patch(route('shop.emergency-requests.update', $request), ['status' => 'accepted'])
        ->assertNotFound();
});

it('rejects skipped and backwards roadside status transitions without notifying the customer', function (string $from, string $to) {
    $fixture = emergencyFixture();
    $shop = createEmergencyShop($fixture['barangay'], 10.24, 123.78);
    $request = $fixture['customer']->emergencyRequests()->create([
        'assigned_shop_id' => $shop->id, 'emergency_type' => 'breakdown',
        'latitude' => 10.24, 'longitude' => 123.78, 'status' => $from, 'requested_at' => now(),
    ]);

    $this->actingAs($shop->owner)->patch(route('shop.emergency-requests.update', $request), ['status' => $to])
        ->assertSessionHasErrors('status');

    $this->assertDatabaseHas('emergency_requests', ['id' => $request->id, 'status' => $from]);
    $this->assertDatabaseCount('notifications', 0);
})->with([
    'skip acceptance' => ['pending', 'en_route'],
    'skip response' => ['pending', 'completed'],
    'move backwards' => ['en_route', 'accepted'],
    'repeat acceptance' => ['accepted', 'accepted'],
]);

it('preserves the first response time through roadside completion', function () {
    $fixture = emergencyFixture();
    $shop = createEmergencyShop($fixture['barangay'], 10.24, 123.78);
    $request = $fixture['customer']->emergencyRequests()->create([
        'assigned_shop_id' => $shop->id, 'emergency_type' => 'breakdown',
        'latitude' => 10.24, 'longitude' => 123.78, 'status' => 'pending', 'requested_at' => now(),
    ]);
    $this->travelTo(now()->startOfSecond());
    $respondedAt = now()->toDateTimeString();
    $this->actingAs($shop->owner)->patch(route('shop.emergency-requests.update', $request), ['status' => 'accepted'])->assertRedirect();
    $this->travel(5)->minutes();
    $this->patch(route('shop.emergency-requests.update', $request), ['status' => 'en_route'])->assertRedirect();
    $this->travel(10)->minutes();
    $this->patch(route('shop.emergency-requests.update', $request), ['status' => 'completed'])->assertRedirect();

    $this->assertDatabaseHas('emergency_requests', [
        'id' => $request->id, 'status' => 'completed', 'responded_at' => $respondedAt, 'completed_at' => now()->toDateTimeString(),
    ]);
    $this->assertDatabaseCount('notifications', 3);
    $this->travelBack();
});

it('prevents inactive shops from responding to roadside requests', function () {
    $fixture = emergencyFixture();
    $shop = createEmergencyShop($fixture['barangay'], 10.24, 123.78);
    $request = $fixture['customer']->emergencyRequests()->create([
        'assigned_shop_id' => $shop->id, 'emergency_type' => 'breakdown',
        'latitude' => 10.24, 'longitude' => 123.78, 'status' => 'pending', 'requested_at' => now(),
    ]);
    $shop->update(['shop_status' => 'inactive']);

    $this->actingAs($shop->owner)->patch(route('shop.emergency-requests.update', $request), ['status' => 'accepted'])->assertForbidden();

    $this->assertDatabaseHas('emergency_requests', ['id' => $request->id, 'status' => 'pending']);
    $this->assertDatabaseCount('notifications', 0);
});

it('renders only the next valid roadside actions and hides controls on closed requests', function () {
    $fixture = emergencyFixture();
    $shop = createEmergencyShop($fixture['barangay'], 10.24, 123.78);
    $request = $fixture['customer']->emergencyRequests()->create([
        'assigned_shop_id' => $shop->id, 'emergency_type' => 'breakdown',
        'latitude' => 10.24, 'longitude' => 123.78, 'status' => 'pending', 'requested_at' => now(),
    ]);

    $this->actingAs($shop->owner)->get(route('shop.emergency-requests.index'))
        ->assertSee('value="accepted"', false)
        ->assertDontSee('value="completed"', false)
        ->assertSee('Next status for request');
    $request->update(['status' => 'completed']);
    $this->get(route('shop.emergency-requests.index'))
        ->assertSee('Request closed')
        ->assertDontSee('name="status"', false);
});

/** @return array{barangay: Barangay, customer: User, vehicle: Vehicle} */
function emergencyFixture(): array
{
    $barangay = Barangay::factory()->create();
    $customer = User::factory()->create(['role' => 'customer']);
    $vehicle = $customer->vehicles()->create(['plate_number' => 'EMERGENCY-001', 'make' => 'Toyota', 'model' => 'Vios', 'year' => 2020]);

    return compact('barangay', 'customer', 'vehicle');
}

function createEmergencyShop(Barangay $barangay, float $latitude, float $longitude): Shop
{
    $owner = User::factory()->create(['role' => 'shop_owner']);

    return Shop::query()->create(['owner_id' => $owner->id, 'name' => fake()->company(), 'phone' => fake()->unique()->numerify('0917#######'), 'email' => fake()->unique()->safeEmail(), 'address' => 'Minglanilla', 'barangay_id' => $barangay->id, 'latitude' => $latitude, 'longitude' => $longitude, 'verification_status' => 'approved', 'shop_status' => 'active']);
}
