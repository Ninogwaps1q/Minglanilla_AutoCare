<?php

use App\Models\Appointment;
use App\Models\Barangay;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Shop;
use App\Models\User;
use App\Services\EmergencyDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('registers a separate mechanic account and creates one private-base provider profile', function () {
    $this->post(route('register'), [
        'name' => 'Mara Santos', 'email' => 'mara@example.test', 'role' => 'mechanic',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertRedirect(route('verification.notice'));
    $mechanic = User::query()->where('email', 'mara@example.test')->firstOrFail();
    expect($mechanic->role)->toBe('mechanic');
    $mechanic->forceFill(['email_verified_at' => now()])->save();
    $this->actingAs($mechanic);
    $barangay = Barangay::factory()->create(['status' => 'active']);
    $payload = [
        'name' => 'Mara Mobile Repairs', 'phone' => '09170000000', 'email' => $mechanic->email,
        'address' => 'Private base, Poblacion', 'barangay_id' => $barangay->id,
        'latitude' => 10.25, 'longitude' => 123.79,
    ];

    $this->post(route('shop.register.store'), $payload)->assertRedirect(route('shop.dashboard'));
    $profile = $mechanic->shops()->sole();
    expect($profile->provider_type)->toBe('independent_mechanic')
        ->and((int) $profile->booking_capacity)->toBe(1)
        ->and((int) $profile->roadside_radius_km)->toBe(20)
        ->and($profile->roadside_available)->toBeFalse();
    $this->post(route('shop.register.store'), $payload)->assertSessionHasErrors('name');
    $this->get(route('shop.staff.index'))->assertForbidden();
    $profile->update(['plan_slug' => 'premium', 'plan_expires_at' => now()->addMonth()]);
    $this->get(route('shop.technicians.index'))->assertForbidden();
});

it('requires mechanic identity and qualifications before public approval', function () {
    Storage::fake('local');
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $profile = Shop::factory()->create(['owner_id' => $mechanic->id, 'provider_type' => 'independent_mechanic']);
    $this->actingAs($mechanic)->post(route('shop.documents.store'), [
        'document_type' => 'business_permit', 'document' => UploadedFile::fake()->create('permit.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('document_type');
    foreach (['government_id', 'mechanic_credentials'] as $documentType) {
        $this->post(route('shop.documents.store'), [
            'document_type' => $documentType, 'document' => UploadedFile::fake()->create("{$documentType}.pdf", 10, 'application/pdf'),
        ])->assertRedirect();
    }
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->patch(route('admin.shops.approve', $profile))->assertSessionHasErrors('shop');
    foreach ($profile->documents as $document) {
        $this->patch(route('admin.shop-documents.update', $document), ['verification_status' => 'verified'])->assertRedirect();
    }
    $this->patch(route('admin.shops.approve', $profile))->assertRedirect();
    expect($profile->fresh()->verification_status)->toBe('approved');
});

it('lists approved mechanics without exposing their private base address', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $profile = Shop::factory()->create([
        'owner_id' => $mechanic->id, 'provider_type' => 'independent_mechanic',
        'verification_status' => 'approved', 'shop_status' => 'active',
        'address' => 'Private home address 123',
    ]);
    Shop::factory()->create(['name' => 'Garage Shop', 'verification_status' => 'approved', 'shop_status' => 'active']);

    $this->get(route('shops.index', ['provider' => 'independent_mechanic']))
        ->assertOk()->assertSee($profile->name)->assertDontSee('Garage Shop');
    $this->get(route('shops.show', $profile))
        ->assertOk()->assertSee('Independent mechanic')->assertDontSee('Private home address 123');
    $mechanic->update(['status' => 'suspended']);
    $this->get(route('shops.show', $profile))->assertNotFound();
    $this->actingAs($mechanic)->get(route('shop.dashboard'))->assertForbidden();
});

it('requires a visit address for mechanic bookings and shows it only to the parties', function () {
    $fixture = independentMechanicFixture();
    $payload = [
        'shop_id' => $fixture['profile']->id, 'vehicle_id' => $fixture['vehicle']->id,
        'service_id' => $fixture['service']->id,
        'appointment_date' => today()->addDay()->toDateString(), 'appointment_time' => '10:00',
    ];
    $this->actingAs($fixture['customer'])->post(route('customer.appointments.store'), $payload)
        ->assertSessionHasErrors('service_address');
    $this->post(route('customer.appointments.store'), [...$payload, 'service_address' => 'Customer garage, Poblacion'])
        ->assertRedirect(route('customer.appointments.index'));
    $appointment = Appointment::query()->sole();
    expect($appointment->service_address)->toBe('Customer garage, Poblacion');
    $this->get(route('customer.appointments.index'))->assertSee('Customer garage, Poblacion');
    $this->actingAs($fixture['mechanic'])->get(route('shop.appointments.index'))
        ->assertOk()->assertSee('Customer garage, Poblacion');
    $this->get(route('shop.staff.index'))->assertForbidden();
});

it('assigns nearby roadside requests to an available independent mechanic', function () {
    $fixture = independentMechanicFixture();
    $fixture['profile']->update(['latitude' => 10.24, 'longitude' => 123.78, 'roadside_available' => true, 'roadside_radius_km' => 20]);
    $request = $fixture['customer']->emergencyRequests()->create([
        'emergency_type' => 'breakdown', 'latitude' => 10.241, 'longitude' => 123.781,
        'status' => 'pending', 'requested_at' => now(),
    ]);

    expect(app(EmergencyDispatchService::class)->assignNext($request)?->id)->toBe($fixture['profile']->id);
    $this->actingAs($fixture['mechanic'])->get(route('shop.emergency-requests.index'))
        ->assertOk()->assertSee('Breakdown');
});

it('starts the same PayMongo subscription checkout for a verified mechanic', function () {
    $fixture = independentMechanicFixture();
    $fixture['profile']->update(['plan_slug' => 'free', 'plan_expires_at' => null]);
    config()->set('services.paymongo.secret_key', 'sk_test_example');
    config()->set('services.paymongo.webhook_secret', 'whsec_example');
    config()->set('services.paymongo.payment_methods', ['card']);
    Http::preventStrayRequests();
    Http::fake(['api.paymongo.com/*' => Http::response(['data' => ['id' => 'cs_mechanic_1', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_mechanic_1']]])]);

    $this->actingAs($fixture['mechanic'])->post(route('shop.pricing.checkout'), ['plan_slug' => 'basic'])
        ->assertRedirect('https://checkout.paymongo.com/cs_mechanic_1');
    $this->assertDatabaseHas('shop_plan_payments', [
        'shop_id' => $fixture['profile']->id, 'plan_slug' => 'basic', 'status' => 'checkout_pending',
    ]);
    Http::assertSentCount(1);
});

/** @return array{mechanic: User, customer: User, profile: Shop, vehicle: \App\Models\Vehicle, service: Service} */
function independentMechanicFixture(): array
{
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $customer = User::factory()->create(['role' => 'customer']);
    $profile = Shop::factory()->create([
        'owner_id' => $mechanic->id, 'provider_type' => 'independent_mechanic',
        'verification_status' => 'approved', 'shop_status' => 'active',
        'plan_slug' => 'basic', 'plan_expires_at' => now()->addMonth(),
    ]);
    $vehicle = $customer->vehicles()->create(['plate_number' => 'MOBILE-001', 'make' => 'Toyota', 'model' => 'Vios', 'year' => 2020]);
    $category = ServiceCategory::query()->create(['name' => 'Mobile service']);
    $service = Service::query()->create(['category_id' => $category->id, 'name' => 'Battery check', 'estimated_duration' => 60]);
    $profile->services()->create(['service_id' => $service->id, 'price' => 700, 'availability' => true]);

    return compact('mechanic', 'customer', 'profile', 'vehicle', 'service');
}
