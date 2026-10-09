<?php

use App\Models\Appointment;
use App\Models\Barangay;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Shop;
use App\Models\ShopService;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a pending appointment for an available public shop', function () {
    $fixture = appointmentFixture();

    $response = $this->actingAs($fixture['customer'])->post(route('customer.appointments.store'), appointmentPayload($fixture));

    $response->assertRedirect(route('customer.appointments.index'));
    $this->assertDatabaseHas('notifications', [
        'user_id' => $fixture['owner']->id,
        'type' => 'appointment_requested',
    ]);
    $this->assertDatabaseHas('appointments', [
        'customer_id' => $fixture['customer']->id,
        'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id,
        'service_id' => $fixture['service']->id,
        'status' => 'pending',
    ]);
});

it('rejects a booking that uses another customer vehicle', function () {
    $fixture = appointmentFixture();
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $otherVehicle = $otherCustomer->vehicles()->create([
        'plate_number' => 'OTHER-001',
        'make' => 'Honda',
        'model' => 'City',
        'year' => 2022,
    ]);

    $response = $this->actingAs($fixture['customer'])->from(route('customer.appointments.create'))
        ->post(route('customer.appointments.store'), appointmentPayload($fixture, ['vehicle_id' => $otherVehicle->id]));

    $response->assertRedirect(route('customer.appointments.create'));
    $response->assertSessionHasErrors('vehicle_id');
    $this->assertDatabaseMissing('appointments', ['vehicle_id' => $otherVehicle->id]);
});

it('rejects a conflicting active appointment slot for the same shop', function () {
    $fixture = appointmentFixture();
    Appointment::query()->create(array_merge(appointmentPayload($fixture), ['customer_id' => $fixture['customer']->id, 'status' => 'confirmed']));
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $otherVehicle = $otherCustomer->vehicles()->create(['plate_number' => 'SLOT-002', 'make' => 'Honda', 'model' => 'City', 'year' => 2021]);

    $response = $this->actingAs($otherCustomer)->from(route('customer.appointments.create'))
        ->post(route('customer.appointments.store'), appointmentPayload($fixture, ['vehicle_id' => $otherVehicle->id]));

    $response->assertRedirect(route('customer.appointments.create'));
    $response->assertSessionHasErrors('appointment_time');
    $this->assertDatabaseCount('appointments', 1);
});

it('does not let a customer cancel another customer appointment', function () {
    $fixture = appointmentFixture();
    $appointment = Appointment::query()->create(array_merge(appointmentPayload($fixture), ['customer_id' => $fixture['customer']->id]));
    $otherCustomer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($otherCustomer)
        ->delete(route('customer.appointments.destroy', $appointment))
        ->assertNotFound();
});

it('updates the appointment status for the shop owner', function () {
    $fixture = appointmentFixture();
    $appointment = Appointment::query()->create(array_merge(appointmentPayload($fixture), ['customer_id' => $fixture['customer']->id]));

    $response = $this->actingAs($fixture['owner'])
        ->patch(route('shop.appointments.update', $appointment), ['status' => 'confirmed']);

    $response->assertRedirect();
    $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'confirmed']);
    $this->assertDatabaseHas('appointment_status_histories', [
        'appointment_id' => $appointment->id,
        'from_status' => 'pending',
        'to_status' => 'confirmed',
        'changed_by' => $fixture['owner']->id,
    ]);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $fixture['customer']->id,
        'type' => 'appointment_status',
    ]);
});

it('blocks invalid appointment status transitions', function () {
    $fixture = appointmentFixture();
    $appointment = Appointment::query()->create(array_merge(appointmentPayload($fixture), ['customer_id' => $fixture['customer']->id, 'status' => 'pending']));

    $this->actingAs($fixture['owner'])
        ->patch(route('shop.appointments.update', $appointment), ['status' => 'completed'])
        ->assertSessionHasErrors('status');

    expect($appointment->fresh()->status)->toBe('pending');
    $this->assertDatabaseCount('appointment_status_histories', 0);
});

it('does not let a shop owner update another shop appointment', function () {
    $fixture = appointmentFixture();
    $appointment = Appointment::query()->create(array_merge(appointmentPayload($fixture), ['customer_id' => $fixture['customer']->id]));
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    $otherShop = Shop::query()->create([
        'owner_id' => $otherOwner->id,
        'name' => 'Other Shop',
        'phone' => '09170000002',
        'email' => 'other-shop@example.test',
        'address' => 'Other Street',
        'barangay_id' => $fixture['barangay']->id,
        'verification_status' => 'approved',
        'shop_status' => 'active',
    ]);
    $appointment->update(['shop_id' => $otherShop->id]);

    $this->actingAs($fixture['owner'])
        ->patch(route('shop.appointments.update', $appointment), ['status' => 'confirmed'])
        ->assertNotFound();
});

it('records customer cancellation history and notifies the shop exactly once', function () {
    $fixture = appointmentFixture();
    $appointment = $fixture['customer']->appointments()->create(appointmentPayload($fixture));

    $this->actingAs($fixture['customer'])->delete(route('customer.appointments.destroy', $appointment))->assertRedirect();
    $this->delete(route('customer.appointments.destroy', $appointment))->assertNotFound();

    $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'cancelled']);
    $this->assertDatabaseHas('appointment_status_histories', [
        'appointment_id' => $appointment->id, 'from_status' => 'pending',
        'to_status' => 'cancelled', 'changed_by' => $fixture['customer']->id,
    ]);
    $this->assertDatabaseCount('appointment_status_histories', 1);
    $this->assertDatabaseHas('notifications', ['user_id' => $fixture['owner']->id, 'type' => 'appointment_cancelled']);
    $this->assertDatabaseCount('notifications', 1);
});

/**
 * @return array{barangay: Barangay, customer: User, owner: User, shop: Shop, vehicle: Vehicle, service: Service}
 */
function appointmentFixture(): array
{
    $barangay = Barangay::factory()->create();
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Appointment Shop',
        'phone' => '09170000000',
        'email' => 'appointment-shop@example.test',
        'address' => 'Demo Street',
        'barangay_id' => $barangay->id,
        'verification_status' => 'approved',
        'shop_status' => 'active',
    ]);
    $vehicle = $customer->vehicles()->create([
        'plate_number' => 'BOOK-001',
        'make' => 'Toyota',
        'model' => 'Vios',
        'year' => 2020,
    ]);
    $category = ServiceCategory::query()->create(['name' => 'Maintenance']);
    $service = Service::query()->create(['category_id' => $category->id, 'name' => 'Oil Change']);
    ShopService::query()->create([
        'shop_id' => $shop->id,
        'service_id' => $service->id,
        'price' => 900,
        'availability' => true,
    ]);

    return compact('barangay', 'customer', 'owner', 'shop', 'vehicle', 'service');
}

/**
 * @param  array{shop: Shop, vehicle: Vehicle, service: Service}  $fixture
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function appointmentPayload(array $fixture, array $overrides = []): array
{
    return array_merge([
        'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id,
        'service_id' => $fixture['service']->id,
        'appointment_date' => today()->addDay()->toDateString(),
        'appointment_time' => '10:00',
        'reason' => 'Routine maintenance',
    ], $overrides);
}
