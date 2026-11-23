<?php

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves each address level from local PSGC records', function () {
    $region = Region::factory()->create(['name' => 'Davao Region', 'psgc_code' => '110000000']);
    $province = Province::factory()->create(['name' => 'Davao del Sur', 'region_id' => $region->id, 'psgc_code' => '112400000']);
    $city = Municipality::factory()->create(['name' => 'Digos City', 'province_id' => $province->id, 'psgc_code' => '112403000']);
    $barangay = Barangay::factory()->create(['name' => 'Aplaya', 'municipality_id' => $city->id, 'psgc_code' => '112403001']);

    $this->getJson(route('catalog.regions'))->assertOk()->assertJsonFragment(['name' => 'Davao Region', 'psgc_code' => '110000000']);
    $this->getJson(route('catalog.provinces', ['region_id' => $region->id]))->assertOk()->assertJsonFragment(['name' => 'Davao del Sur']);
    $this->getJson(route('catalog.municipalities', ['province_id' => $province->id]))->assertOk()->assertJsonFragment(['name' => 'Digos City']);
    $this->getJson(route('catalog.barangays', ['municipality_id' => $city->id]))->assertOk()->assertJsonFragment(['name' => 'Aplaya']);
    $this->getJson(route('catalog.address-path', ['barangay_id' => $barangay->id]))->assertOk()->assertJsonPath('region_id', $region->id);
});

it('allows only admins to maintain makes, models, and variants', function () {
    $make = VehicleMake::factory()->create(['name' => 'Toyota', 'vehicle_type' => 'car']);
    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)->post(route('admin.vehicle-models.store'), ['vehicle_make_id' => $make->id, 'name' => 'Vios'])->assertForbidden();

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->post(route('admin.vehicle-models.store'), ['vehicle_make_id' => $make->id, 'name' => 'Vios', 'variants' => 'E, G'])->assertRedirect();
    $model = VehicleModel::query()->where('name', 'Vios')->sole();
    expect($model->variants)->toBe(['E', 'G']);
    $this->getJson(route('catalog.vehicle-makes', ['type' => 'car']))->assertOk()->assertJsonFragment(['name' => 'Toyota']);
    $this->getJson(route('catalog.vehicle-models', ['make_id' => $make->id]))->assertOk()->assertJsonFragment(['name' => 'Vios']);

    $this->put(route('admin.vehicle-models.update', $model), ['vehicle_make_id' => $make->id, 'name' => 'Vios', 'variants' => 'XLE, G'])->assertRedirect();
    expect($model->fresh()->variants)->toBe(['XLE', 'G']);

    Vehicle::factory()->create(['vehicle_type' => 'car', 'vehicle_make_id' => $make->id, 'vehicle_model_id' => $model->id]);
    $motorcycleMake = VehicleMake::factory()->create(['vehicle_type' => 'motorcycle']);
    $this->put(route('admin.vehicle-models.update', $model), ['vehicle_make_id' => $motorcycleMake->id, 'name' => 'Vios'])
        ->assertSessionHasErrors('vehicle_make_id');
});
