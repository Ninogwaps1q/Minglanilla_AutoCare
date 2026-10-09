<?php

use App\Models\Barangay;
use App\Models\MessageThread;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('escapes untrusted customer message content when rendering a conversation', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::query()->create(['owner_id' => $owner->id, 'barangay_id' => Barangay::factory()->create()->id, 'name' => 'Secure Shop', 'phone' => '09170000000', 'email' => 'secure@example.test', 'address' => 'Demo Street', 'verification_status' => 'approved', 'shop_status' => 'active']);
    $thread = MessageThread::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);
    $message = '<script>alert("xss")</script>';
    $thread->messages()->create(['sender_id' => $customer->id, 'message' => $message]);

    $this->actingAs($customer)->get(route('customer.messages.show', $shop))
        ->assertOk()
        ->assertSee($message)
        ->assertDontSee($message, false);
});
