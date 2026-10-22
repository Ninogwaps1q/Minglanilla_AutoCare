<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('keeps the customer dashboard usable when message storage is unavailable', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    Schema::drop('message_threads');

    $this->actingAs($customer)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Messages are temporarily unavailable');

    $this->get(route('customer.messages.index'))
        ->assertStatus(503)
        ->assertSee('Messages are temporarily unavailable');
});

it('keeps the shop dashboard usable when message storage is unavailable', function () {
    $owner = User::factory()->create(['role' => 'shop_owner']);
    Shop::factory()->create(['owner_id' => $owner->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    Schema::drop('message_threads');

    $this->actingAs($owner)->get(route('shop.dashboard'))
        ->assertOk()
        ->assertSee('Messages are temporarily unavailable');

    $this->get(route('shop.messages.index'))
        ->assertStatus(503)
        ->assertSee('Messages are temporarily unavailable');
});
