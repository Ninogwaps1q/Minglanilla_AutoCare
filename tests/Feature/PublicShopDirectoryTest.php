<?php

use App\Models\Barangay;
use App\Models\RepairJob;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Shop;
use App\Models\ShopService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

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

it('includes the selected public shop logo and location in the map', function () {
    Storage::fake('public');
    Storage::disk('public')->put('shop-logos/atlas.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JQqkAAAAASUVORK5CYII='));

    $shop = Shop::factory()->create([
        'name' => 'Atlas AutoCare',
        'logo' => 'shop-logos/atlas.png',
        'latitude' => 10.2443,
        'longitude' => 123.7898,
        'verification_status' => 'approved',
        'shop_status' => 'active',
    ]);
    Shop::factory()->create([
        'name' => 'Private Garage',
        'logo' => 'shop-logos/private.png',
        'latitude' => 10.25,
        'longitude' => 123.79,
        'verification_status' => 'pending',
        'shop_status' => 'inactive',
    ]);

    $this->get(route('shops.index', ['selected' => $shop->id]))
        ->assertOk()
        ->assertSee('aria-label="Show Atlas AutoCare on map"', false)
        ->assertViewHas('mapShops', fn ($mapShops): bool => $mapShops->count() === 1
            && $mapShops->first()['id'] === $shop->id
            && $mapShops->first()['logo_url'] === route('media.image', ['directory' => 'shop-logos', 'filename' => 'atlas.png']));
});

it('shows five visual stars with a one-decimal rating and does not rate shops without reviews', function () {
    $owner = User::factory()->create(['role' => 'shop_owner']);
    Shop::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Five Star AutoCare',
        'verification_status' => 'approved',
        'shop_status' => 'active',
        'average_rating' => 5,
        'total_reviews' => 1,
    ]);
    Shop::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'New AutoCare',
        'verification_status' => 'approved',
        'shop_status' => 'active',
        'average_rating' => 0,
        'total_reviews' => 0,
    ]);

    $response = $this->get(route('shops.index'));

    $response->assertOk()
        ->assertSee('aria-label="5.0 out of 5 stars"', false)
        ->assertSee('Not yet rated');
    expect(substr_count($response->getContent(), 'class="bi bi-star-fill"'))->toBe(5);
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

it('sorts the same filtered shop cards by distance when a location is supplied', function () {
    $near = Shop::factory()->create(['name' => 'Zulu Nearby Garage', 'latitude' => 10.2443, 'longitude' => 123.7898, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $far = Shop::factory()->create(['name' => 'Alpha Far Garage', 'latitude' => 10.3, 'longitude' => 123.8, 'verification_status' => 'approved', 'shop_status' => 'active']);

    $this->get(route('shops.index', ['latitude' => 10.2443, 'longitude' => 123.7898, 'sort' => 'distance']))
        ->assertOk()
        ->assertSee('0.0 km away')
        ->assertViewHas('shops', fn ($shops) => $shops->pluck('id')->all() === [$near->id, $far->id]);
});

it('rejects incomplete location coordinates instead of silently changing nearby results', function () {
    $this->get(route('shops.index', ['latitude' => 10.2443]))->assertSessionHasErrors('longitude');
});

it('does not expose non-public shop profiles', function () {
    $barangay = Barangay::factory()->create();
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::query()->create(['owner_id' => $owner->id, 'name' => 'Pending Shop', 'phone' => '09170000000', 'email' => 'pending@example.test', 'address' => 'Demo Street', 'barangay_id' => $barangay->id, 'verification_status' => 'pending', 'shop_status' => 'inactive']);

    $this->get(route('shops.show', $shop))->assertNotFound();
});

it('connects the public shop profile to customer booking, messaging, and published reviews', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::factory()->create(['owner_id' => $owner->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $category = ServiceCategory::query()->create(['name' => 'Maintenance']);
    $service = Service::query()->create(['category_id' => $category->id, 'name' => 'Brake check']);
    ShopService::query()->create(['shop_id' => $shop->id, 'service_id' => $service->id, 'price' => 500, 'availability' => true]);
    $vehicle = $customer->vehicles()->create(['plate_number' => 'PUBLIC-001', 'make' => 'Toyota', 'model' => 'Vios', 'year' => 2020]);
    $repairJob = RepairJob::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'vehicle_id' => $vehicle->id, 'job_number' => 'RJ-PUBLIC-001', 'description' => 'Brake check', 'status' => 'completed']);
    $hiddenRepairJob = RepairJob::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'vehicle_id' => $vehicle->id, 'job_number' => 'RJ-PUBLIC-002', 'description' => 'Follow-up check', 'status' => 'completed']);
    Review::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'repair_job_id' => $repairJob->id, 'rating' => 5, 'comment' => 'Helpful staff.', 'status' => 'published']);
    Review::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'repair_job_id' => $hiddenRepairJob->id, 'rating' => 1, 'comment' => 'Hidden comment.', 'status' => 'hidden']);

    $this->get(route('shops.show', $shop))->assertOk()->assertSee('Sign in to book or message')->assertSee('Helpful staff.')->assertDontSee('Hidden comment.');
    $this->actingAs($customer)->get(route('shops.show', $shop))->assertOk()
        ->assertSee(route('customer.appointments.create', ['shop_id' => $shop->id]), false)
        ->assertSee(route('customer.messages.show', $shop), false);
    $this->get(route('customer.appointments.create', ['shop_id' => $shop->id]))
        ->assertOk()->assertSee('value="'.$shop->id.'" selected', false);
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
