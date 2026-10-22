<?php

use App\Models\Barangay;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('stores and renders a customer profile photo', function () {
    Storage::fake('public');
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)->put(route('customer.profile.update'), [
        'name' => $customer->name,
        'profile_photo' => mediaImage('profile.png'),
    ])->assertRedirect(route('customer.profile.edit'));

    $path = $customer->fresh()->profile_photo;
    expect($path)->toStartWith('profile-photos/');
    Storage::disk('public')->assertExists($path);
    $this->get(route('customer.profile.edit'))->assertOk()->assertSee('/media/'.$path, false);
    $this->get(route('media.image', ['directory' => 'profile-photos', 'filename' => basename($path)]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('stores shop branding during registration and replaces it from the profile', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $barangay = Barangay::factory()->create(['status' => 'active']);

    $this->actingAs($owner)->post(route('shop.register.store'), [
        'name' => 'Image Ready AutoCare',
        'phone' => '09170000000',
        'email' => 'images@example.test',
        'address' => 'Minglanilla',
        'barangay_id' => $barangay->id,
        'latitude' => 10.25,
        'longitude' => 123.79,
        'logo' => mediaImage('logo.png'),
        'cover_photo' => mediaImage('cover.png'),
    ])->assertRedirect(route('dashboard'));

    $shop = $owner->shops()->sole();
    Storage::disk('public')->assertExists($shop->logo);
    Storage::disk('public')->assertExists($shop->cover_photo);
    $oldLogo = $shop->logo;

    $this->put(route('shop.profile.update'), [
        'name' => $shop->name,
        'phone' => $shop->phone,
        'email' => $shop->email,
        'address' => $shop->address,
        'barangay_id' => $shop->barangay_id,
        'logo' => mediaImage('replacement.png'),
    ])->assertRedirect();

    $shop->refresh();
    Storage::disk('public')->assertMissing($oldLogo);
    Storage::disk('public')->assertExists($shop->logo);
    $this->get(route('shop.profile.edit'))->assertOk()->assertSee('/media/'.$shop->logo, false);
});

it('shows usable fallbacks and reupload guidance when saved shop images are missing', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Missing Image Shop',
        'logo' => 'shop-logos/missing.png',
        'cover_photo' => 'shop-covers/missing.png',
        'latitude' => 10.25,
        'longitude' => 123.79,
        'verification_status' => 'approved',
        'shop_status' => 'active',
    ]);

    $this->get(route('shops.show', $shop))
        ->assertOk()
        ->assertSee('aria-label="Missing Image Shop logo"', false)
        ->assertDontSee('/media/shop-logos/missing.png')
        ->assertDontSee('/media/shop-covers/missing.png');
    $this->get(route('shops.index', ['selected' => $shop->id]))
        ->assertViewHas('mapShops', fn ($shops): bool => $shops->first()['logo_url'] === null);
    $this->actingAs($owner)->get(route('shop.profile.edit'))
        ->assertOk()
        ->assertSee('Choose a new shop logo below')
        ->assertSee('Choose a new cover photo below');
});

it('stores and renders a technician photo only for the owning shop', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::query()->create([
        'owner_id' => $owner->id,
        'barangay_id' => Barangay::factory()->create()->id,
        'name' => 'Photo Technician Shop',
        'phone' => '09170000000',
        'email' => 'technician-photo@example.test',
        'address' => 'Minglanilla',
    ]);

    $this->actingAs($owner)->post(route('shop.technicians.store'), [
        'name' => 'Alex Santos',
        'experience_years' => 4,
        'status' => 'active',
        'photo' => mediaImage('technician.png'),
    ])->assertRedirect();

    $technician = $shop->technicians()->sole();
    Storage::disk('public')->assertExists($technician->photo);
    $this->get(route('shop.technicians.index'))->assertOk()->assertSee('/media/'.$technician->photo, false);
});

it('rejects active and oversized upload formats', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)->put(route('customer.profile.update'), [
        'name' => $customer->name,
        'profile_photo' => UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml'),
    ])->assertSessionHasErrors('profile_photo');

    $this->put(route('customer.profile.update'), [
        'name' => $customer->name,
        'profile_photo' => UploadedFile::fake()->createWithContent('large.png', mediaPng().str_repeat('0', 2_100_000)),
    ])->assertSessionHasErrors('profile_photo');
});

it('serves only uploaded public images and keeps private documents inaccessible', function () {
    Storage::fake('public');
    Storage::disk('public')->put('vehicles/photo.png', mediaPng());
    Storage::disk('public')->put('vehicles/not-image.png', 'not an image');
    Storage::disk('public')->put('shop-documents/secret.png', mediaPng());

    $this->get(route('media.image', ['directory' => 'vehicles', 'filename' => 'photo.png']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertStreamedContent(mediaPng());

    $this->get(route('media.image', ['directory' => 'vehicles', 'filename' => 'missing.png']))->assertNotFound();
    $this->get(route('media.image', ['directory' => 'vehicles', 'filename' => 'not-image.png']))->assertNotFound();
    $this->get('/media/shop-documents/secret.png')->assertNotFound();
    $this->get('/media/vehicles/%2E%2E%2Fsecret.png')->assertNotFound();
});

function mediaImage(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, mediaPng());
}

function mediaPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JQqkAAAAASUVORK5CYII=');
}
