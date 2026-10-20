<?php

use App\Models\Barangay;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps old map links working and shows only approved active shops in the combined directory', function () {
    $barangay = Barangay::factory()->create();
    $owner = User::factory()->create(['role' => 'shop_owner']);

    Shop::query()->create(['owner_id' => $owner->id, 'name' => 'Approved Demo Shop', 'phone' => '09171234567', 'email' => 'approved@example.test', 'address' => 'Demo Street', 'barangay_id' => $barangay->id, 'latitude' => 10.24, 'longitude' => 123.78, 'verification_status' => 'approved', 'shop_status' => 'active']);
    Shop::query()->create(['owner_id' => $owner->id, 'name' => 'Pending Demo Shop', 'phone' => '09171234568', 'email' => 'pending@example.test', 'address' => 'Demo Street', 'barangay_id' => $barangay->id, 'latitude' => 10.25, 'longitude' => 123.79, 'verification_status' => 'pending', 'shop_status' => 'inactive']);

    $this->get(route('map.index'))->assertRedirect(route('shops.index').'#shop-map');

    $this->get(route('shops.index'))
        ->assertSee('Matching shops on the map')
        ->assertSee('Approved Demo Shop', false)
        ->assertDontSee('Pending Demo Shop', false);
});

it('preserves filters and distance sorting when opening an old map link', function () {
    $barangay = Barangay::factory()->create();

    $this->get(route('map.index', ['barangay' => $barangay->id, 'latitude' => 10.2443, 'longitude' => 123.7898]))
        ->assertRedirectContains('/shops?')
        ->assertRedirectContains('barangay='.$barangay->id)
        ->assertRedirectContains('sort=distance')
        ->assertRedirectContains('#shop-map');
});
