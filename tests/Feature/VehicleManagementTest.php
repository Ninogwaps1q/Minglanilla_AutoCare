<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a vehicle for the authenticated customer', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $make = VehicleMake::factory()->create(['name' => 'Toyota']);
    $model = VehicleModel::factory()->for($make, 'make')->create(['name' => 'Vios', 'variants' => ['E', 'G']]);

    $response = $this->actingAs($customer)->post('/customer/vehicles', [
        'plate_number' => 'ABC-1234', 'vehicle_type' => 'car', 'vehicle_make_id' => $make->id,
        'vehicle_model_id' => $model->id, 'year' => 2022, 'variant' => 'E',
    ]);

    $response->assertRedirect(route('customer.vehicles.index'));
    $this->assertDatabaseHas('vehicles', ['customer_id' => $customer->id, 'plate_number' => 'ABC-1234',
        'vehicle_make_id' => $make->id, 'vehicle_model_id' => $model->id, 'make' => 'Toyota', 'model' => 'Vios', 'variant' => 'E']);
});

it('accepts an unlisted vehicle while rejecting a model from another make', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $make = VehicleMake::factory()->create(['name' => 'Toyota']);
    $otherModel = VehicleModel::factory()->create(['name' => 'City']);

    $this->actingAs($customer)->post(route('customer.vehicles.store'), [
        'plate_number' => 'BAD-100', 'vehicle_type' => 'car', 'vehicle_make_id' => $make->id,
        'vehicle_model_id' => $otherModel->id, 'year' => 2022, 'variant' => 'Base',
    ])->assertInvalid(['vehicle_model_id']);

    $this->post(route('customer.vehicles.store'), [
        'plate_number' => 'NEW-100', 'vehicle_type' => 'other', 'make' => 'Local Maker',
        'model' => 'Prototype', 'year' => 2023, 'variant' => '__other', 'variant_other' => 'Special',
    ])->assertRedirect(route('customer.vehicles.index'));
    $this->assertDatabaseHas('vehicles', ['plate_number' => 'NEW-100', 'make' => 'Local Maker',
        'model' => 'Prototype', 'variant' => 'Special', 'vehicle_make_id' => null]);
});

it('returns to booking with a newly added vehicle selected', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)->get(route('customer.appointments.create'))->assertOk()->assertSee('Add a vehicle here');
    $response = $this->post(route('customer.vehicles.store'), [
        'plate_number' => 'BOOK-100', 'vehicle_type' => 'other', 'make' => 'Local Maker',
        'model' => 'Prototype', 'year' => 2023, 'return_to_booking' => '1',
    ]);
    $vehicle = Vehicle::query()->where('plate_number', 'BOOK-100')->sole();
    $response->assertRedirect(route('customer.appointments.create', ['vehicle_id' => $vehicle->id]));
});

it('removes retired passport pages without deleting vehicle service history', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $vehicle = Vehicle::factory()->for($customer, 'customer')->create();

    $this->actingAs($customer)->get("/customer/vehicles/{$vehicle->id}/passport")->assertNotFound();
    $this->get(route('customer.vehicles.show', $vehicle))->assertOk()->assertSee('Service history');
});

it('does not allow a customer to view another customer vehicle', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $vehicle = Vehicle::factory()->create();

    $this->actingAs($customer)->get(route('customer.vehicles.show', $vehicle))->assertForbidden();
});
