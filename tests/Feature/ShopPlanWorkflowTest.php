<?php

use App\Models\Quotation;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopPlan;
use App\Models\ShopPlanPayment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('keeps free shops discoverable while blocking new work and premium tools', function () {
    $shop = Shop::factory()->create(['name' => 'Free Listing Garage', 'verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'free', 'plan_expires_at' => null]);
    $service = Service::factory()->create();

    $this->actingAs($shop->owner)->post(route('shop.services.store'), ['service_id' => $service->id, 'price' => 500, 'availability' => true])->assertRedirect();
    $this->get(route('shop.inventory.index'))->assertRedirect(route('shop.pricing'));
    $this->post(route('shop.repair-jobs.store'), [])->assertSessionHasErrors('shop');
    $this->get(route('shop.dashboard'))->assertOk()->assertSee('Your Free listing is visible');
    $this->get(route('shop.repair-jobs.index'))->assertOk();

    $this->get(route('shops.index'))->assertSee('Free Listing Garage');
    $this->actingAs(User::factory()->create(['role' => 'customer']))->get(route('shops.show', $shop))->assertSee('not accepting online bookings');
    $this->get(route('customer.appointments.create'))->assertDontSee('Free Listing Garage');
    $customer = auth()->user();
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $this->post(route('customer.appointments.store'), [
        'shop_id' => $shop->id, 'vehicle_id' => $vehicle->id, 'service_id' => $service->id,
        'appointment_date' => today()->addDay()->toDateString(), 'appointment_time' => '10:00',
    ])->assertSessionHasErrors('shop_id');
    $quotation = Quotation::query()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'quotation_number' => 'QT-FREE-001', 'status' => 'accepted']);
    $this->actingAs($shop->owner)->post(route('shop.quotations.repair', $quotation))->assertSessionHasErrors('shop');
});

it('lets basic shops manage repairs but reserves reports and inventory for premium', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'basic', 'plan_expires_at' => now()->addMonth()]);

    $this->actingAs($shop->owner)->get(route('shop.repair-jobs.create'))->assertOk();
    $this->get(route('shop.reports.index'))->assertRedirect(route('shop.pricing'));
    $this->get(route('shop.inventory.index'))->assertRedirect(route('shop.pricing'));
});

it('shows the trial countdown and removes premium access after thirty days', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'free', 'plan_expires_at' => null]);

    $this->actingAs($shop->owner)->post(route('shop.pricing.trial'))->assertRedirect();
    $this->get(route('shop.pricing'))->assertSee('30 days left');
    $this->get(route('shop.inventory.index'))->assertOk();

    $this->travel(31)->days();
    $this->get(route('shop.inventory.index'))->assertRedirect(route('shop.pricing'));
    $this->get(route('shop.pricing'))->assertSee('Your Premium trial ended');
});

it('records a private proof and price snapshot before an admin activates a paid plan', function () {
    Storage::fake(config('filesystems.default'));
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'free', 'plan_expires_at' => null]);
    $basic = ShopPlan::query()->where('slug', 'basic')->firstOrFail();
    $basic->update(['payment_instructions' => 'Pay using the platform account shown here.']);

    $this->actingAs($shop->owner)->post(route('shop.pricing.request', $basic))->assertRedirect();
    $this->post(route('shop.pricing.payments.store'), ['reference' => 'PAY-123', 'proof' => UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf')])->assertRedirect();
    $payment = ShopPlanPayment::query()->sole();
    expect($payment->amount)->toBe(499);
    expect($payment->status)->toBe('pending');
    Storage::assertExists($payment->proof_path);
    $this->get(route('shop.pricing.payments.proof', $payment))->assertOk();
    $this->actingAs(User::factory()->create(['role' => 'shop_owner']))->get(route('shop.pricing.payments.proof', $payment))->assertNotFound();
    $this->actingAs($shop->owner);
    $this->post(route('shop.pricing.payments.store'), ['reference' => 'DUPLICATE'])->assertSessionHasErrors('payment');

    $basic->update(['monthly_price' => 650]);
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($shop->owner)->patch(route('admin.shop-plan-payments.update', $payment), ['decision' => 'approved'])->assertForbidden();
    $this->actingAs($admin)->get(route('admin.shop-plan-payments.proof', $payment))->assertOk();
    $this->patch(route('admin.shop-plan-payments.update', $payment), ['decision' => 'approved'])->assertRedirect();
    expect($payment->fresh()->amount)->toBe(499);
    expect($shop->fresh()->activePlanSlug())->toBe('basic');
    expect($shop->fresh()->plan_expires_at)->not->toBeNull();
    $this->get(route('admin.shop-plans.index'))->assertOk()->assertSee('Payment review history')->assertSee('PAY-123');
    $this->assertDatabaseHas('notifications', ['user_id' => $shop->owner_id, 'type' => 'shop_plan_payment']);
    $this->patch(route('admin.shop-plan-payments.update', $payment), ['decision' => 'approved'])->assertSessionHasErrors('payment');
});

it('starts a paid month after an active free trial ends', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'free', 'plan_expires_at' => null]);
    $premium = ShopPlan::query()->where('slug', 'premium')->firstOrFail();
    $premium->update(['payment_instructions' => 'Use the platform account.']);

    $this->actingAs($shop->owner)->post(route('shop.pricing.trial'))->assertRedirect();
    $trialEndsAt = $shop->fresh()->trialEndsAt();
    $this->post(route('shop.pricing.request', $premium))->assertRedirect();
    $this->post(route('shop.pricing.payments.store'), ['reference' => 'TRIAL-PAYMENT'])->assertRedirect();
    $payment = ShopPlanPayment::query()->sole();

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.shop-plan-payments.update', $payment), ['decision' => 'approved'])->assertRedirect();
    expect($payment->fresh()->period_starts_at->toDateString())->toBe($trialEndsAt->toDateString());
    expect($payment->fresh()->period_ends_at->greaterThan($trialEndsAt))->toBeTrue();

    $this->travel(31)->days();
    expect($shop->fresh()->activePlanSlug())->toBe('premium');
});

it('requires a rejection reason and lets an owner resubmit after rejection', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'free', 'plan_expires_at' => null]);
    $plan = ShopPlan::query()->where('slug', 'premium')->firstOrFail();
    $plan->update(['payment_instructions' => 'Pay using the approved platform account.']);
    $this->actingAs($shop->owner)->post(route('shop.pricing.request', $plan))->assertRedirect();
    $this->post(route('shop.pricing.payments.store'), ['reference' => 'WRONG-REF'])->assertRedirect();
    $payment = ShopPlanPayment::query()->sole();

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->patch(route('admin.shop-plan-payments.update', $payment), ['decision' => 'rejected'])->assertSessionHasErrors('review_notes');
    $this->patch(route('admin.shop-plan-payments.update', $payment), ['decision' => 'rejected', 'review_notes' => 'Reference not found.'])->assertRedirect();
    expect($shop->fresh()->activePlanSlug())->toBe('free');
    $this->actingAs($shop->owner)->post(route('shop.pricing.payments.store'), ['reference' => 'CORRECT-REF'])->assertRedirect();
    expect(ShopPlanPayment::query()->where('status', 'pending')->count())->toBe(1);
});

it('sends each expiry reminder once and links it to pricing', function () {
    $shop = Shop::factory()->create(['plan_slug' => 'free', 'plan_expires_at' => null, 'trial_started_at' => now()->subDays(23)]);
    $this->artisan('app:send-shop-plan-reminders')->assertExitCode(0);
    $this->artisan('app:send-shop-plan-reminders')->assertExitCode(0);
    expect($shop->owner->notifications()->where('type', 'shop_plan_reminder')->count())->toBe(1);

    $this->travel(6)->days();
    $this->artisan('app:send-shop-plan-reminders')->assertExitCode(0);
    expect($shop->owner->notifications()->where('type', 'shop_plan_reminder')->count())->toBe(2);

    $this->travel(1)->days();
    $this->artisan('app:send-shop-plan-reminders')->assertExitCode(0);
    expect($shop->owner->notifications()->where('type', 'shop_plan_reminder')->count())->toBe(3);
    expect($shop->owner->notifications()->latest()->first()->destinationFor($shop->owner))->toBe(route('shop.pricing'));
});

it('reminds owners of paid expiry without repeating a reminder', function () {
    $shop = Shop::factory()->create(['plan_slug' => 'basic', 'plan_expires_at' => now()->addDays(7), 'trial_started_at' => null]);
    $this->artisan('app:send-shop-plan-reminders')->assertExitCode(0);
    $this->artisan('app:send-shop-plan-reminders')->assertExitCode(0);
    expect($shop->owner->notifications()->where('type', 'shop_plan_reminder')->count())->toBe(1);
});

it('allows an admin to close a trial and records the access change', function () {
    $shop = Shop::factory()->create(['plan_slug' => 'free', 'plan_expires_at' => null, 'trial_started_at' => now()]);
    expect($shop->activePlanSlug())->toBe('premium');
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->patch(route('admin.shops.plan', $shop), ['plan_slug' => 'free'])->assertRedirect();
    expect($shop->fresh()->activePlanSlug())->toBe('free');
    $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'changed_shop_plan']);
});
