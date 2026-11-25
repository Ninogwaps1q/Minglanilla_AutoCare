<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    config(['services.paymongo.secret_key' => 'sk_test_example', 'services.paymongo.webhook_secret' => 'whsec_example']);
    Http::preventStrayRequests();
});

it('recovers invoice and subscription payments without a webhook or open browser and does not duplicate confirmation', function () {
    [$payment, $shop] = reconciliationInvoice();
    $subscription = $shop->planPayments()->create(['plan_slug' => 'basic', 'amount' => 499, 'status' => 'checkout_pending', 'paymongo_session_id' => 'cs_background_shop']);
    Http::fake([
        'api.paymongo.com/v1/checkout_sessions/cs_background_invoice' => Http::response(['data' => reconciliationSession('cs_background_invoice', 'invoice-'.$payment->id, 'pay_background_invoice', 50000)]),
        'api.paymongo.com/v1/checkout_sessions/cs_background_shop' => Http::response(['data' => reconciliationSession('cs_background_shop', 'shop-plan-'.$subscription->id, 'pay_background_shop', 49900)]),
    ]);
    $this->artisan('app:reconcile-paymongo-payments')->assertSuccessful();
    $this->travel(6)->minutes();
    $this->artisan('app:reconcile-paymongo-payments')->assertSuccessful();
    expect($payment->fresh()->status)->toBe('confirmed')->and($payment->invoice->fresh()->status)->toBe('paid')
        ->and($shop->fresh()->activePlanSlug())->toBe('basic')->and($subscription->fresh()->status)->toBe('approved')
        ->and($payment->customer->notifications()->where('type', 'payment_verification')->count())->toBe(1);
    Http::assertSentCount(2);
});

it('leaves transient failures and amount mismatches pending and cancels only confirmed expired sessions', function () {
    [, $shop] = reconciliationInvoice();
    $expired = $shop->planPayments()->create(['plan_slug' => 'basic', 'amount' => 499, 'status' => 'checkout_pending', 'paymongo_session_id' => 'cs_expired']);
    $wrong = $shop->planPayments()->create(['plan_slug' => 'premium', 'amount' => 999, 'status' => 'checkout_pending', 'paymongo_session_id' => 'cs_wrong']);
    Http::fake([
        'api.paymongo.com/v1/checkout_sessions/cs_background_invoice' => Http::response([], 503),
        'api.paymongo.com/v1/checkout_sessions/cs_expired' => Http::response(['data' => ['id' => 'cs_expired', 'attributes' => ['status' => 'expired', 'payments' => []]]]),
        'api.paymongo.com/v1/checkout_sessions/cs_wrong' => Http::response(['data' => reconciliationSession('cs_wrong', 'shop-plan-'.$wrong->id, 'pay_wrong', 100)]),
    ]);
    $this->artisan('app:reconcile-paymongo-payments')->assertFailed();
    expect($expired->fresh()->status)->toBe('cancelled')->and($wrong->fresh()->status)->toBe('checkout_pending')
        ->and(Payment::query()->sole()->status)->toBe('checkout_pending')->and($shop->fresh()->activePlanSlug())->toBe('free');
});

it('notifies about one declined checkout attempt without closing a session that can be retried', function () {
    [$payment] = reconciliationInvoice();
    Http::fake(['api.paymongo.com/v1/checkout_sessions/*' => Http::response(['data' => [
        'id' => $payment->paymongo_session_id,
        'attributes' => [
            'status' => 'active', 'payments' => [],
            'payment_intent' => ['id' => 'pi_failed_attempt', 'attributes' => [
                'status' => 'awaiting_payment_method', 'last_payment_error' => ['code' => 'card_declined'],
            ]],
        ],
    ]])]);

    $this->artisan('app:reconcile-paymongo-payments')->assertSuccessful();
    $this->travel(6)->minutes();
    $this->artisan('app:reconcile-paymongo-payments')->assertSuccessful();

    expect($payment->fresh()->status)->toBe('checkout_pending');
    expect($payment->customer->notifications()->where('type', 'payment_verification')->sole()->title)->toBe('Payment needs attention');
});

it('bounds background checks and rotates past recently checked records', function () {
    [, $shop] = reconciliationInvoice();
    $subscription = $shop->planPayments()->create(['plan_slug' => 'basic', 'amount' => 499, 'status' => 'checkout_pending', 'paymongo_session_id' => 'cs_waiting']);
    Http::fake(['api.paymongo.com/*' => Http::response([], 503)]);
    $this->artisan('app:reconcile-paymongo-payments', ['--limit' => 1])->assertFailed();
    $this->artisan('app:reconcile-paymongo-payments', ['--limit' => 1])->assertFailed();
    $this->artisan('app:reconcile-paymongo-payments', ['--limit' => 1])->assertSuccessful();
    Http::assertSentCount(2);
    expect($subscription->fresh()->paymongo_checked_at)->not->toBeNull();
});

it('recovers processing refunds and flags interrupted submissions without sending another refund', function () {
    [$payment, $shop] = reconciliationInvoice();
    $payment->update(['status' => 'confirmed', 'paymongo_payment_id' => 'pay_background_refund', 'paymongo_refund_id' => 'ref_background', 'refund_status' => 'processing', 'refund_amount' => 500]);
    $payment->invoice->update(['status' => 'paid']);
    $subscription = $shop->planPayments()->create(['plan_slug' => 'premium', 'amount' => 999, 'status' => 'approved', 'paymongo_payment_id' => 'pay_interrupted', 'refund_status' => 'submitting', 'refund_amount' => 999, 'refund_reviewed_at' => now()->subMinutes(11)]);
    Http::fake(['api.paymongo.com/v1/refunds/ref_background' => Http::response(['data' => ['id' => 'ref_background', 'attributes' => ['payment_id' => 'pay_background_refund', 'amount' => 50000, 'currency' => 'PHP', 'status' => 'succeeded']]])]);
    $this->artisan('app:reconcile-paymongo-payments')->assertSuccessful();
    expect($payment->fresh()->refund_status)->toBe('succeeded')->and($payment->invoice->fresh()->status)->toBe('unpaid')
        ->and($subscription->fresh()->refund_status)->toBe('needs_review');
    Http::assertSentCount(1);
    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST');
});

it('prevents settlement while refund submission or confirmation is unresolved', function (string $status) {
    Storage::fake('local');
    [$payment] = reconciliationInvoice();
    $payment->update(['status' => 'confirmed', 'paymongo_payment_id' => 'pay_hold', 'settlement_status' => 'pending', 'paymongo_fee' => 0, 'paymongo_net_amount' => 500, 'refund_status' => $status, 'refund_amount' => 100]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.paymongo-settlements.reserve', $payment), ['settlement_amount' => 500, 'platform_fee' => 0])->assertSessionHasErrors('settlement_amount');
    expect($payment->fresh()->settlement_status)->toBe('pending')->and($payment->fresh()->settlement_proof_path)->toBeNull();
})->with(['requested', 'submitting', 'needs_review', 'pending', 'processing']);

it('does not request or approve a refund after a shop settlement wins the lock', function () {
    [$payment] = reconciliationInvoice();
    $payment->update(['status' => 'confirmed', 'paymongo_payment_id' => 'pay_settled', 'settlement_status' => 'settled']);
    $this->actingAs($payment->customer)->post(route('customer.payments.refund.request', $payment), ['reason' => 'Cancel', 'amount' => 100])->assertSessionHasErrors('refund');
    $payment->update(['refund_status' => 'requested', 'refund_amount' => 100]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.paymongo-refunds.update', $payment), ['decision' => 'approved', 'refund_resolution' => 'replacement'])->assertSessionHasErrors('refund');
    Http::assertNothingSent();
});

it('reports refunds in their completion period and separates unsettled balances with missing fees', function () {
    [$payment, $shop] = reconciliationInvoice();
    $payment->update(['status' => 'confirmed', 'paymongo_payment_id' => 'pay_report', 'settlement_status' => 'pending', 'paymongo_fee' => 10, 'paymongo_net_amount' => 490, 'platform_fee' => 20, 'refund_status' => 'succeeded', 'refund_amount' => 100]);
    $shop->planPayments()->create(['plan_slug' => 'basic', 'amount' => 499, 'status' => 'approved', 'reviewed_at' => now()->subMonths(2), 'refund_status' => 'succeeded', 'refund_amount' => 499, 'refunded_at' => now()]);
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.subscription-billing.index'));
    $response->assertOk()->assertViewHas('gross', 0)->assertViewHas('refunded', 499)->assertViewHas('unsettledBalance', 370)->assertViewHas('unknownFees', 0);
    $payment->update(['paymongo_net_amount' => null]);
    $this->get(route('admin.subscription-billing.index'))->assertOk()->assertViewHas('unknownFees', 1)->assertViewHas('unsettledBalance', 0);
});

/** @return array{Payment, Shop} */
function reconciliationInvoice(): array
{
    $shop = Shop::factory()->create(['plan_slug' => 'free', 'plan_expires_at' => null, 'trial_started_at' => null]);
    $customer = User::factory()->create(['role' => 'customer']);
    $invoice = Invoice::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'invoice_number' => 'INV-BACKGROUND', 'subtotal' => 500, 'total' => 500, 'status' => 'unpaid']);
    $payment = $invoice->payments()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'amount' => 500, 'payment_method' => 'paymongo', 'payment_date' => now(), 'status' => 'checkout_pending', 'paymongo_session_id' => 'cs_background_invoice']);

    return [$payment, $shop];
}

/** @return array<string, mixed> */
function reconciliationSession(string $sessionId, string $reference, string $paymentId, int $amount): array
{
    return ['id' => $sessionId, 'attributes' => ['reference_number' => $reference, 'payments' => [['id' => $paymentId, 'attributes' => ['status' => 'paid', 'amount' => $amount, 'currency' => 'PHP', 'fee' => 100, 'net_amount' => $amount - 100, 'source' => ['type' => 'gcash']]]]]];
}
