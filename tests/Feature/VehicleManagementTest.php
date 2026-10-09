<?php

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a vehicle for the authenticated customer', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $response = $this->actingAs($customer)->post('/customer/vehicles', ['plate_number' => 'ABC-1234', 'make' => 'Toyota', 'model' => 'Vios', 'year' => 2022, 'current_mileage' => 12000]);

    $response->assertRedirect(route('customer.vehicles.index'));
    $this->assertDatabaseHas('vehicles', ['customer_id' => $customer->id, 'plate_number' => 'ABC-1234']);
});

it('does not allow a customer to view another customer vehicle', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $vehicle = Vehicle::factory()->create();

    $this->actingAs($customer)->get(route('customer.vehicles.show', $vehicle))->assertForbidden();
});
