<?php

use App\Models\Barangay;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows only approved active shops on the public map', function () {
    $barangay = Barangay::factory()->create();
    $owner = User::factory()->create(['role' => 'shop_owner']);

    Shop::query()->create(['owner_id' => $owner->id, 'name' => 'Approved Demo Shop', 'phone' => '09171234567', 'email' => 'approved@example.test', 'address' => 'Demo Street', 'barangay_id' => $barangay->id, 'latitude' => 10.24, 'longitude' => 123.78, 'verification_status' => 'approved', 'shop_status' => 'active']);
    Shop::query()->create(['owner_id' => $owner->id, 'name' => 'Pending Demo Shop', 'phone' => '09171234568', 'email' => 'pending@example.test', 'address' => 'Demo Street', 'barangay_id' => $barangay->id, 'latitude' => 10.25, 'longitude' => 123.79, 'verification_status' => 'pending', 'shop_status' => 'inactive']);

    $this->get(route('map.index'))
        ->assertSee('const shops =', false)
        ->assertSee('Approved Demo Shop', false)
        ->assertDontSee('Pending Demo Shop', false);
});
