<?php

use App\Models\Barangay;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('guides a shop owner without a registered shop through setup', function () {
    $owner = User::factory()->create(['role' => 'shop_owner']);

    $this->actingAs($owner)
        ->get(route('shop.dashboard'))
        ->assertOk()
        ->assertSee('Set up your service shop')
        ->assertSee('Register my shop');
});

it('presents an approved shop owner with a task focused workspace', function () {
    $owner = User::factory()->create(['role' => 'shop_owner']);
    Shop::factory()->create([
        'owner_id' => $owner->id,
        'barangay_id' => Barangay::factory()->create()->id,
        'name' => 'Easy Service Garage',
        'phone' => '09170000000',
        'email' => 'garage@example.test',
        'address' => 'Minglanilla',
        'verification_status' => 'approved',
        'shop_status' => 'active',
    ]);

    $this->actingAs($owner)
        ->get(route('shop.dashboard'))
        ->assertOk()
        ->assertSee('Easy Service Garage')
        ->assertSee('Today at a glance')
        ->assertSee('Review appointments')
        ->assertSee('View repair jobs')
        ->assertSee('Quick actions');
});

it('points an unverified shop owner to verification details', function () {
    $owner = User::factory()->create(['role' => 'shop_owner']);
    Shop::factory()->create([
        'owner_id' => $owner->id,
        'barangay_id' => Barangay::factory()->create()->id,
        'verification_status' => 'pending',
        'shop_status' => 'inactive',
    ]);

    $this->actingAs($owner)->get(route('shop.dashboard'))
        ->assertOk()
        ->assertSee('Your shop is not approved yet')
        ->assertSee('Check verification details');
});

it('presents administrators with platform priorities and clear actions', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    Shop::factory()->create([
        'owner_id' => $owner->id,
        'barangay_id' => Barangay::factory()->create()->id,
        'name' => 'Pending Garage',
        'phone' => '09170000001',
        'email' => 'pending@example.test',
        'address' => 'Minglanilla',
        'verification_status' => 'pending',
        'shop_status' => 'inactive',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('System overview')
        ->assertSee('Pending Garage')
        ->assertSee('Shop registrations to review')
        ->assertSee('Operations requiring attention')
        ->assertSee('Review 1 pending shop');
});

it('points administrators to operations when there are no shop registrations to review', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Open operations monitor');
});
