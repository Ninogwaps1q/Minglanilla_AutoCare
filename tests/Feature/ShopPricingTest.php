<?php

use App\Models\Shop;
use App\Models\ShopPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.paymongo.legacy_manual_payments', true);
    config()->set('services.paymongo.secret_key', null);
    config()->set('services.paymongo.webhook_secret', null);
});

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

it('opens checkout from an older plan request link without creating a separate request', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active']);
    $basic = ShopPlan::query()->where('slug', 'basic')->firstOrFail();
    $this->actingAs($shop->owner)->post(route('shop.pricing.request', $basic))->assertRedirect(route('shop.pricing', ['plan' => 'basic']).'#plan-payment');
    expect($shop->fresh()->requested_plan)->toBeNull();

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

it('shows the selected plan payment details without first requesting a plan', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'free', 'plan_expires_at' => null]);
    ShopPlan::query()->where('slug', 'basic')->firstOrFail()->update(['payment_instructions' => 'Basic transfer instructions']);
    ShopPlan::query()->where('slug', 'premium')->firstOrFail()->update([
        'payment_instructions' => 'Premium transfer instructions',
        'payment_account_name' => 'AutoCare Billing',
        'payment_account_number' => '001234567890',
    ]);

    $this->actingAs($shop->owner)->get(route('shop.pricing', ['plan' => 'premium']))
        ->assertOk()->assertSee('Pay for Premium')->assertSee('Premium transfer instructions')
        ->assertSee('AutoCare Billing')->assertSee('001234567890')->assertDontSee('Basic transfer instructions')
        ->assertSee('name="plan_slug" value="premium"', false)->assertDontSee('Request Premium');
    expect($shop->fresh()->requested_plan)->toBeNull();
    $this->assertDatabaseCount('shop_plan_payments', 0);
});

it('preserves the selected plan and reference when payment validation fails', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active']);
    ShopPlan::query()->where('slug', 'premium')->firstOrFail()->update(['payment_instructions' => 'Premium payment details']);

    $this->actingAs($shop->owner)->from(route('shop.pricing', ['plan' => 'premium']))
        ->post(route('shop.pricing.payments.store'), ['plan_slug' => 'premium', 'reference' => str_repeat('1', 101)])
        ->assertSessionHasErrors('reference');
    $this->get(route('shop.pricing'))->assertSee('Pay for Premium')->assertSee(str_repeat('1', 101));
    $this->assertDatabaseCount('shop_plan_payments', 0);
});

it('selects the current paid plan by default when an owner renews', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'premium', 'plan_expires_at' => now()->addDays(7)]);
    ShopPlan::query()->where('slug', 'premium')->firstOrFail()->update(['payment_instructions' => 'Premium renewal instructions']);

    $this->actingAs($shop->owner)->get(route('shop.pricing'))->assertSee('Pay for Premium')->assertSee('Current plan: Premium')->assertSee('7 days left');
});

it('lets admins replace and remove payment QR images without exposing them to customers', function () {
    Storage::fake('local');
    $plan = ShopPlan::query()->where('slug', 'basic')->firstOrFail();
    $payload = ['monthly_price' => 499, 'summary' => $plan->summary, 'features' => $plan->features,
        'payment_account_name' => 'AutoCare Billing', 'payment_account_number' => '001234567890', 'payment_instructions' => 'Use the test bank.'];
    $admin = User::factory()->create(['role' => 'admin']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $image = fn (): UploadedFile => UploadedFile::fake()->createWithContent('qr.png', file_get_contents(public_path('images/Minglanilla AutoCare Hub Logo.png')));

    $this->actingAs($owner)->put(route('admin.shop-plans.update', $plan), $payload + ['payment_qr' => $image()])->assertForbidden();
    Storage::disk('local')->assertDirectoryEmpty('shop-plan-qrs');
    $this->actingAs($admin)->put(route('admin.shop-plans.update', $plan), $payload + ['payment_qr' => $image()])->assertSessionHasNoErrors();
    $firstPath = $plan->fresh()->payment_qr_path;
    Storage::disk('local')->assertExists($firstPath);
    expect($plan->fresh()->payment_account_number)->toBe('001234567890');
    $this->get(route('shop.pricing.qr', $plan))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($owner)->get(route('shop.pricing.qr', $plan))->assertOk();
    $this->actingAs(User::factory()->create(['role' => 'customer']))->get(route('shop.pricing.qr', $plan))->assertForbidden();

    $this->actingAs($admin)->put(route('admin.shop-plans.update', $plan), $payload + ['payment_qr' => $image()])->assertSessionHasNoErrors();
    $secondPath = $plan->fresh()->payment_qr_path;
    Storage::disk('local')->assertMissing($firstPath);
    Storage::disk('local')->assertExists($secondPath);
    $this->put(route('admin.shop-plans.update', $plan), $payload + ['remove_payment_qr' => 1])->assertSessionHasNoErrors();
    expect($plan->fresh()->payment_qr_path)->toBeNull();
    Storage::disk('local')->assertMissing($secondPath);
    $this->get(route('shop.pricing.qr', $plan))->assertNotFound();
});

it('rejects unsafe or oversized QR uploads and incomplete account details while keeping the existing QR', function () {
    Storage::fake('local');
    Storage::disk('local')->put('shop-plan-qrs/existing.png', 'existing');
    $plan = ShopPlan::query()->where('slug', 'basic')->firstOrFail();
    $plan->update(['payment_qr_path' => 'shop-plan-qrs/existing.png']);
    $payload = ['monthly_price' => 499, 'summary' => $plan->summary, 'features' => $plan->features];
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $this->put(route('admin.shop-plans.update', $plan), $payload + [
        'payment_qr' => UploadedFile::fake()->createWithContent('qr.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    ])->assertSessionHasErrors('payment_qr');
    $this->put(route('admin.shop-plans.update', $plan), $payload + [
        'payment_qr' => UploadedFile::fake()->createWithContent('large.png', file_get_contents(public_path('images/Minglanilla AutoCare Hub Logo.png')).str_repeat('0', 2_100_000)),
    ])->assertSessionHasErrors('payment_qr');
    $this->put(route('admin.shop-plans.update', $plan), $payload + ['payment_account_name' => 'AutoCare Billing'])->assertSessionHasErrors('payment_account_number');
    expect($plan->fresh()->payment_qr_path)->toBe('shop-plan-qrs/existing.png');
    Storage::disk('local')->assertExists('shop-plan-qrs/existing.png');
    Storage::disk('local')->assertCount('shop-plan-qrs', 1);
});
