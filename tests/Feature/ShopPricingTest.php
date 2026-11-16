<?php

use App\Models\Shop;
use App\Models\ShopPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows example plans and the free listing promise publicly', function () {
    $this->get(route('shop.pricing'))->assertOk()
        ->assertSee('₱499')->assertSee('₱999')
        ->assertSee('Shop name and services on the map')
        ->assertSee('30-day trial');
});

it('lets an admin edit prices and features but denies other users', function () {
    $plan = ShopPlan::query()->where('slug', 'basic')->firstOrFail();
    $payload = ['monthly_price' => 650, 'summary' => 'More visibility for local shops.', 'features' => ['Shop listing', 'Priority placement', 'Shop workspace']];
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $this->actingAs($owner)->put(route('admin.shop-plans.update', $plan), $payload)->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->put(route('admin.shop-plans.update', $plan), $payload)->assertRedirect();
    $this->get(route('admin.shop-plans.index'))->assertOk()->assertSee('More visibility for local shops.');
    $this->get(route('shop.pricing'))->assertSee('₱650')->assertSee('More visibility for local shops.');
});

it('starts a premium trial only once and returns to free after 30 days', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'free', 'plan_expires_at' => null]);
    $this->actingAs($shop->owner)->post(route('shop.pricing.trial'))->assertRedirect();
    expect($shop->fresh()->activePlanSlug())->toBe('premium');
    $this->post(route('shop.pricing.trial'))->assertSessionHasErrors('trial');

    $this->travel(31)->days();
    expect($shop->fresh()->activePlanSlug())->toBe('free');
});

it('does not start a trial before shop approval', function () {
    $shop = Shop::factory()->create();
    $this->actingAs($shop->owner)->post(route('shop.pricing.trial'))->assertSessionHasErrors('trial');
    $basic = ShopPlan::query()->where('slug', 'basic')->firstOrFail();
    $this->post(route('shop.pricing.request', $basic))->assertSessionHasErrors('plan');
    expect($shop->fresh()->trial_started_at)->toBeNull();
    expect($shop->fresh()->requested_plan)->toBeNull();
});

it('places active paid shops above free shops and keeps every approved shop visible', function () {
    Shop::factory()->create(['name' => 'A Free Shop', 'verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'free', 'plan_expires_at' => null]);
    Shop::factory()->create(['name' => 'B Basic Shop', 'verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'basic', 'plan_expires_at' => now()->addMonth()]);
    Shop::factory()->create(['name' => 'C Premium Shop', 'verification_status' => 'approved', 'shop_status' => 'active', 'trial_started_at' => now()]);

    $this->get(route('shops.index'))->assertOk()->assertSeeInOrder(['C Premium Shop', 'B Basic Shop', 'A Free Shop']);
});

it('lets an owner request a paid plan and an admin activate it', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active']);
    $basic = ShopPlan::query()->where('slug', 'basic')->firstOrFail();
    $this->actingAs($shop->owner)->post(route('shop.pricing.request', $basic))->assertRedirect();
    expect($shop->fresh()->requested_plan)->toBe('basic');

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.shops.plan', $shop), ['plan_slug' => 'basic', 'plan_expires_at' => now()->addMonth()->toDateString()])
        ->assertRedirect();
    expect($shop->fresh()->activePlanSlug())->toBe('basic');
    expect($shop->fresh()->requested_plan)->toBeNull();
});

it('expires paid placement and prevents owners activating their own plan', function () {
    $shop = Shop::factory()->create(['plan_slug' => 'premium', 'plan_expires_at' => now()->subDay()]);
    expect($shop->activePlanSlug())->toBe('free');
    $this->actingAs($shop->owner)->patch(route('admin.shops.plan', $shop), ['plan_slug' => 'premium', 'plan_expires_at' => now()->addMonth()->toDateString()])->assertForbidden();
    expect($shop->fresh()->activePlanSlug())->toBe('free');
});
