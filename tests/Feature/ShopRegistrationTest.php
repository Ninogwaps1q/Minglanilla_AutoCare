<?php

use App\Models\Barangay;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('adds selected services when a shop owner registers a shop', function () {
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $barangay = Barangay::factory()->create(['status' => 'active']);
    $category = ServiceCategory::query()->create(['name' => 'Maintenance']);
    $oilChange = Service::query()->create(['category_id' => $category->id, 'name' => 'Oil Change', 'default_price' => 950]);
    $wheelAlignment = Service::query()->create(['category_id' => $category->id, 'name' => 'Wheel Alignment', 'default_price' => 700]);

    $response = $this->actingAs($owner)->post(route('shop.register.store'), [
        'name' => 'Registration Service Shop',
        'phone' => '09170000000',
        'email' => 'registration@example.test',
        'address' => 'Demo Street',
        'barangay_id' => $barangay->id,
        'latitude' => 10.25,
        'longitude' => 123.79,
        'service_ids' => [$oilChange->id, $wheelAlignment->id],
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertDatabaseHas('shop_services', ['service_id' => $oilChange->id, 'price' => 950, 'availability' => true]);
    $this->assertDatabaseHas('shop_services', ['service_id' => $wheelAlignment->id, 'price' => 700, 'availability' => true]);
});

it('rejects service ids that are not in the catalog', function () {
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $barangay = Barangay::factory()->create(['status' => 'active']);

    $response = $this->actingAs($owner)->from(route('shop.register'))
        ->post(route('shop.register.store'), [
            'name' => 'Invalid Service Shop',
            'phone' => '09170000000',
            'email' => 'invalid-service@example.test',
            'address' => 'Demo Street',
            'barangay_id' => $barangay->id,
            'latitude' => 10.25,
            'longitude' => 123.79,
            'service_ids' => [9999],
        ]);

    $response->assertRedirect(route('shop.register'));
    $response->assertSessionHasErrors('service_ids.0');
});
