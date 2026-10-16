<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows a customers exact unread count and reduces it after reading an update', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    foreach (range(1, 10) as $number) {
        $customer->notifications()->create(['type' => 'status', 'title' => "Update {$number}", 'message' => 'Your service changed.']);
    }
    $customer->notifications()->create(['type' => 'status', 'title' => 'Older update', 'message' => 'Already read.', 'read_at' => now()]);
    $otherCustomer->notifications()->create(['type' => 'status', 'title' => 'Someone else', 'message' => 'Private.']);

    $this->actingAs($customer)->get(route('customer.notifications.index'))
        ->assertSee('Notifications, 10 unread')
        ->assertSee('10 unread')
        ->assertSee('Unread');

    $notification = $customer->notifications()->whereNull('read_at')->firstOrFail();
    $this->patch(route('customer.notifications.read', $notification))->assertRedirect();

    $this->get(route('dashboard'))->assertSee('Notifications, 9 unread');
    $this->get(route('customer.notifications.index'))->assertSee('9 unread')->assertDontSee('Someone else');
});

it('shows the shop owner an unread notification count in the shared navigation', function () {
    $owner = User::factory()->create(['role' => 'shop_owner']);
    Shop::factory()->create(['owner_id' => $owner->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $notification = $owner->notifications()->create(['type' => 'status', 'title' => 'Booking update', 'message' => 'New booking.']);

    $this->actingAs($owner)->get(route('shop.notifications.index'))
        ->assertSee('Notifications, 1 unread')
        ->assertSee('1 unread')
        ->assertSee('Booking update');

    $this->patch(route('shop.notifications.update', $notification))->assertRedirect();

    $this->get(route('shop.dashboard'))->assertSee('Notifications, 0 unread');
});

it('keeps the precise accessible count when the visible badge reaches 99 plus', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $customer->notifications()->createMany(array_fill(0, 101, [
        'type' => 'status', 'title' => 'Update', 'message' => 'New update.',
    ]));

    $this->actingAs($customer)->get(route('customer.notifications.index'))
        ->assertSee('Notifications, 101 unread')
        ->assertSee('99+')
        ->assertSee('101 unread');
});
