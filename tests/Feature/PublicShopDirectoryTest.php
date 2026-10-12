<?php

use App\Models\Barangay;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Shop;
use App\Models\ShopService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the branded public footer with useful navigation', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Trusted local vehicle care, made easier.')
        ->assertSee('Shop directory')
        ->assertSee('Create an account');
});

it('shows only approved active shops in the directory', function () {
    $barangay = Barangay::factory()->create();
    $owner = User::factory()->create(['role' => 'shop_owner']);
    Shop::query()->create(['owner_id' => $owner->id, 'name' => 'Directory Shop', 'phone' => '09170000000', 'email' => 'directory@example.test', 'address' => 'Demo Street', 'barangay_id' => $barangay->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    Shop::query()->create(['owner_id' => $owner->id, 'name' => 'Hidden Shop', 'phone' => '09170000001', 'email' => 'hidden@example.test', 'address' => 'Demo Street', 'barangay_id' => $barangay->id, 'verification_status' => 'pending', 'shop_status' => 'inactive']);

    $this->get(route('shops.index'))->assertSee('Directory Shop')->assertDontSee('Hidden Shop');
});

it('filters the directory by an available service', function () {
    $barangay = Barangay::factory()->create();
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $category = ServiceCategory::query()->create(['name' => 'Maintenance']);
    $service = Service::query()->create(['category_id' => $category->id, 'name' => 'Oil Change']);
    $matchingShop = Shop::query()->create(['owner_id' => $owner->id, 'name' => 'Oil Change Shop', 'phone' => '09170000000', 'email' => 'oil@example.test', 'address' => 'Demo Street', 'barangay_id' => $barangay->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    Shop::query()->create(['owner_id' => $owner->id, 'name' => 'No Oil Change Shop', 'phone' => '09170000001', 'email' => 'other@example.test', 'address' => 'Demo Street', 'barangay_id' => $barangay->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    ShopService::query()->create(['shop_id' => $matchingShop->id, 'service_id' => $service->id, 'price' => 800, 'availability' => true]);

    $this->get(route('shops.index', ['service' => $service]))
        ->assertSee('Oil Change Shop')
        ->assertDontSee('No Oil Change Shop');
});

it('does not expose non-public shop profiles', function () {
    $barangay = Barangay::factory()->create();
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::query()->create(['owner_id' => $owner->id, 'name' => 'Pending Shop', 'phone' => '09170000000', 'email' => 'pending@example.test', 'address' => 'Demo Street', 'barangay_id' => $barangay->id, 'verification_status' => 'pending', 'shop_status' => 'inactive']);

    $this->get(route('shops.show', $shop))->assertNotFound();
});

it('compares offers from approved active shops only', function () {
    $barangay = Barangay::factory()->create();
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $category = ServiceCategory::query()->create(['name' => 'Maintenance']);
    $service = Service::query()->create(['category_id' => $category->id, 'name' => 'Wheel Alignment']);
    $publicShop = Shop::query()->create(['owner_id' => $owner->id, 'name' => 'Public Alignment Shop', 'phone' => '09170000000', 'email' => 'public@example.test', 'address' => 'Demo Street', 'barangay_id' => $barangay->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $hiddenShop = Shop::query()->create(['owner_id' => $owner->id, 'name' => 'Hidden Alignment Shop', 'phone' => '09170000001', 'email' => 'hidden@example.test', 'address' => 'Demo Street', 'barangay_id' => $barangay->id, 'verification_status' => 'pending', 'shop_status' => 'inactive']);
    ShopService::query()->create(['shop_id' => $publicShop->id, 'service_id' => $service->id, 'price' => 500, 'availability' => true]);
    ShopService::query()->create(['shop_id' => $hiddenShop->id, 'service_id' => $service->id, 'price' => 300, 'availability' => true]);

    $this->get(route('services.compare', $service))
        ->assertSee('Public Alignment Shop')
        ->assertDontSee('Hidden Alignment Shop');
});
