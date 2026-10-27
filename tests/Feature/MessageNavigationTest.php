<?php

use App\Models\MessageThread;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

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

it('shows the shop logo and shop identity throughout the customer inbox and conversation', function () {
    Storage::fake('public');
    Storage::disk('public')->put('shop-logos/message-shop.png', 'image bytes');
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner', 'name' => 'Private Owner Name']);
    $shop = Shop::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Clear Auto Shop',
        'logo' => 'shop-logos/message-shop.png',
        'verification_status' => 'approved',
        'shop_status' => 'active',
    ]);
    $thread = MessageThread::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);
    $thread->messages()->create(['sender_id' => $owner->id, 'message' => 'Your car is ready.']);

    $this->actingAs($customer)->get(route('customer.messages.index'))
        ->assertOk()->assertSee('Clear Auto Shop logo')->assertSee(url('media/shop-logos/message-shop.png'));
    $this->get(route('customer.messages.show', $shop))
        ->assertOk()->assertSee('Clear Auto Shop logo')->assertSee('Your car is ready.')
        ->assertDontSee('Private Owner Name');
});

it('shows the customer profile photo and name throughout the shop inbox and conversation', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/message-customer.png', 'image bytes');
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $customer = User::factory()->create([
        'role' => 'customer',
        'name' => 'Mara Customer',
        'profile_photo' => 'profile-photos/message-customer.png',
    ]);
    $shop = Shop::factory()->create(['owner_id' => $owner->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $thread = MessageThread::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);
    $thread->messages()->create(['sender_id' => $customer->id, 'message' => 'Is my repair done?']);

    $this->actingAs($owner)->get(route('shop.messages.index'))
        ->assertOk()->assertSee('Mara Customer profile photo')->assertSee(url('media/profile-photos/message-customer.png'));
    $this->get(route('shop.messages.show', $thread))
        ->assertOk()->assertSee('Mara Customer profile photo')->assertSee('Is my repair done?');
});

it('sends and polls messages in JSON only for conversation participants', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::factory()->create(['owner_id' => $owner->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $thread = MessageThread::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);

    $this->actingAs($otherCustomer)->postJson(route('customer.messages.store', $thread), ['message' => 'Unauthorized'])->assertNotFound();
    $this->actingAs($customer)->postJson(route('customer.messages.store', $thread), ['message' => 'Please check my brakes'])
        ->assertCreated()->assertJsonPath('message.mine', true)->assertJsonPath('message.body', 'Please check my brakes');
    $this->actingAs($owner)->postJson(route('shop.messages.updates', $thread), ['after_id' => 0])
        ->assertOk()->assertJsonPath('messages.0.body', 'Please check my brakes')->assertJsonPath('messages.0.mine', false);
    expect($thread->messages()->firstOrFail()->read_at)->not->toBeNull();
    $this->actingAs($otherCustomer)->postJson(route('customer.messages.updates', $thread), ['after_id' => 0])->assertNotFound();
});

it('loads only the latest chat messages first and lets participants retrieve older messages', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::factory()->create(['owner_id' => $owner->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $thread = MessageThread::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);
    foreach (range(1, 55) as $number) {
        $thread->messages()->create(['sender_id' => $owner->id, 'message' => 'History message '.$number]);
    }
    $oldest = $thread->messages()->oldest('id')->firstOrFail();
    $sixth = $thread->messages()->oldest('id')->skip(5)->firstOrFail();

    $this->actingAs($customer)->get(route('customer.messages.show', $shop))
        ->assertOk()->assertSee('Load earlier messages')->assertSee('History message 55')
        ->assertViewHas('thread', fn (MessageThread $loaded): bool => $loaded->messages->count() === 50 && $loaded->messages->first()->message === 'History message 6');
    $this->postJson(route('customer.messages.updates', $thread), ['before_id' => $sixth->id])
        ->assertOk()->assertJsonPath('messages.0.id', $oldest->id)
        ->assertJsonPath('messages.4.body', 'History message 5')->assertJsonPath('has_more', false);
    $this->actingAs(User::factory()->create(['role' => 'customer']))
        ->postJson(route('customer.messages.updates', $thread), ['before_id' => $sixth->id])->assertNotFound();
});
