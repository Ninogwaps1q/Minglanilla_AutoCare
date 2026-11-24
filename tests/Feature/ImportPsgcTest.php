<?php

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports the full PSA catalog and preserves existing address IDs on reimport', function () {
    $region = Region::factory()->create(['name' => 'Central Visayas', 'psgc_code' => '070000000']);
    $province = Province::factory()->create(['name' => 'Cebu', 'region_id' => $region->id, 'psgc_code' => '072200000']);
    $municipality = Municipality::factory()->create(['name' => 'Minglanilla', 'province_id' => $province->id, 'psgc_code' => '072232000']);
    $barangay = Barangay::factory()->create(['name' => 'Poblacion Ward I', 'municipality_id' => $municipality->id, 'psgc_code' => '072232010']);
    $customer = User::factory()->create(['barangay_id' => $barangay->id]);

    $this->artisan('catalog:import-psgc')->assertSuccessful();
    $this->artisan('catalog:import-psgc')->assertSuccessful();

    expect(Region::query()->count())->toBe(18)
        ->and(Province::query()->whereNotNull('psgc_code')->where('psgc_code', '!=', '1999900000')->count())->toBe(82)
        ->and(Municipality::query()->count())->toBe(1642)
        ->and(Barangay::query()->count())->toBe(42010)
        ->and($customer->fresh()->barangay_id)->toBe($barangay->id)
        ->and($barangay->fresh()->psgc_code)->toBe('0702232010');

    $this->assertDatabaseHas('regions', ['psgc_code' => '1800000000']);
    $this->assertDatabaseHas('barangays', ['psgc_code' => '1001322005', 'name' => 'Lambagan']);
    $this->getJson(route('catalog.address-path', ['barangay_id' => $barangay->id]))
        ->assertJsonPath('province_id', $province->id)
        ->assertJsonPath('region_id', $region->id);
});
