<?php

use App\Models\Invoice;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows shop-owned payment details only to the invoiced customer', function () {
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::factory()->create(['owner_id' => $owner->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $customer = User::factory()->create(['role' => 'customer']);
    Invoice::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'invoice_number' => 'INV-PAYMENT-DETAILS', 'subtotal' => 1200, 'total' => 1200]);

    $this->actingAs($owner)->put(route('shop.profile.update'), [
        'name' => $shop->name,
        'phone' => $shop->phone,
        'email' => $shop->email,
        'address' => $shop->address,
        'barangay_id' => $shop->barangay_id,
        'gcash_number' => '09171234567',
        'bank_transfer_details' => "Sample Bank\nAccount: AutoCare\nNo. 123456",
    ])->assertRedirect();

    $this->assertDatabaseHas('shops', ['id' => $shop->id, 'gcash_number' => '09171234567']);
    $this->actingAs($customer)->get(route('customer.invoices.index'))
        ->assertOk()->assertSee('09171234567')->assertSee('Sample Bank')->assertSee('I paid the shop');
    $this->actingAs(User::factory()->create(['role' => 'customer']))->get(route('customer.invoices.index'))
        ->assertOk()->assertDontSee('09171234567')->assertDontSee('Sample Bank');
});

it('rejects payment instructions that exceed the field limits', function () {
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner)->put(route('shop.profile.update'), [
        'name' => $shop->name,
        'phone' => $shop->phone,
        'email' => $shop->email,
        'address' => $shop->address,
        'barangay_id' => $shop->barangay_id,
        'gcash_number' => str_repeat('9', 31),
    ])->assertSessionHasErrors('gcash_number');

    expect($shop->fresh()->gcash_number)->toBeNull();
});
