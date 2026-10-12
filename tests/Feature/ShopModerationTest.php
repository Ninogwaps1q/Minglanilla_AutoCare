<?php

use App\Models\Barangay;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('suspends a verified shop without removing its records and can reactivate it', function () {
    $shop = shopModerationFixture();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->patch(route('admin.shops.status', $shop), ['shop_status' => 'suspended', 'reason' => 'Verification follow-up required.'])->assertRedirect();

    $this->assertDatabaseHas('shops', ['id' => $shop->id, 'verification_status' => 'approved', 'shop_status' => 'suspended']);
    $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'updated_shop_status']);
    $this->assertDatabaseHas('notifications', ['user_id' => $shop->owner_id, 'message' => 'Shop is now suspended. Reason: Verification follow-up required.']);
    $this->get(route('shops.show', $shop))->assertNotFound();
    $this->get(route('shops.index'))->assertDontSee($shop->name);

    $this->patch(route('admin.shops.status', $shop), ['shop_status' => 'active'])->assertRedirect();
    $this->assertDatabaseHas('shops', ['id' => $shop->id, 'shop_status' => 'active']);
    $this->get(route('shops.show', $shop))->assertOk();
});

it('requires a suspension reason and rejects activation of unverified shops', function () {
    $shop = shopModerationFixture();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->patch(route('admin.shops.status', $shop), ['shop_status' => 'suspended'])->assertSessionHasErrors('reason');
    $shop->update(['verification_status' => 'pending', 'shop_status' => 'inactive']);
    $this->patch(route('admin.shops.status', $shop), ['shop_status' => 'active'])->assertSessionHasErrors('shop_status');
    $this->assertDatabaseHas('shops', ['id' => $shop->id, 'shop_status' => 'inactive']);
    $this->assertDatabaseCount('notifications', 0);
    $this->assertDatabaseCount('activity_logs', 0);
});

it('notifies and logs a verification decision once and does not bypass suspension by replaying approval', function () {
    $shop = shopModerationFixture();
    $shop->update(['verification_status' => 'pending', 'shop_status' => 'inactive']);
    $admin = User::factory()->create(['role' => 'admin']);
    $shop->documents()->createMany([
        ['document_type' => 'business_permit', 'document_path' => 'documents/business.pdf', 'verification_status' => 'verified'],
        ['document_type' => 'mayors_permit', 'document_path' => 'documents/mayor.pdf', 'verification_status' => 'verified'],
    ]);

    $this->actingAs($admin)->patch(route('admin.shops.approve', $shop))->assertRedirect();
    $this->patch(route('admin.shops.approve', $shop))->assertRedirect();
    $this->assertDatabaseCount('notifications', 1);
    $this->assertDatabaseCount('activity_logs', 1);
    $shop->update(['shop_status' => 'suspended']);
    $this->patch(route('admin.shops.approve', $shop))->assertRedirect();
    $this->assertDatabaseHas('shops', ['id' => $shop->id, 'shop_status' => 'suspended']);
});

it('allows an administrator to approve after reviewing incomplete documents', function () {
    $shop = shopModerationFixture();
    $shop->update(['verification_status' => 'pending', 'shop_status' => 'inactive']);
    $shop->documents()->create(['document_type' => 'business_permit', 'document_path' => 'documents/expired.pdf', 'verification_status' => 'verified', 'expires_at' => today()->subDay()]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.shops.approve', $shop))->assertRedirect();

    $this->assertDatabaseHas('shops', ['id' => $shop->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $this->assertDatabaseCount('activity_logs', 1);
    $this->assertDatabaseCount('notifications', 1);
});

it('denies shop moderation to non administrators', function (string $role) {
    $shop = shopModerationFixture();
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->patch(route('admin.shops.status', $shop), ['shop_status' => 'suspended', 'reason' => 'Unauthorized'])->assertForbidden();
    $this->patch(route('admin.shops.approve', $shop))->assertForbidden();
    $this->patch(route('admin.shops.reject', $shop))->assertForbidden();
    $this->assertDatabaseHas('shops', ['id' => $shop->id, 'shop_status' => 'active']);
    $this->assertDatabaseCount('notifications', 0);
})->with(['customer', 'shop_owner']);

it('records a rejected registration and informs its owner', function () {
    $shop = shopModerationFixture();
    $shop->update(['verification_status' => 'pending', 'shop_status' => 'inactive']);

    $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.shops.reject', $shop))->assertRedirect();

    $this->assertDatabaseHas('shops', ['id' => $shop->id, 'verification_status' => 'rejected', 'shop_status' => 'inactive']);
    $this->assertDatabaseHas('notifications', ['user_id' => $shop->owner_id, 'message' => 'Shop registration rejected.']);
    $this->assertDatabaseCount('activity_logs', 1);
});

it('blocks direct rejection of an approved shop so its history remains approved while suspended', function () {
    $shop = shopModerationFixture();

    $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.shops.reject', $shop))->assertSessionHasErrors('shop');

    $this->assertDatabaseHas('shops', ['id' => $shop->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $this->assertDatabaseCount('notifications', 0);
});

function shopModerationFixture(): Shop
{
    return Shop::factory()->create([
        'owner_id' => User::factory()->create(['role' => 'shop_owner'])->id,
        'barangay_id' => Barangay::factory()->create()->id, 'name' => 'Moderated AutoCare',
        'phone' => '09170000000', 'email' => 'moderated@example.test', 'address' => 'Minglanilla',
        'verification_status' => 'approved', 'shop_status' => 'active',
    ]);
}
