<?php

use App\Http\Controllers\Webhooks\PayMongoWebhookController;
use App\Models\ActivityLog;
use App\Models\Shop;
use App\Models\ShopPlanPayment;
use App\Models\User;
use App\Services\PayMongoRefunds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 11, 19)->setTime(10, 0));
    $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    config(['services.paymongo.secret_key' => 'sk_test_example', 'services.paymongo.webhook_secret' => 'whsec_example']);
    Http::preventStrayRequests();
});

it('repairs a prematurely recorded billing migration without removing existing payments', function () {
    $shop = Shop::factory()->create(['plan_slug' => 'basic', 'plan_expires_at' => now()->addMonth(), 'trial_started_at' => null]);
    $payment = $shop->planPayments()->create(['plan_slug' => 'basic', 'amount' => 499, 'status' => 'approved', 'period_starts_at' => now()->subDay(), 'period_ends_at' => now()->addMonth(), 'paymongo_payment_id' => 'pay_existing']);
    $original = require database_path('migrations/2026_11_19_024050_add_subscription_billing_controls.php');
    $original->down();
    $repair = require database_path('migrations/2026_11_19_031825_add_refund_status_to_shop_plan_payments_table.php');
    $repair->up();
    $repair->up();

    expect($shop->fresh()->plan_payment_id)->toBe($payment->id)->and($shop->fresh()->activePlanSlug())->toBe('basic');
    $this->assertDatabaseCount('shop_plan_payments', 1);
    $this->actingAs($shop->owner)->post(route('shop.pricing.refund', $payment), ['reason' => 'Review existing payment'])->assertSessionHasNoErrors();
    expect($payment->fresh()->refund_status)->toBe('requested')->and($payment->fresh()->refund_request_key)->not->toBeNull();
    $this->get(route('shop.pricing'))->assertOk();
});

it('preserves paid days and switches plans only when the next billing period starts', function (string $current, string $next) {
    $endsAt = now()->addDays(10);
    $shop = Shop::factory()->create(['plan_slug' => $current, 'plan_expires_at' => $endsAt, 'trial_started_at' => null]);
    $payment = subscriptionPayment($shop, $next);

    expect(app(PayMongoWebhookController::class)->processSession(subscriptionSession($payment))->getStatusCode())->toBe(200);
    $payment->refresh();
    expect($shop->fresh()->activePlanSlug())->toBe($current)
        ->and($shop->fresh()->activePlanEndsAt()->equalTo($endsAt))->toBeTrue()
        ->and($payment->period_starts_at->equalTo($endsAt))->toBeTrue();

    $this->travelTo($endsAt);
    expect($shop->fresh()->activePlanSlug())->toBe($next)
        ->and($shop->fresh()->activePlanEndsAt()->equalTo($payment->period_ends_at))->toBeTrue()
        ->and(Shop::query()->withOnlineBookings()->whereKey($shop->id)->exists())->toBeTrue();
    $this->artisan('app:sync-shop-subscriptions')->assertSuccessful();
    expect($shop->fresh()->plan_payment_id)->toBe($payment->id)->and($shop->fresh()->plan_slug)->toBe($next);

    $this->travelTo($payment->period_ends_at);
    expect($shop->fresh()->activePlanSlug())->toBe('free')
        ->and(Shop::query()->withOnlineBookings()->whereKey($shop->id)->exists())->toBeFalse();
})->with([['basic', 'premium'], ['premium', 'basic'], ['premium', 'premium']]);

it('queues consecutive months after the trial without overlap or duplicate webhook extensions', function () {
    $shop = Shop::factory()->create(['plan_slug' => 'free', 'plan_expires_at' => null, 'trial_started_at' => now()->subDays(20)]);
    $first = subscriptionPayment($shop, 'premium');
    $second = subscriptionPayment($shop, 'basic');
    $processor = app(PayMongoWebhookController::class);
    $processor->processSession(subscriptionSession($first));
    $processor->processSession(subscriptionSession($second));
    $processor->processSession(subscriptionSession($second));

    expect($first->fresh()->period_starts_at->equalTo($shop->trialEndsAt()))->toBeTrue()
        ->and($second->fresh()->period_starts_at->equalTo($first->fresh()->period_ends_at))->toBeTrue()
        ->and($shop->fresh()->plan_slug)->toBe('free')
        ->and($shop->fresh()->activePlanSlug())->toBe('premium');
    $this->actingAs($shop->owner)->get(route('shop.pricing'))->assertOk()->assertSee('Upcoming paid coverage');
    $this->artisan('app:send-shop-plan-reminders')->assertSuccessful();
    expect($shop->owner->notifications()->where('type', 'shop_plan_reminder')->count())->toBe(0);
});

it('requires admin approval of a full refund and removes only that paid period after confirmation', function () {
    $shop = Shop::factory()->create(['plan_slug' => 'free', 'plan_expires_at' => null, 'trial_started_at' => null]);
    $payment = subscriptionPayment($shop, 'premium');
    app(PayMongoWebhookController::class)->processSession(subscriptionSession($payment));
    $next = subscriptionPayment($shop, 'basic');
    app(PayMongoWebhookController::class)->processSession(subscriptionSession($next));
    $nextStarts = $next->fresh()->period_starts_at;

    $this->actingAs($shop->owner)->post(route('shop.pricing.refund', $payment), ['reason' => 'Plan no longer needed', 'amount' => 1])->assertSessionHasErrors('amount');
    $this->post(route('shop.pricing.refund', $payment), ['reason' => 'Plan no longer needed'])->assertSessionHasNoErrors();
    expect($payment->fresh()->refund_amount)->toBe('499.00')->and($shop->fresh()->activePlanSlug())->toBe('premium');
    $this->patch(route('admin.subscription-billing.refund', $payment), ['decision' => 'approved'])->assertForbidden();
    Http::fake(['api.paymongo.com/v1/refunds' => Http::response(['data' => subscriptionRefund($payment->fresh(), 'processing')])]);
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->patch(route('admin.subscription-billing.refund', $payment), ['decision' => 'approved', 'notes' => 'Approved in full'])->assertSessionHasNoErrors();
    $this->get(route('admin.subscription-billing.index'))->assertOk()->assertSee('Approved in full')->assertSee('Check refund status');
    expect($shop->fresh()->activePlanSlug())->toBe('premium')->and($payment->fresh()->refund_reviewed_by)->toBe($admin->id);
    Http::assertSent(fn ($request): bool => $request['data']['attributes']['amount'] === 49900 && filled($request->header('Idempotency-Key')[0]));
    subscriptionRefundWebhook($this, subscriptionRefund($payment->fresh(), 'succeeded'))->assertOk();
    subscriptionRefundWebhook($this, subscriptionRefund($payment->fresh(), 'processing'))->assertOk();
    expect($shop->fresh()->activePlanSlug())->toBe('free')
        ->and($payment->fresh()->refund_status)->toBe('succeeded')
        ->and($next->fresh()->period_starts_at->equalTo($nextStarts))->toBeTrue();
    $this->travelTo($nextStarts);
    expect($shop->fresh()->activePlanSlug())->toBe('basic');
    expect(ActivityLog::query()->where('action', 'refund_approved')->where('user_id', $admin->id)->count())->toBe(1);
});

it('keeps the current paid plan when a future subscription is refunded', function () {
    $shop = Shop::factory()->create(['plan_slug' => 'premium', 'plan_expires_at' => now()->addDays(12), 'trial_started_at' => null]);
    $payment = subscriptionPayment($shop, 'basic');
    app(PayMongoWebhookController::class)->processSession(subscriptionSession($payment));
    $this->actingAs($shop->owner)->post(route('shop.pricing.refund', $payment), ['reason' => 'Cancel next month'])->assertSessionHasNoErrors();
    Http::fake(['api.paymongo.com/v1/refunds' => Http::response(['data' => subscriptionRefund($payment->fresh(), 'succeeded')])]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.subscription-billing.refund', $payment), ['decision' => 'approved'])->assertSessionHasNoErrors();
    expect($shop->fresh()->activePlanSlug())->toBe('premium')->and($shop->fresh()->next_plan_starts_at)->toBeNull();
});

it('claims refund approval before contacting the gateway and rejects a competing approval', function () {
    $shop = Shop::factory()->create(['plan_slug' => 'free', 'plan_expires_at' => null]);
    $payment = subscriptionPayment($shop, 'basic');
    app(PayMongoWebhookController::class)->processSession(subscriptionSession($payment));
    $this->actingAs($shop->owner)->post(route('shop.pricing.refund', $payment), ['reason' => 'Cancel'])->assertSessionHasNoErrors();
    $admin = User::factory()->create(['role' => 'admin']);
    Http::fake(function () use ($payment, $admin) {
        expect($payment->fresh()->refund_status)->toBe('submitting');
        expect(fn () => app(PayMongoRefunds::class)->review($payment, $admin->id, 'approved', null))->toThrow(ValidationException::class);

        return Http::response(['data' => subscriptionRefund($payment->fresh(), 'processing')]);
    });
    $this->actingAs($admin)->patch(route('admin.subscription-billing.refund', $payment), ['decision' => 'approved'])->assertSessionHasNoErrors();
    Http::assertSentCount(1);
});

it('holds uncertain refunds and recovers them only with a matching gateway refund', function () {
    $shop = Shop::factory()->create(['plan_slug' => 'free', 'plan_expires_at' => null]);
    $payment = subscriptionPayment($shop, 'basic');
    app(PayMongoWebhookController::class)->processSession(subscriptionSession($payment));
    $this->actingAs($shop->owner)->post(route('shop.pricing.refund', $payment), ['reason' => 'Cancel'])->assertSessionHasNoErrors();
    Http::fake(['api.paymongo.com/v1/refunds' => Http::response([], 503)]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.subscription-billing.refund', $payment), ['decision' => 'approved'])->assertSessionHasErrors('refund');
    expect($payment->fresh()->refund_status)->toBe('needs_review')->and($shop->fresh()->activePlanSlug())->toBe('basic');
    $this->get(route('admin.subscription-billing.index'))->assertOk()->assertSee('PayMongo refund ID')->assertSee('Do not send another refund.');
    $this->patch(route('admin.subscription-billing.refund', $payment), ['decision' => 'approved'])->assertSessionHasErrors('refund');
    Http::assertSentCount(1);

    $refund = subscriptionRefund($payment->fresh(), 'succeeded');
    Http::fake(['api.paymongo.com/v1/refunds/*' => Http::sequence()->push(['data' => array_replace_recursive($refund, ['attributes' => ['amount' => 100]])])->push(['data' => $refund])]);
    $this->post(route('admin.subscription-billing.check', $payment), ['refund_id' => $refund['id']])->assertSessionHasErrors('refund');
    expect($shop->fresh()->activePlanSlug())->toBe('basic');
    $this->post(route('admin.subscription-billing.check', $payment), ['refund_id' => $refund['id']])->assertSessionHasNoErrors();
    expect($shop->fresh()->activePlanSlug())->toBe('free');
});

it('does not remove paid coverage for rejected or failed refunds', function (string $result) {
    $shop = Shop::factory()->create(['plan_slug' => 'free', 'plan_expires_at' => null]);
    $payment = subscriptionPayment($shop, 'premium');
    app(PayMongoWebhookController::class)->processSession(subscriptionSession($payment));
    $this->actingAs($shop->owner)->post(route('shop.pricing.refund', $payment), ['reason' => 'Cancel'])->assertSessionHasNoErrors();
    Http::fake(['api.paymongo.com/*' => Http::response(['errors' => [['code' => 'payment_not_refundable']]], 422)]);
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.subscription-billing.refund', $payment), ['decision' => $result === 'rejected' ? 'rejected' : 'approved']);
    $result === 'rejected' ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('refund');
    expect($shop->fresh()->activePlanSlug())->toBe('premium')->and($payment->fresh()->refund_status)->toBe($result);
})->with(['rejected', 'failed']);

it('prevents a new trial or a manual override from consuming existing paid coverage', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'free', 'plan_expires_at' => null, 'trial_started_at' => null]);
    $payment = subscriptionPayment($shop, 'basic');
    app(PayMongoWebhookController::class)->processSession(subscriptionSession($payment));
    $this->actingAs($shop->owner)->post(route('shop.pricing.trial'))->assertSessionHasErrors('trial');
    expect($shop->fresh()->trial_started_at)->toBeNull()->and($shop->fresh()->activePlanSlug())->toBe('basic');
    $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.shops.plan', $shop), ['plan_slug' => 'free'])->assertSessionHasErrors('plan');
    expect($shop->fresh()->activePlanSlug())->toBe('basic');
});

it('protects other owners receipts and refunds and exports dated billing without spreadsheet formulas', function () {
    $shop = Shop::factory()->create(['name' => '=UNTRUSTED()', 'plan_slug' => 'free', 'plan_expires_at' => null]);
    $payment = subscriptionPayment($shop, 'basic');
    app(PayMongoWebhookController::class)->processSession(subscriptionSession($payment));
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    $this->actingAs($otherOwner)->get(route('shop.pricing.receipt', $payment))->assertNotFound();
    $this->post(route('shop.pricing.refund', $payment), ['reason' => 'Cancel'])->assertNotFound();
    $this->get(route('admin.subscription-billing.index'))->assertForbidden();
    $this->get(route('admin.subscription-billing.export'))->assertForbidden();
    $this->actingAs($shop->owner)->get(route('shop.pricing.receipt', $payment))->assertOk()->assertHeader('content-type', 'application/pdf')->assertDownload('subscription-'.$payment->id.'.pdf');
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.subscription-billing.index'))->assertOk()->assertViewHas('gross', 499)->assertViewHas('refunded', 0)->assertSee('Billing report');
    $csv = $this->get(route('admin.subscription-billing.export'))->assertOk()->streamedContent();
    expect($csv)->toContain("'=UNTRUSTED()")->toContain('499')->toContain('Current unsettled invoice payments');
    $this->get(route('admin.subscription-billing.index', ['from' => '2026-11-20', 'to' => '2026-11-19']))->assertSessionHasErrors('to');
    $this->get(route('admin.subscription-billing.index', ['from' => '2026-11-01', 'to' => '2026-11-18']))->assertViewHas('gross', 0);
});

/** @return array<string, mixed> */
function subscriptionSession(ShopPlanPayment $payment): array
{
    return ['id' => $payment->paymongo_session_id, 'attributes' => ['reference_number' => 'shop-plan-'.$payment->id, 'payments' => [['id' => 'pay_subscription_'.$payment->id, 'attributes' => ['status' => 'paid', 'amount' => $payment->amount * 100, 'currency' => 'PHP', 'source' => ['type' => 'gcash']]]]]];
}

function subscriptionPayment(Shop $shop, string $plan): ShopPlanPayment
{
    return $shop->planPayments()->create(['plan_slug' => $plan, 'amount' => 499, 'status' => 'checkout_pending', 'paymongo_session_id' => 'cs_'.fake()->uuid()]);
}

/** @return array<string, mixed> */
function subscriptionRefund(ShopPlanPayment $payment, string $status): array
{
    return ['id' => 'ref_subscription_'.$payment->id, 'attributes' => ['payment_id' => $payment->paymongo_payment_id, 'amount' => 49900, 'currency' => 'PHP', 'status' => $status]];
}

function subscriptionRefundWebhook(TestCase $test, array $resource): TestResponse
{
    $body = json_encode(['data' => ['attributes' => ['type' => 'payment.refund.updated', 'livemode' => false, 'data' => $resource]]], JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_example');

    return $test->call('POST', route('webhooks.paymongo'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => 't='.$timestamp.',te='.$signature], $body);
}
