<?php

use App\Models\Appointment;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function nationwideBarangay(array $address, string $status = 'active'): Barangay
{
    [$regionName, $regionCode, $provinceName, $provinceCode, $cityName, $cityCode, $barangayName, $barangayCode] = $address;
    $region = Region::factory()->create(['name' => $regionName, 'psgc_code' => $regionCode]);
    $province = Province::factory()->create(['region_id' => $region->id, 'name' => $provinceName, 'psgc_code' => $provinceCode]);
    $city = Municipality::factory()->create(['province_id' => $province->id, 'name' => $cityName, 'psgc_code' => $cityCode]);

    return Barangay::factory()->create(['municipality_id' => $city->id, 'name' => $barangayName, 'psgc_code' => $barangayCode, 'status' => $status]);
}

it('registers and verifies a provider outside Cebu and takes a booking from another province', function (string $role, array $area, float $latitude, float $longitude) {
    $pickup = nationwideBarangay($area, 'inactive');
    $city = $pickup->municipality;
    $province = $city->province;
    $customerArea = nationwideBarangay(['CALABARZON', '0400000000', 'Laguna', '0403400000', 'City of Calamba', '0403405000', 'Banlic', '0403405003'], 'inactive');
    $owner = User::factory()->create(['role' => $role]);
    $service = Service::factory()->create(['default_price' => 750, 'estimated_duration' => 45]);
    $provider = 'Island Care Garage';
    $payload = [
        'name' => $provider, 'phone' => '09170000001', 'email' => 'provider@example.test',
        'barangay_id' => $pickup->id, 'address' => '18 Service Road', 'landmark' => 'Across the terminal',
        'latitude' => $latitude, 'longitude' => $longitude, 'service_ids' => [$service->id],
    ];

    $this->getJson(route('catalog.barangays', ['municipality_id' => $city->id]))->assertJsonFragment(['id' => $pickup->id]);
    $this->actingAs($owner)->post(route('shop.register.store'), $payload)->assertSessionHasErrors('legal_consent');
    $this->post(route('shop.register.store'), $payload + ['legal_consent' => 1])->assertSessionHasNoErrors()->assertRedirect();
    expect($owner->fresh()->legal_accepted_at)->not->toBeNull();
    $shop = $owner->shops()->sole();
    expect($shop->barangay_id)->toBe($pickup->id);
    $this->get(route('shop.profile.edit'))->assertOk()->assertSee('data-initial-barangay="'.$pickup->id.'"', false);

    foreach ($shop->permitChecklist() as $permit) {
        $shop->documents()->create(['document_type' => $permit['type'], 'document_path' => 'private/permit.pdf', 'verification_status' => 'verified']);
    }
    $admin = User::factory()->create(['role' => 'admin']);
    $filters = ['region_id' => $province->region_id, 'province_id' => $province->id, 'municipality_id' => $city->id];
    $this->actingAs($admin)->get(route('admin.shops.index', $filters))->assertOk()->assertSee($provider)->assertSee($province->name)->assertSee($city->name);
    $this->get(route('admin.shops.index', ['province_id' => $customerArea->municipality->province_id]))->assertOk()->assertDontSee($provider);
    $this->patch(route('admin.shops.approve', $shop))->assertSessionHasNoErrors()->assertRedirect();
    expect($shop->refresh()->verification_status)->toBe('approved');

    $shop->update(['plan_slug' => 'basic', 'plan_expires_at' => now()->addMonth()]);
    $this->get(route('shops.index', ['search' => $province->name, ...$filters]))->assertOk()
        ->assertSee($provider)
        ->assertViewHas('mapShops', fn ($pins): bool => $pins->pluck('id')->all() === [$shop->id]);
    $this->getJson('/api/shops?'.http_build_query(['search' => $city->name, ...$filters]))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $shop->id)
        ->assertJsonPath('data.0.longitude', round($longitude, $role === 'mechanic' ? 2 : 7));
    $this->getJson('/api/shops/nearby?'.http_build_query([...$filters, 'latitude' => $latitude, 'longitude' => $longitude]))
        ->assertOk()->assertJsonPath('data.0.id', $shop->id);
    $this->getJson('/api/shops?province_id='.$customerArea->municipality->province_id)->assertOk()->assertJsonCount(0, 'data');

    $customer = User::factory()->create(['role' => 'customer']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $this->actingAs($customer)->put(route('customer.profile.update'), ['name' => $customer->name, 'barangay_id' => $customerArea->id, 'address' => '12 Market Street'])
        ->assertSessionHasNoErrors()->assertRedirect();
    $this->post(route('customer.appointments.store'), [
        'shop_id' => $shop->id, 'vehicle_id' => $vehicle->id, 'service_ids' => [$service->id],
        'appointment_date' => now()->addDay()->toDateString(), 'appointment_time' => '10:00',
        ...($role === 'mechanic' ? ['service_barangay_id' => $pickup->id, 'service_street' => '24 Pickup Road'] : []),
    ])->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseHas('appointments', ['shop_id' => $shop->id, 'customer_id' => $customer->id]);

    if ($role === 'mechanic') {
        expect(Appointment::query()->sole()->service_barangay_id)->toBe($pickup->id);
    }
})->with([
    'Digos shop' => ['shop_owner', ['Davao Region', '1100000000', 'Davao del Sur', '1102400000', 'City of Digos', '1102403000', 'Aplaya', '1102403001'], 6.75, 125.35],
    'Kalayaan mechanic' => ['mechanic', ['MIMAROPA Region', '1700000000', 'Palawan', '1705300000', 'Kalayaan', '1705321000', 'Pag-Asa', '1705321004'], 11.0521, 114.2821],
]);

it('filters a paginated admin barangay list by location and name', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $barangay = Barangay::factory()->create(['name' => 'Alpha Junction']);
    $city = $barangay->municipality;
    $province = $city->province;
    Barangay::factory()->count(26)->create(['municipality_id' => $city->id]);
    $other = Barangay::factory()->create(['name' => 'Alpha Vista']);

    $filters = ['region_id' => $province->region_id, 'province_id' => $province->id, 'municipality_id' => $city->id];
    $this->actingAs($admin)->get(route('barangays.index', $filters))->assertOk()
        ->assertViewHas('barangays', fn ($results): bool => $results->total() === 27 && $results->count() === 25);
    $this->get(route('barangays.index', [...$filters, 'search' => 'Alpha']))
        ->assertOk()->assertSee($barangay->name)->assertDontSee($other->name);
});

it('dispatches by GPS while sharing the selected nationwide pickup address with the assigned provider', function () {
    $pickup = nationwideBarangay(['Davao Region', '1100000000', 'Davao del Sur', '1102400000', 'City of Digos', '1102403000', 'Aplaya', '1102403001']);
    $provider = Shop::factory()->create([
        'barangay_id' => $pickup->id, 'verification_status' => 'approved', 'shop_status' => 'active',
        'roadside_available' => true, 'roadside_radius_km' => 10, 'latitude' => 6.75, 'longitude' => 125.35,
    ]);
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)->post(route('customer.emergency-requests.store'), [
        'emergency_type' => 'breakdown', 'latitude' => 6.751, 'longitude' => 125.351,
        'barangay_id' => $pickup->id, 'address' => '9 Market Road', 'landmark' => 'Beside the bridge',
    ])->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseHas('emergency_requests', ['customer_id' => $customer->id, 'assigned_shop_id' => $provider->id, 'barangay_id' => $pickup->id, 'landmark' => 'Beside the bridge']);
    $this->actingAs($provider->owner)->get(route('shop.emergency-requests.index'))->assertOk()
        ->assertSee('9 Market Road, Aplaya, City of Digos, Davao del Sur, Davao Region, Beside the bridge');
});
