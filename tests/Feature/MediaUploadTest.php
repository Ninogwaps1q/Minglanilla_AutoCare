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
    $this->get(route('customer.profile.edit'))->assertOk()->assertSee('/storage/'.$path, false);
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
    $this->get(route('shop.profile.edit'))->assertOk()->assertSee('/storage/'.$shop->logo, false);
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
    $this->get(route('shop.technicians.index'))->assertOk()->assertSee('/storage/'.$technician->photo, false);
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

function mediaImage(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, mediaPng());
}

function mediaPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JQqkAAAAASUVORK5CYII=');
}
