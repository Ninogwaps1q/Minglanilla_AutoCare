<?php

use App\Models\Barangay;
use App\Models\Shop;
use App\Models\User;
use App\Models\VehicleServiceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows only the authenticated customer service history', function () {
    $barangay = Barangay::factory()->create();
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::query()->create(['owner_id' => $owner->id, 'name' => 'History Shop', 'phone' => '09170000000', 'email' => 'history@example.test', 'address' => 'Demo Street', 'barangay_id' => $barangay->id]);
    $customer = User::factory()->create(['role' => 'customer']);
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $vehicle = $customer->vehicles()->create(['plate_number' => 'HISTORY-001', 'make' => 'Toyota', 'model' => 'Vios', 'year' => 2020]);
    $otherVehicle = $otherCustomer->vehicles()->create(['plate_number' => 'HISTORY-002', 'make' => 'Honda', 'model' => 'City', 'year' => 2021]);
    VehicleServiceRecord::query()->create(['vehicle_id' => $vehicle->id, 'shop_id' => $shop->id, 'customer_id' => $customer->id, 'service_date' => today(), 'service_type' => 'Oil Change']);
    VehicleServiceRecord::query()->create(['vehicle_id' => $otherVehicle->id, 'shop_id' => $shop->id, 'customer_id' => $otherCustomer->id, 'service_date' => today(), 'service_type' => 'Hidden Service']);

    $this->actingAs($customer)->get(route('customer.service-history.index'))
        ->assertSee('Oil Change')
        ->assertDontSee('Hidden Service');
});
