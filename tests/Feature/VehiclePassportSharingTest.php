<?php

use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePassportShare;
use App\Models\VehicleServiceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('lets a customer create a temporary shop link and an approved shop read only that vehicle history', function () {
    [$customer, $vehicle, $shopOwner, $shop] = passportSharingFixture();
    VehicleServiceRecord::query()->create([
        'vehicle_id' => $vehicle->id, 'customer_id' => $customer->id, 'shop_id' => $shop->id,
        'service_date' => today(), 'service_type' => 'Oil Change', 'total_cost' => 900,
    ]);
    $otherVehicle = Vehicle::factory()->create();
    VehicleServiceRecord::query()->create([
        'vehicle_id' => $otherVehicle->id, 'customer_id' => $otherVehicle->customer_id,
        'shop_id' => $shop->id, 'service_date' => today(), 'service_type' => 'Private Engine Work',
    ]);

    $this->actingAs($customer)->post(route('customer.vehicles.passport.shares.store', $vehicle))
        ->assertRedirect(route('customer.vehicles.passport', $vehicle));

    $share = $vehicle->passportShares()->sole();
    expect($share->expires_at->isFuture())->toBeTrue();
    $url = URL::temporarySignedRoute('shop.passports.shared', $share->expires_at, ['passportShare' => $share]);

    $this->get(route('customer.vehicles.passport', $vehicle))->assertSee($url)->assertSee('Revoke access');
    $this->get(route('customer.vehicles.passport.shares.qr', [$vehicle, $share]))
        ->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('<svg', false);

    $response = $this->actingAs($shopOwner)->get($url)
        ->assertOk()
        ->assertSee('Oil Change')
        ->assertSee('900.00')
        ->assertDontSee('Private Engine Work');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('prevents another customer from creating or revoking a vehicle share', function () {
    [$customer, $vehicle] = passportSharingFixture();
    $share = VehiclePassportShare::factory()->for($vehicle)->create();
    $otherCustomer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($otherCustomer)->post(route('customer.vehicles.passport.shares.store', $vehicle))->assertForbidden();
    $this->delete(route('customer.vehicles.passport.shares.destroy', [$vehicle, $share]))->assertForbidden();

    expect($share->fresh()->revoked_at)->toBeNull();
    expect($vehicle->passportShares()->count())->toBe(1);
});

it('does not let an owner use a share belonging to another vehicle', function () {
    [$customer, $vehicle] = passportSharingFixture();
    $otherVehicle = Vehicle::factory()->for($customer, 'customer')->create();
    $share = VehiclePassportShare::factory()->for($otherVehicle)->create();

    $this->actingAs($customer)->delete(route('customer.vehicles.passport.shares.destroy', [$vehicle, $share]))->assertNotFound();
    $this->get(route('customer.vehicles.passport.shares.qr', [$vehicle, $share]))->assertNotFound();

    expect($share->fresh()->revoked_at)->toBeNull();
});

it('requires a signed link and an approved active shop account', function () {
    [$customer, $vehicle, $shopOwner, $shop] = passportSharingFixture();
    $share = VehiclePassportShare::factory()->for($vehicle)->create();
    $url = URL::temporarySignedRoute('shop.passports.shared', $share->expires_at, ['passportShare' => $share]);

    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($customer)->get($url)->assertForbidden();
    $this->actingAs($shopOwner)->get(route('shop.passports.shared', $share))->assertForbidden();

    $shop->update(['verification_status' => 'pending', 'shop_status' => 'inactive']);
    $this->get($url)->assertForbidden();
});

it('revokes previous links when a new one is created and blocks revoked links', function () {
    [$customer, $vehicle, $shopOwner] = passportSharingFixture();
    $oldShare = VehiclePassportShare::factory()->for($vehicle)->create();
    $oldUrl = URL::temporarySignedRoute('shop.passports.shared', $oldShare->expires_at, ['passportShare' => $oldShare]);

    $this->actingAs($customer)->post(route('customer.vehicles.passport.shares.store', $vehicle))->assertRedirect();
    $newShare = $vehicle->passportShares()->latest('id')->firstOrFail();
    expect($oldShare->fresh()->revoked_at)->not->toBeNull();
    expect($newShare->id)->not->toBe($oldShare->id);

    $this->actingAs($shopOwner)->get($oldUrl)->assertNotFound();

    $this->actingAs($customer)->delete(route('customer.vehicles.passport.shares.destroy', [$vehicle, $newShare]))->assertRedirect();
    $newUrl = URL::temporarySignedRoute('shop.passports.shared', $newShare->expires_at, ['passportShare' => $newShare]);
    $this->actingAs($shopOwner)->get($newUrl)->assertNotFound();
    $this->actingAs($customer)->get(route('customer.vehicles.passport.shares.qr', [$vehicle, $newShare]))->assertNotFound();
});

it('rejects expired links and keeps later signed history pages accessible', function () {
    [$customer, $vehicle, $shopOwner, $shop] = passportSharingFixture();
    $expiredShare = VehiclePassportShare::factory()->for($vehicle)->create(['expires_at' => now()->subMinute()]);
    $expiredUrl = URL::temporarySignedRoute('shop.passports.shared', $expiredShare->expires_at, ['passportShare' => $expiredShare]);
    $share = VehiclePassportShare::factory()->for($vehicle)->create();
    foreach (range(1, 21) as $number) {
        VehicleServiceRecord::query()->create([
            'vehicle_id' => $vehicle->id, 'customer_id' => $customer->id, 'shop_id' => $shop->id,
            'service_date' => today()->subDays($number), 'service_type' => 'Visit '.$number,
        ]);
    }

    $this->actingAs($shopOwner)->get($expiredUrl)->assertForbidden();
    $firstPage = URL::temporarySignedRoute('shop.passports.shared', $share->expires_at, ['passportShare' => $share]);
    $this->get($firstPage)->assertSee('Next')->assertSee('page=2', false);
    $secondPage = URL::temporarySignedRoute('shop.passports.shared', $share->expires_at, ['passportShare' => $share, 'page' => 2]);
    $this->get($secondPage)->assertOk()->assertSee('Visit 21')->assertSee('Previous');
});

/** @return array{User, Vehicle, User, Shop} */
function passportSharingFixture(): array
{
    $customer = User::factory()->create(['role' => 'customer']);
    $vehicle = Vehicle::factory()->for($customer, 'customer')->create();
    $shopOwner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::factory()->for($shopOwner, 'owner')->create(['verification_status' => 'approved', 'shop_status' => 'active']);

    return [$customer, $vehicle, $shopOwner, $shop];
}
