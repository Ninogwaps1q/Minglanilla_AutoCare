<?php

use App\Jobs\SendEventSms;
use App\Models\Appointment;
use App\Models\Barangay;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Shop;
use App\Models\ShopPlan;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\EmergencyDispatchService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('registers a separate mechanic account and creates one private-base provider profile', function () {
    $this->post(route('register'), [
        'legal_consent' => 1,
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
        'legal_consent' => 1,
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
    Storage::fake('public');
    Storage::disk('public')->put('shop-logos/mechanic.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JQqkAAAAASUVORK5CYII='));
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $profile = Shop::factory()->create([
        'owner_id' => $mechanic->id, 'provider_type' => 'independent_mechanic',
        'verification_status' => 'approved', 'shop_status' => 'active',
        'address' => 'Private home address 123',
        'logo' => 'shop-logos/mechanic.png',
    ]);
    Shop::factory()->create(['name' => 'Garage Shop', 'verification_status' => 'approved', 'shop_status' => 'active']);

    $this->get(route('shops.index', ['provider' => 'independent_mechanic']))
        ->assertOk()->assertSee($profile->name)->assertSee('media/shop-logos/mechanic.png')->assertDontSee('Garage Shop');
    $this->get(route('shops.show', $profile))
        ->assertOk()->assertSee('Independent mechanic')->assertSee('media/shop-logos/mechanic.png')->assertDontSee('Private home address 123');
    $this->actingAs($mechanic)->get(route('shop.profile.edit'))->assertOk()->assertSee('Mechanic logo or profile photo');
    $mechanic->update(['status' => 'suspended']);
    $this->actingAs(User::factory()->create(['role' => 'customer']))->get(route('shops.show', $profile))->assertNotFound();
    $this->actingAs($mechanic)->get(route('shop.dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
});

it('requires a visit address for mechanic bookings and shows it only to the parties', function () {
    $fixture = independentMechanicFixture();
    $payload = [
        'shop_id' => $fixture['profile']->id, 'vehicle_id' => $fixture['vehicle']->id,
        'service_id' => $fixture['service']->id,
        'appointment_date' => today()->addDay()->toDateString(), 'appointment_time' => '10:00',
    ];
    $this->actingAs($fixture['customer'])->post(route('customer.appointments.store'), $payload)
        ->assertSessionHasErrors(['service_barangay_id', 'service_street']);
    $this->post(route('customer.appointments.store'), [...$payload, 'service_barangay_id' => $fixture['profile']->barangay_id, 'service_street' => 'Customer garage', 'service_landmark' => 'Near Poblacion'])
        ->assertRedirect(route('customer.appointments.index'));
    $appointment = Appointment::query()->sole();
    expect($appointment->service_address)->toContain('Customer garage');
    $this->get(route('customer.appointments.index'))->assertSee('Customer garage');
    $this->actingAs($fixture['mechanic'])->get(route('shop.appointments.index'))
        ->assertOk()->assertSee('Customer garage');
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

it('opens the personal workspace and denies business routes and other mechanics’ records', function () {
    $fixture = independentMechanicFixture();
    $fixture['profile']->update(['plan_slug' => 'premium']);
    $this->actingAs($fixture['mechanic'])->get(route('shop.dashboard'))
        ->assertOk()->assertViewIs('shop.mechanic.dashboard')->assertSee('Upcoming home services')->assertDontSee('Parts inventory');
    foreach (['availability', 'coverage', 'tools', 'portfolio', 'earnings'] as $section) {
        $this->get(route('shop.mechanic.'.$section.'.index'))->assertOk();
    }
    foreach (['shop.inventory.index', 'shop.reports.index', 'shop.exports.index', 'shop.technicians.index'] as $name) {
        $this->get(route($name))->assertForbidden();
    }
    $tool = ['name' => 'OBD scanner', 'category' => 'equipment', 'quantity' => 1, 'available' => true, 'condition' => 'good'];
    $this->post(route('shop.mechanic.tools.store'), $tool)->assertRedirect();
    $id = array_key_first($fixture['profile']->fresh()->mechanic_tools);
    $this->put(route('shop.mechanic.tools.update', $id), [...$tool, 'quantity' => 2])->assertRedirect();
    expect($fixture['profile']->fresh()->mechanic_tools[$id]['quantity'])->toBe(2);
    $second = User::factory()->create(['role' => 'mechanic']);
    Shop::factory()->create(['owner_id' => $second->id, 'provider_type' => 'independent_mechanic']);
    $this->actingAs($second)->delete(route('shop.mechanic.tools.destroy', $id))->assertNotFound();
    $this->actingAs(User::factory()->create(['role' => 'shop_owner']))->get(route('shop.mechanic.earnings.index'))->assertForbidden();
});

it('validates a nearby home service quote and carries the accepted fees through to the invoice', function () {
    $fixture = independentMechanicFixture();
    $profile = $fixture['profile'];
    $profile->update(['latitude' => 10.241235, 'longitude' => 123.781235, 'booking_capacity' => 4]);
    $this->actingAs($fixture['mechanic'])->put(route('shop.mechanic.coverage'), [
        'radius_km' => 5, 'coverage_level' => 'barangay', 'coverage_barangay' => $profile->barangay_id,
        'home_fee' => 100, 'inspection_fee' => 50, 'travel_rate' => 25,
    ])->assertRedirect()->assertSessionDoesntHaveErrors();
    $this->put(route('shop.mechanic.availability'), ['online' => true, 'buffer_minutes' => 30])
        ->assertRedirect()->assertSessionDoesntHaveErrors();
    $payload = [
        'shop_id' => $profile->id, 'vehicle_id' => $fixture['vehicle']->id, 'service_id' => $fixture['service']->id,
        'appointment_date' => today(Appointment::TIMEZONE)->addDay()->toDateString(), 'appointment_time' => '10:00',
        'service_street' => 'Customer garage', 'service_barangay_id' => $profile->barangay_id,
        'latitude' => 10.241, 'longitude' => 123.78,
    ];
    $quoteRoute = route('customer.appointments.mobile-quote');
    $this->actingAs($fixture['customer'])->postJson($quoteRoute, [...$payload, 'latitude' => 11.0])
        ->assertUnprocessable()->assertJsonValidationErrors('latitude');
    $this->postJson($quoteRoute, [...$payload, 'service_barangay_id' => Barangay::factory()->create()->id])
        ->assertUnprocessable()->assertJsonValidationErrors('service_barangay_id');
    $quote = $this->postJson($quoteRoute, $payload)->assertOk()
        ->assertJsonPath('home_fee', 10000)->assertJsonPath('inspection_fee', 5000)->assertJsonMissingPath('latitude')->json();
    expect($quote['travel_fee'])->toBeGreaterThan(0);
    $this->post(route('customer.appointments.store'), [...$payload, 'mobile_quote_total' => $quote['total'] + 1])
        ->assertSessionHasErrors('mobile_quote_total');
    $this->post(route('customer.appointments.store'), [...$payload, 'mobile_quote_total' => $quote['total']])
        ->assertRedirect(route('customer.appointments.index'));
    $appointment = Appointment::query()->sole();
    expect($appointment->mobile_service_quote)->toBe($quote);
    $this->get(route('customer.appointments.index'))->assertSee('Travel fee');
    $this->post(route('customer.appointments.store'), [...$payload, 'appointment_time' => '11:15', 'mobile_quote_total' => $quote['total']])
        ->assertSessionHasErrors('appointment_time');

    $this->actingAs($fixture['mechanic'])->patch(route('shop.appointments.update', $appointment), ['status' => 'confirmed'])
        ->assertRedirect()->assertSessionDoesntHaveErrors();
    $this->patch(route('shop.mechanic.travel', $appointment), ['stage' => 'arrived'])->assertSessionHasErrors('stage');
    foreach (['en_route', 'en_route', 'arrived'] as $stage) {
        $this->patch(route('shop.mechanic.travel', $appointment), ['stage' => $stage])->assertRedirect()->assertSessionDoesntHaveErrors();
    }
    expect($fixture['customer']->notifications()->where('title', 'Mechanic en route')->count())->toBe(1);
    $profile->update(['plan_slug' => 'free', 'plan_expires_at' => null]);
    $this->post(route('shop.repair-jobs.store'), ['appointment_id' => $appointment->id, 'description' => 'Battery check'])
        ->assertRedirect(route('shop.repair-jobs.index'));
    $job = $appointment->fresh()->repairJob;
    expect((float) $job->actual_cost)->toBe($quote['total'] / 100);
    $travel = $job->items()->where('type', 'travel_fee')->sole();
    $this->delete(route('shop.repair-job-items.destroy', $travel))->assertConflict();
    $job->update(['status' => 'completed']);
    $this->post(route('shop.invoices.store', $job))->assertRedirect(route('shop.invoices.index'))->assertSessionDoesntHaveErrors();
    expect((float) $job->invoice()->sole()->total)->toBe($quote['total'] / 100);
});

it('enforces configured free plan limits and mechanic availability on the server', function () {
    $fixture = independentMechanicFixture();
    $profile = $fixture['profile'];
    $profile->update(['plan_slug' => 'free', 'plan_expires_at' => null]);
    ShopPlan::query()->where('slug', 'free')->update(['mechanic_entitlements' => json_encode(['monthly_limit' => 0])]);
    $payload = [
        'shop_id' => $profile->id, 'vehicle_id' => $fixture['vehicle']->id, 'service_id' => $fixture['service']->id,
        'appointment_date' => today(Appointment::TIMEZONE)->addDay()->toDateString(), 'appointment_time' => '10:00',
        'service_street' => 'Customer garage', 'service_barangay_id' => $profile->barangay_id,
    ];
    $this->actingAs($fixture['customer'])->post(route('customer.appointments.store'), $payload)
        ->assertSessionHasErrors('shop_id');
    $profile->update(['mechanic_settings' => ['online' => false]]);
    $this->getJson(route('customer.appointments.availability', ['shop_id' => $profile->id, 'date' => $payload['appointment_date']]))
        ->assertNotFound();
    expect(Appointment::query()->count())->toBe(0);
});

it('adds and removes individual areas without losing the other areas in a mechanic’s coverage', function () {
    $fixture = independentMechanicFixture();
    $profile = $fixture['profile'];
    $profile->update(['latitude' => 10.24, 'longitude' => 123.78]);
    $barangay = Barangay::factory()->create(['name' => 'Second service area']);
    $settings = ['radius_km' => 20, 'coverage_level' => 'barangay', 'home_fee' => 100, 'inspection_fee' => 0, 'travel_rate' => 0];

    $this->actingAs($fixture['mechanic'])->put(route('shop.mechanic.coverage'), [
        ...$settings, 'coverage_barangay' => $profile->barangay_id,
    ])->assertSessionDoesntHaveErrors();
    $this->put(route('shop.mechanic.coverage'), [
        ...$settings, 'coverage_barangay' => $barangay->id, 'keep_existing_areas' => true,
    ])->assertSessionDoesntHaveErrors();
    expect($profile->fresh()->mechanic_settings['coverage_barangays'])->toBe([$profile->barangay_id, $barangay->id]);
    $this->get(route('shop.mechanic.coverage.index'))->assertOk()->assertSee('Second service area');

    $this->delete(route('shop.mechanic.coverage.destroy', ['barangay', $barangay->id]))->assertRedirect();
    expect($profile->fresh()->mechanic_settings['coverage_barangays'])->toBe([$profile->barangay_id]);
});

it('tracks mechanic application review and only publishes approved projects with permission', function () {
    Storage::fake('local');
    $fixture = independentMechanicFixture();
    $profile = $fixture['profile'];
    $profile->update(['verification_status' => 'pending', 'shop_status' => 'inactive', 'mechanic_profile' => ['vehicle_types' => ['motorcycle']]]);
    $path = 'mechanic-portfolio/'.$profile->id.'/repair.png';
    $id = (string) Str::uuid();
    Storage::disk('local')->put($path, 'sample photo content');
    $profile->update(['mechanic_portfolio' => [$id => ['title' => 'Brake rebuild', 'description' => 'Replaced worn pads', 'vehicle' => 'Motorcycle', 'before' => $path, 'permission' => true]]]);
    $photo = route('shops.portfolio.photo', [$profile, $id, 'before']);
    $this->get($photo)->assertNotFound();
    $review = route('admin.shops.mechanic-review', $profile);
    $this->actingAs($fixture['mechanic'])->patch($review, ['status' => 'under_review'])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->patch($review, ['status' => 'under_review'])->assertRedirect();
    $this->patch($review, ['status' => 'additional_requirements', 'reason' => 'Upload newer training evidence.'])->assertRedirect();
    expect($profile->fresh()->verification_status)->toBe('additional_requirements');
    $profile->update(['verification_status' => 'approved', 'shop_status' => 'active']);
    $this->get($photo)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('shops.index', ['vehicle_type' => 'motorcycle', 'home_service' => 1, 'max_price' => 700]))
        ->assertOk()->assertSee($profile->name);
    $this->get(route('shops.index', ['max_price' => 699]))->assertOk()->assertDontSee($profile->name);
    $this->get(route('shops.show', $profile))->assertOk()->assertSee('Brake rebuild')->assertDontSee($path);
    $this->actingAs($fixture['mechanic'])->delete(route('shop.mechanic.portfolio.destroy', $id))->assertRedirect();
    $this->get($photo)->assertNotFound();
    Storage::disk('local')->assertMissing($path);
});

it('reports this mechanic’s confirmed payments, expenses and net separately', function () {
    $fixture = independentMechanicFixture();
    $profile = $fixture['profile'];
    $invoice = $profile->invoices()->create(['customer_id' => $fixture['customer']->id, 'invoice_number' => 'IN-MECHANIC-TEST', 'subtotal' => 800, 'total' => 800]);
    Payment::query()->create([
        'shop_id' => $profile->id, 'invoice_id' => $invoice->id, 'customer_id' => $fixture['customer']->id,
        'amount' => 800, 'payment_method' => 'cash', 'payment_date' => now(), 'status' => 'confirmed',
    ]);
    $this->actingAs($fixture['mechanic'])->post(route('shop.mechanic.expenses.store'), [
        'spent_on' => today(Appointment::TIMEZONE)->toDateString(), 'description' => 'Fuel', 'amount' => 120,
    ])->assertRedirect()->assertSessionDoesntHaveErrors();
    $this->get(route('shop.mechanic.earnings.index', ['period' => 'day']))->assertOk()
        ->assertSee('PHP 800.00')->assertSee('PHP 120.00')->assertSee('PHP 680.00');
    $this->get(route('shop.mechanic.export', ['period' => 'day']))->assertOk()->assertDownload('mechanic-earnings.csv');
});

it('shares a responder fix only with consent and clears it at arrival', function () {
    $fixture = independentMechanicFixture();
    $fixture['profile']->update(['plan_slug' => 'premium']);
    $customer = $fixture['customer'];
    $payload = ['emergency_type' => 'breakdown', 'latitude' => 10.241, 'longitude' => 123.781];
    $this->actingAs($customer)->postJson(route('customer.emergency-requests.store'), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('share_pickup');
    $request = $customer->emergencyRequests()->create([...$payload, 'assigned_shop_id' => $fixture['profile']->id, 'requested_at' => now(), 'status' => 'en_route']);
    $share = route('shop.emergency-requests.location', $request);
    $tracking = route('customer.emergency-requests.tracking', $request);
    $location = ['action' => 'share', 'latitude' => 10.24567, 'longitude' => 123.78567];
    $this->actingAs($fixture['mechanic'])->postJson($share, $location)->assertUnprocessable()->assertJsonValidationErrors('consent');
    $this->postJson($share, [...$location, 'consent' => true])->assertOk();
    $this->actingAs(User::factory()->create(['role' => 'customer']))->getJson($tracking)->assertNotFound();
    $this->actingAs($customer)->getJson($tracking)->assertOk()->assertJsonPath('location.latitude', 10.24567);
    $this->actingAs($fixture['mechanic'])->postJson($share, ['action' => 'arrive'])->assertOk();
    $this->postJson($share, [...$location, 'consent' => true])->assertConflict();
    $this->actingAs($customer)->getJson($tracking)->assertOk()->assertJsonPath('arrived', true)->assertJsonPath('location', null);
    expect($request->fresh()->responder_location)->toBeNull();
});

it('reserves the original slot until a customer accepts the mechanic’s proposal', function () {
    $fixture = independentMechanicFixture();
    $date = today(Appointment::TIMEZONE)->addDay()->toDateString();
    $appointment = $fixture['customer']->appointments()->create([
        'shop_id' => $fixture['profile']->id, 'vehicle_id' => $fixture['vehicle']->id, 'service_id' => $fixture['service']->id,
        'appointment_date' => $date, 'appointment_time' => '10:00', 'status' => 'pending',
    ]);
    $proposal = ['requested_date' => $date, 'requested_time' => '13:00', 'reason' => 'Earlier visit is running long.'];
    $this->actingAs($fixture['mechanic'])->post(route('shop.appointments.propose', $appointment), $proposal)
        ->assertRedirect()->assertSessionDoesntHaveErrors();
    expect($appointment->fresh()->appointment_time->format('H:i'))->toBe('10:00');
    $this->patch(route('shop.appointments.update', $appointment), ['status' => 'confirmed'])->assertSessionHasErrors('status');
    $this->actingAs($fixture['customer'])->get(route('customer.appointments.index'))->assertOk()->assertSee('Accept proposed time');
    $this->patch(route('customer.appointments.proposal.decide', $appointment), ['decision' => 'approve'])
        ->assertRedirect()->assertSessionDoesntHaveErrors();
    expect($appointment->fresh()->status)->toBe('confirmed')
        ->and($appointment->fresh()->appointment_time->format('H:i'))->toBe('13:00');
});

it('simulates opted-in mechanic SMS once without contacting the provider', function () {
    config()->set(['services.sms.driver' => 'demo', 'services.sms.mechanic_alerts' => true, 'services.semaphore.daily_limit' => 1]);
    Http::preventStrayRequests();
    Http::fake();
    $mechanic = independentMechanicFixture()['mechanic'];
    $mechanic->forceFill(['phone' => '09175550000', 'communication_preferences' => ['sms' => ['booking' => true, 'roadside' => false]]])->save();
    $mechanic->recordOptionalConsent('sms', true);
    $first = $mechanic->notifications()->create([
        'type' => 'appointment_requested', 'title' => 'New visit', 'message' => 'Visit request',
        'data' => ['appointment_id' => 123, 'mechanic_update' => true],
    ]);
    $second = $mechanic->notifications()->create([
        'type' => 'appointment_reminder', 'title' => 'Upcoming visit', 'message' => 'Visit reminder',
        'data' => ['appointment_id' => 123, 'mechanic_update' => true],
    ]);
    $service = app(SmsService::class);
    foreach ([$first, $first, $second] as $notification) {
        (new SendEventSms($notification->id))->handle($service);
    }
    $this->assertDatabaseHas('sms_deliveries', ['notification_id' => $first->id, 'status' => 'simulated', 'cost' => 0]);
    $this->assertDatabaseMissing('sms_deliveries', ['notification_id' => $second->id]);
    $this->assertDatabaseCount('sms_deliveries', 1);
    Http::assertNothingSent();
});

it('retries a semaphore rate-limit rejection once and records the accepted SMS', function () {
    config()->set([
        'services.sms.driver' => 'semaphore', 'services.sms.mechanic_alerts' => true,
        'services.semaphore.api_key' => 'fake-mechanic-sms-key',
    ]);
    Http::preventStrayRequests();
    Http::fake(['https://api.semaphore.co/*' => Http::sequence()
        ->push(['error' => 'Rate limited'], 429, ['Retry-After' => '0'])
        ->push([['message_id' => 456, 'status' => 'Queued']])]);
    $fixture = independentMechanicFixture();
    $fixture['mechanic']->forceFill(['phone' => '09175550000', 'communication_preferences' => ['sms' => ['booking' => true]]])->save();
    $fixture['mechanic']->recordOptionalConsent('sms', true);
    $notification = $fixture['mechanic']->notifications()->create([
        'type' => 'appointment_requested', 'title' => 'New visit', 'message' => 'Visit request',
        'data' => ['appointment_id' => 123, 'mechanic_update' => true],
    ]);

    (new SendEventSms($notification->id))->handle(app(SmsService::class));
    Http::assertSentCount(2);
    $this->assertDatabaseHas('sms_deliveries', ['notification_id' => $notification->id, 'status' => 'accepted']);
    $this->assertDatabaseCount('sms_deliveries', 1);
});

/** @return array{mechanic: User, customer: User, profile: Shop, vehicle: Vehicle, service: Service} */
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
