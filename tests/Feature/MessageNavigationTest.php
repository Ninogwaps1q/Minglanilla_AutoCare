<?php

use App\Models\MessageThread;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts only unread incoming messages for the customer and clears them after opening a conversation', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::factory()->create(['owner_id' => $owner->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $thread = MessageThread::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);
    $thread->messages()->createMany([
        ['sender_id' => $owner->id, 'message' => 'Your brake parts arrived.'],
        ['sender_id' => $owner->id, 'message' => 'We can start tomorrow.'],
        ['sender_id' => $customer->id, 'message' => 'Thank you.'],
    ]);
    MessageThread::query()->create(['customer_id' => $otherCustomer->id, 'shop_id' => $shop->id])
        ->messages()->create(['sender_id' => $owner->id, 'message' => 'Private reply.']);

    $this->actingAs($customer)->get(route('customer.messages.index'))
        ->assertOk()
        ->assertSee('2 unread messages')
        ->assertSee('2 unread')
        ->assertDontSee('Private reply');

    $this->get(route('customer.messages.show', $shop))->assertOk();
    $this->get(route('customer.messages.index'))
        ->assertSee('0 unread messages')
        ->assertDontSee('2 unread');
});

it('counts only incoming messages for the shop shown in its workspace', function () {
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    $customer = User::factory()->create(['role' => 'customer']);
    $shop = Shop::factory()->create(['owner_id' => $owner->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $otherShop = Shop::factory()->create(['owner_id' => $otherOwner->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $thread = MessageThread::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);
    $thread->messages()->createMany([
        ['sender_id' => $customer->id, 'message' => 'Is the repair complete?'],
        ['sender_id' => $owner->id, 'message' => 'Almost finished.'],
    ]);
    MessageThread::query()->create(['customer_id' => $customer->id, 'shop_id' => $otherShop->id])
        ->messages()->create(['sender_id' => $customer->id, 'message' => 'Another shop question.']);

    $this->actingAs($owner)->get(route('shop.messages.index'))
        ->assertOk()
        ->assertSee('Messages, 1 unread')
        ->assertSee('1 unread message')
        ->assertDontSee('Another shop question.');

    $this->get(route('shop.messages.show', $thread))->assertOk();
    $this->get(route('shop.messages.index'))->assertSee('Messages, 0 unread')->assertSee('0 unread messages');
});
