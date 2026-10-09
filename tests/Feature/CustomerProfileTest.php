<?php

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates the authenticated customer profile', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $barangay = Barangay::factory()->create(['status' => 'active']);

    $response = $this->actingAs($customer)->put(route('customer.profile.update'), [
        'name' => 'Jamie Cruz',
        'phone' => '09171234567',
        'address' => 'Poblacion, Minglanilla',
        'barangay_id' => $barangay->id,
    ]);

    $response->assertRedirect(route('customer.profile.edit'));
    $this->assertDatabaseHas('users', [
        'id' => $customer->id,
        'name' => 'Jamie Cruz',
        'phone' => '09171234567',
        'barangay_id' => $barangay->id,
    ]);
});

it('forbids a shop owner from updating a customer profile', function () {
    $shopOwner = User::factory()->create(['role' => 'shop_owner']);

    $this->actingAs($shopOwner)
        ->put(route('customer.profile.update'), ['name' => 'Unauthorised update'])
        ->assertForbidden();
});
