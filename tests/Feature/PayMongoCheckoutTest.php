<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\ShopPlanPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    config()->set('services.paymongo.secret_key', 'sk_test_example');
    config()->set('services.paymongo.webhook_secret', 'whsec_example');
    config()->set('services.paymongo.payment_methods', ['card', 'gcash', 'shopee_pay', 'qrph']);
    config()->set('services.paymongo.legacy_manual_payments', false);
    Http::preventStrayRequests();
});

it('starts one PayMongo checkout for an approved shop and reuses the session', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'free', 'plan_expires_at' => null]);
    Http::fake(['api.paymongo.com/*' => Http::response(['data' => ['id' => 'cs_shop_1', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_shop_1']]])]);

    $this->actingAs($shop->owner)->post(route('shop.pricing.checkout'), ['plan_slug' => 'basic'])->assertRedirect('https://checkout.paymongo.com/cs_shop_1');
    $payment = ShopPlanPayment::query()->sole();
    expect($payment->status)->toBe('checkout_pending');
    expect($payment->amount)->toBe(499);
    $this->post(route('shop.pricing.checkout'), ['plan_slug' => 'basic'])->assertRedirect('https://checkout.paymongo.com/cs_shop_1');
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['data']['attributes']['line_items'][0]['amount'] === 49900
        && $request['data']['attributes']['payment_method_types'] === ['card', 'gcash', 'shopee_pay', 'qrph']
        && $request->hasHeader('Idempotency-Key', 'shop-plan-'.$payment->id));
    $this->post(route('shop.pricing.checkout'), ['plan_slug' => 'premium'])->assertSessionHasErrors('payment');
});

it('sends the valid ShopeePay method once when configuration contains the old spelling', function () {
    [$invoice, $customer] = payMongoInvoiceFixture();
    config()->set('services.paymongo.payment_methods', ['card', ' shopeepay ', 'shopee_pay', '', 'qrph']);
    Http::fake(['https://api.paymongo.com/v2/checkout_sessions' => Http::response(['data' => ['id' => 'cs_shopee', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_shopee']]])]);

    $this->actingAs($customer)->post(route('customer.payments.checkout', $invoice))->assertRedirect('https://checkout.paymongo.com/cs_shopee');

    Http::assertSent(fn ($request) => $request['data']['attributes']['payment_method_types'] === ['card', 'shopee_pay', 'qrph']);
    expect($invoice->payments()->sole()->paymongo_session_id)->toBe('cs_shopee');
});

it('activates a paid plan only after a valid signed paid webhook and ignores duplicates', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'free', 'plan_expires_at' => null]);
    $payment = $shop->planPayments()->create(['plan_slug' => 'basic', 'amount' => 499, 'status' => 'checkout_pending', 'paymongo_session_id' => 'cs_shop_2']);
    $event = payMongoPaidEvent('shop-plan-'.$payment->id, 'cs_shop_2', 'pay_shop_2', 49900);

    payMongoWebhook($this, $event, false)->assertUnauthorized();
    expect($shop->fresh()->activePlanSlug())->toBe('free');
    payMongoWebhook($this, $event)->assertOk();
    payMongoWebhook($this, $event)->assertOk();
    expect($shop->fresh()->activePlanSlug())->toBe('basic');
    expect($payment->fresh()->period_ends_at)->not->toBeNull();
    expect($payment->fresh()->paymongo_method)->toBe('gcash');
    expect($shop->owner->notifications()->where('type', 'shop_plan_payment')->count())->toBe(1);
});

it('rejects webhook amount mismatches without granting access', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'free', 'plan_expires_at' => null]);
    $payment = $shop->planPayments()->create(['plan_slug' => 'basic', 'amount' => 499, 'status' => 'checkout_pending', 'paymongo_session_id' => 'cs_shop_3']);

    payMongoWebhook($this, payMongoPaidEvent('shop-plan-'.$payment->id, 'cs_shop_3', 'pay_shop_3', 100))->assertStatus(422);
    expect($payment->fresh()->status)->toBe('checkout_pending');
    payMongoWebhook($this, payMongoPaidEvent('shop-plan-'.$payment->id, 'cs_other_session', 'pay_shop_3', 49900))->assertStatus(422);
    expect($shop->fresh()->activePlanSlug())->toBe('free');
});

it('routes wallet payments through PayMongo when configured', function () {
    [$invoice, $customer] = payMongoInvoiceFixture();

    $this->actingAs($customer)->post(route('customer.payments.store', $invoice), ['amount' => 500, 'payment_method' => 'gcash', 'reference_number' => 'OFFLINE-1'])->assertSessionHasErrors('payment');
    expect(Payment::query()->count())->toBe(0);
});

it('does not offer manual payment when PayMongo setup is incomplete', function () {
    config()->set('services.paymongo.webhook_secret', null);
    [$invoice, $customer] = payMongoInvoiceFixture();
    $shop = $invoice->shop;

    $this->actingAs($customer)->get(route('customer.invoices.index'))->assertOk()->assertSee('PayMongo checkout is temporarily unavailable')->assertDontSee(route('customer.payments.store', $invoice), false);
    $this->post(route('customer.payments.store', $invoice), ['amount' => 500, 'payment_method' => 'cash'])->assertSessionHasErrors('payment');
    expect($invoice->payments()->count())->toBe(0);
    $this->actingAs($shop->owner)->get(route('shop.pricing'))->assertOk()->assertSee('PayMongo checkout is temporarily unavailable')->assertDontSee(route('shop.pricing.payments.store'), false);
    $this->post(route('shop.pricing.payments.store'), ['plan_slug' => 'basic', 'reference' => 'DIRECT-1'])->assertSessionHasErrors('payment');
    expect($shop->planPayments()->count())->toBe(0);
});

it('shows only PayMongo checkout for new customer and shop payments', function () {
    [$invoice, $customer] = payMongoInvoiceFixture();
    $this->actingAs($customer)->get(route('customer.invoices.index'))->assertOk()->assertSee('Pay ₱500.00 with PayMongo')->assertDontSee(route('customer.payments.store', $invoice), false);
    $this->post(route('customer.payments.store', $invoice), ['amount' => 500, 'payment_method' => 'cash'])->assertSessionHasErrors('payment');
    $this->actingAs($invoice->shop->owner)->get(route('shop.pricing'))->assertOk()->assertSee('with PayMongo')->assertDontSee(route('shop.pricing.payments.store'), false);
    $this->get(route('shop.profile.edit'))->assertOk()->assertDontSee('name="gcash_number"', false)->assertDontSee('name="bank_transfer_details"', false);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.shop-plans.index'))->assertOk()->assertDontSee('name="payment_qr"', false);
});

it('releases a rejected checkout attempt so the owner can try again', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'free', 'plan_expires_at' => null]);
    Http::fake(['api.paymongo.com/*' => Http::sequence()->push(['errors' => [['code' => 'invalid_payment_method']]], 422)->push(['data' => ['id' => 'cs_retry_shop', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_retry_shop']]], 200)]);

    $this->actingAs($shop->owner)->post(route('shop.pricing.checkout'), ['plan_slug' => 'basic'])->assertSessionHasErrors('payment');
    expect(ShopPlanPayment::query()->firstOrFail()->status)->toBe('failed');
    $this->post(route('shop.pricing.checkout'), ['plan_slug' => 'basic'])->assertRedirect('https://checkout.paymongo.com/cs_retry_shop');
    expect(ShopPlanPayment::query()->count())->toBe(2);
});

it('releases a rejected invoice checkout attempt for another try', function () {
    [$invoice, $customer] = payMongoInvoiceFixture();
    Http::fake(['api.paymongo.com/*' => Http::sequence()->push(['errors' => [['code' => 'invalid_payment_method']]], 422)->push(['data' => ['id' => 'cs_retry_invoice', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_retry_invoice']]], 200)]);

    $this->actingAs($customer)->post(route('customer.payments.checkout', $invoice))->assertSessionHasErrors('payment');
    expect(Payment::query()->firstOrFail()->status)->toBe('failed');
    $this->post(route('customer.payments.checkout', $invoice))->assertRedirect('https://checkout.paymongo.com/cs_retry_invoice');
    expect(Payment::query()->count())->toBe(2);
});

it('accepts the newer PayMongo checkout webhook envelope', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'free', 'plan_expires_at' => null]);
    $payment = $shop->planPayments()->create(['plan_slug' => 'basic', 'amount' => 499, 'status' => 'checkout_pending', 'paymongo_session_id' => 'cs_shop_new']);
    $legacy = payMongoPaidEvent('shop-plan-'.$payment->id, 'cs_shop_new', 'pay_shop_new', 49900);
    $event = ['event_type' => 'send.webhook', 'data' => ['type' => 'checkout_session.payment.paid', 'livemode' => false, 'data' => $legacy['data']['attributes']['data']]];

    payMongoWebhook($this, $event)->assertOk();
    expect($payment->fresh()->status)->toBe('approved');
});

it('expires a shop checkout before allowing another plan and keeps it pending on gateway refusal', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'free', 'plan_expires_at' => null, 'requested_plan' => 'basic']);
    $payment = $shop->planPayments()->create(['plan_slug' => 'basic', 'amount' => 499, 'status' => 'checkout_pending', 'paymongo_session_id' => 'cs_cancel_shop']);
    Http::fake(['api.paymongo.com/*' => Http::sequence()->push([], 400)->push(['data' => ['attributes' => ['status' => 'expired']]], 200)]);

    $this->actingAs($shop->owner)->post(route('shop.pricing.checkout.cancel', $payment))->assertSessionHasErrors('payment');
    expect($payment->fresh()->status)->toBe('checkout_pending');
    $this->post(route('shop.pricing.checkout.cancel', $payment))->assertRedirect(route('shop.pricing'));
    expect($payment->fresh()->status)->toBe('cancelled');
    expect($shop->fresh()->requested_plan)->toBeNull();
});

it('releases an invoice balance only after PayMongo expires its checkout', function () {
    [$invoice, $customer, $shop] = payMongoInvoiceFixture();
    $payment = $invoice->payments()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'amount' => 500, 'payment_method' => 'paymongo', 'payment_date' => now(), 'status' => 'checkout_pending', 'paymongo_session_id' => 'cs_cancel_invoice']);
    Http::fake(['api.paymongo.com/*' => Http::response(['data' => ['attributes' => ['status' => 'expired']]])]);

    $this->actingAs($shop->owner)->post(route('customer.payments.checkout.cancel', $payment))->assertForbidden();
    $this->actingAs($customer)->post(route('customer.payments.checkout.cancel', $payment))->assertRedirect(route('customer.invoices.index'));
    expect($payment->fresh()->status)->toBe('cancelled');
});

it('confirms a customer invoice online and keeps settlement pending for the admin', function () {
    [$invoice, $customer] = payMongoInvoiceFixture();
    Http::fake(['api.paymongo.com/*' => Http::response(['data' => ['id' => 'cs_invoice_1', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_invoice_1']]])]);

    $this->actingAs(User::factory()->create(['role' => 'customer']))->post(route('customer.payments.checkout', $invoice))->assertNotFound();
    $this->actingAs($customer)->post(route('customer.payments.checkout', $invoice))->assertRedirect('https://checkout.paymongo.com/cs_invoice_1');
    $payment = Payment::query()->sole();
    expect($payment->amount)->toBe('500.00');
    $this->post(route('customer.payments.checkout', $invoice))->assertRedirect('https://checkout.paymongo.com/cs_invoice_1');
    Http::assertSentCount(1);
    $event = payMongoPaidEvent('invoice-'.$payment->id, 'cs_invoice_1', 'pay_invoice_1', 50000);
    $event['data']['attributes']['data']['attributes']['payments'][0]['attributes']['fee'] = 1000;
    $event['data']['attributes']['data']['attributes']['payments'][0]['attributes']['net_amount'] = 49000;
    payMongoWebhook($this, $event)->assertOk();
    expect($invoice->fresh()->status)->toBe('paid');
    expect($payment->fresh()->settlement_status)->toBe('pending');
    expect($payment->fresh()->status)->toBe('confirmed');
    expect($payment->fresh()->paymongo_fee)->toBe('10.00');
    expect($payment->fresh()->paymongo_net_amount)->toBe('490.00');
    expect($payment->fresh()->paymongo_method)->toBe('gcash');
    $this->get(route('customer.invoices.index'))->assertOk()->assertSee('Confirmed by PayMongo');
    $this->actingAs($invoice->shop->owner)->get(route('shop.invoices.index'))->assertOk()->assertSee('Platform settlement:');
});

it('lets only an admin record a completed shop settlement once', function () {
    Storage::fake();
    [$invoice, $customer, $shop] = payMongoInvoiceFixture();
    $payment = $invoice->payments()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'amount' => 500, 'payment_method' => 'paymongo', 'payment_date' => now(), 'status' => 'confirmed', 'paymongo_payment_id' => 'pay_invoice_2', 'paymongo_fee' => 0, 'paymongo_net_amount' => 500, 'settlement_status' => 'pending']);
    $route = route('admin.paymongo-settlements.update', $payment);
    $this->actingAs($shop->owner)->patch($route, ['settlement_amount' => 480, 'settlement_reference' => 'TRANSFER-1'])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.paymongo-settlements.index'))->assertOk()->assertSee('pay_invoice_2');
    $reserve = route('admin.paymongo-settlements.reserve', $payment);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->post($reserve, ['settlement_amount' => 501, 'platform_fee' => 20])->assertSessionHasErrors('settlement_amount');
    $this->post($reserve, ['settlement_amount' => 480, 'platform_fee' => 20])->assertRedirect();
    $this->patch($route, ['settlement_reference' => 'TRANSFER-1', 'proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')])->assertRedirect();
    expect($payment->fresh()->settlement_status)->toBe('settled');
    expect($payment->fresh()->settlement_amount)->toBe('480.00');
    expect($payment->fresh()->platform_fee)->toBe('20.00');
    $this->actingAs($shop->owner)->get(route('admin.paymongo-settlements.proof', $payment))->assertOk();
    $this->actingAs(User::factory()->create(['role' => 'shop_owner']))->get(route('admin.paymongo-settlements.proof', $payment))->assertNotFound();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->patch($route, ['settlement_reference' => 'TRANSFER-1', 'proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')])->assertSessionHasErrors('payment');
});

it('rechecks a paid checkout and confirms only its customer invoice', function () {
    [$invoice, $customer, $shop] = payMongoInvoiceFixture();
    $payment = $invoice->payments()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'amount' => 500, 'payment_method' => 'paymongo', 'payment_date' => now(), 'status' => 'checkout_pending', 'paymongo_session_id' => 'cs_recheck']);
    $session = payMongoPaidEvent('invoice-'.$payment->id, 'cs_recheck', 'pay_recheck', 50000)['data']['attributes']['data'];
    Http::fake(['api.paymongo.com/*' => Http::response(['data' => $session])]);

    $this->actingAs(User::factory()->create(['role' => 'customer']))->post(route('customer.payments.checkout.check', $payment))->assertNotFound();
    $this->actingAs($customer)->post(route('customer.payments.checkout.check', $payment))->assertOk()->assertJsonPath('status', 'confirmed');
    expect($invoice->fresh()->status)->toBe('paid');
    $this->post(route('customer.payments.checkout.check', $payment))->assertOk()->assertJsonPath('status', 'confirmed');
    Http::assertSentCount(1);
});

it('holds settlement during a refund and lets the customer pay the refunded balance', function () {
    Storage::fake();
    [$invoice, $customer, $shop] = payMongoInvoiceFixture();
    $payment = $invoice->payments()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'amount' => 500, 'payment_method' => 'paymongo', 'payment_date' => now(), 'status' => 'confirmed', 'paymongo_payment_id' => 'pay_refund_1', 'paymongo_fee' => 0, 'paymongo_net_amount' => 500, 'settlement_status' => 'pending']);
    $invoice->update(['status' => 'paid']);
    $this->actingAs($customer)->post(route('customer.payments.refund.request', $payment), ['amount' => 100, 'reason' => 'Duplicate charge'])->assertRedirect();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.paymongo-settlements.reserve', $payment), ['settlement_amount' => 400, 'platform_fee' => 0])->assertSessionHasErrors('settlement_amount');
    Http::fake(['api.paymongo.com/*' => Http::response(['data' => ['id' => 'ref_example', 'attributes' => ['payment_id' => 'pay_refund_1', 'amount' => 10000, 'currency' => 'PHP', 'status' => 'succeeded']]])]);
    $this->patch(route('admin.paymongo-refunds.update', $payment), ['decision' => 'approved', 'refund_resolution' => 'replacement'])->assertRedirect();
    expect($payment->fresh()->refund_status)->toBe('succeeded');
    expect($invoice->fresh()->status)->toBe('unpaid');
    expect($invoice->fresh()->remainingBalance())->toBe(100.0);
    $this->actingAs($customer)->post(route('customer.payments.checkout', $invoice))->assertRedirect();
    expect(Payment::query()->latest('id')->firstOrFail()->amount)->toBe('100.00');
});

it('alerts admins once for stale online checkouts', function () {
    [$invoice, $customer, $shop] = payMongoInvoiceFixture();
    $admin = User::factory()->create(['role' => 'admin']);
    $payment = $invoice->payments()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'amount' => 500, 'payment_method' => 'paymongo', 'payment_date' => now()->subHour(), 'status' => 'checkout_pending', 'paymongo_session_id' => 'cs_stale']);
    $payment->forceFill(['created_at' => now()->subHour()])->save();

    $this->artisan('app:alert-stale-paymongo-payments')->assertSuccessful();
    $this->artisan('app:alert-stale-paymongo-payments')->assertSuccessful();
    expect($admin->notifications()->where('type', 'paymongo_alert')->count())->toBe(1);
});

it('lets the owner recheck a paid shop plan checkout', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'plan_slug' => 'free', 'plan_expires_at' => null]);
    $payment = $shop->planPayments()->create(['plan_slug' => 'basic', 'amount' => 499, 'status' => 'checkout_pending', 'paymongo_session_id' => 'cs_shop_recheck']);
    $session = payMongoPaidEvent('shop-plan-'.$payment->id, 'cs_shop_recheck', 'pay_shop_recheck', 49900)['data']['attributes']['data'];
    Http::fake(['api.paymongo.com/*' => Http::response(['data' => $session])]);

    $this->actingAs(User::factory()->create(['role' => 'shop_owner']))->post(route('shop.pricing.checkout.check', $payment))->assertNotFound();
    $this->actingAs($shop->owner)->post(route('shop.pricing.checkout.check', $payment))->assertOk()->assertJsonPath('status', 'approved');
    expect($shop->fresh()->activePlanSlug())->toBe('basic');
});

it('tracks a dispute and holds shop settlement until it is won', function () {
    Storage::fake();
    [$invoice, $customer, $shop] = payMongoInvoiceFixture();
    $payment = $invoice->payments()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'amount' => 500, 'payment_method' => 'paymongo', 'payment_date' => now(), 'status' => 'confirmed', 'paymongo_payment_id' => 'pay_disputed', 'paymongo_fee' => 0, 'paymongo_net_amount' => 500, 'settlement_status' => 'pending']);
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.paymongo-settlements.index', ['payment_id' => 'pay_disputed']))->assertOk()->assertSee('INV-PAYMONGO-1');
    $disputeRoute = route('admin.paymongo-settlements.dispute', $payment);
    $this->patch($disputeRoute, ['dispute_id' => 'dsp_example', 'dispute_status' => 'open', 'dispute_amount' => 500, 'dispute_reason' => 'Fraudulent'])->assertRedirect();
    $settlement = ['settlement_amount' => 500, 'platform_fee' => 0, 'settlement_reference' => 'TRANSFER', 'proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')];
    $this->post(route('admin.paymongo-settlements.reserve', $payment), $settlement)->assertSessionHasErrors('settlement_amount');
    $resolved = ['data' => ['id' => 'evt_dispute_resolved', 'attributes' => ['type' => 'dispute.resolved', 'livemode' => false, 'data' => ['id' => 'dsp_example', 'type' => 'dispute', 'attributes' => ['amount' => 50000, 'status' => 'won', 'reason' => 'Fraudulent']]]]];
    payMongoWebhook($this, $resolved)->assertOk();
    expect($payment->fresh()->dispute_status)->toBe('won');
    $this->post(route('admin.paymongo-settlements.reserve', $payment), $settlement)->assertRedirect();
    $this->patch(route('admin.paymongo-settlements.update', $payment), $settlement)->assertRedirect();
    expect($payment->fresh()->settlement_status)->toBe('settled');
});

it('recovers missing PayMongo fee details before allowing settlement', function () {
    Storage::fake();
    [$invoice, $customer, $shop] = payMongoInvoiceFixture();
    $payment = $invoice->payments()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'amount' => 500, 'payment_method' => 'paymongo', 'payment_date' => now(), 'status' => 'confirmed', 'paymongo_payment_id' => 'pay_fee_refresh', 'paymongo_session_id' => 'cs_fee_refresh', 'settlement_status' => 'pending']);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.paymongo-settlements.reserve', $payment), ['settlement_amount' => 500, 'platform_fee' => 0])->assertSessionHasErrors('settlement_amount');
    $session = payMongoPaidEvent('invoice-'.$payment->id, 'cs_fee_refresh', 'pay_fee_refresh', 50000)['data']['attributes']['data'];
    $session['attributes']['payments'][0]['attributes']['fee'] = 1000;
    $session['attributes']['payments'][0]['attributes']['net_amount'] = 49000;
    Http::fake(['api.paymongo.com/*' => Http::response(['data' => $session])]);
    $this->post(route('admin.paymongo-settlements.refresh', $payment))->assertRedirect();
    expect($payment->fresh()->paymongo_fee)->toBe('10.00');
    expect($payment->fresh()->paymongo_net_amount)->toBe('490.00');
});

it('updates a processing refund from a signed PayMongo event', function () {
    [$invoice, $customer, $shop] = payMongoInvoiceFixture();
    $payment = $invoice->payments()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'amount' => 500, 'payment_method' => 'paymongo', 'payment_date' => now(), 'status' => 'confirmed', 'paymongo_payment_id' => 'pay_refund_event', 'refund_amount' => 100, 'refund_status' => 'processing', 'paymongo_refund_id' => 'ref_event', 'settlement_status' => 'pending']);
    $invoice->update(['status' => 'paid']);
    $event = ['data' => ['id' => 'evt_refund', 'attributes' => ['type' => 'payment.refund.updated', 'livemode' => false, 'data' => ['id' => 'ref_event', 'type' => 'refund', 'attributes' => ['payment_id' => 'pay_refund_event', 'amount' => 10000, 'currency' => 'PHP', 'status' => 'succeeded']]]]];

    payMongoWebhook($this, $event)->assertOk();
    expect($payment->fresh()->refund_status)->toBe('succeeded');
    expect($invoice->fresh()->remainingBalance())->toBe(100.0);
});

it('alerts an admin when PayMongo sends a dispute without a payment reference', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = ['data' => ['id' => 'evt_unmatched_dispute', 'attributes' => ['type' => 'dispute.created', 'livemode' => false, 'data' => ['id' => 'dsp_unmatched', 'type' => 'dispute', 'attributes' => ['amount' => 50000, 'status' => 'under_review', 'reason' => 'fraudulent']]]]];

    payMongoWebhook($this, $event)->assertOk();
    payMongoWebhook($this, $event)->assertOk();
    expect($admin->notifications()->where('type', 'paymongo_alert')->count())->toBe(1);
});

/** @return array{Invoice, User, Shop} */
function payMongoInvoiceFixture(): array
{
    $customer = User::factory()->create(['role' => 'customer']);
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active']);
    $invoice = Invoice::query()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'invoice_number' => 'INV-PAYMONGO-1', 'subtotal' => 500, 'total' => 500, 'status' => 'unpaid']);

    return [$invoice, $customer, $shop];
}

/** @return array<string, mixed> */
function payMongoPaidEvent(string $reference, string $sessionId, string $paymentId, int $amount): array
{
    return ['data' => ['id' => 'evt_example', 'type' => 'event', 'attributes' => ['type' => 'checkout_session.payment.paid', 'livemode' => false, 'data' => ['id' => $sessionId, 'type' => 'checkout_session', 'attributes' => ['reference_number' => $reference, 'payments' => [['id' => $paymentId, 'type' => 'payment', 'attributes' => ['amount' => $amount, 'currency' => 'PHP', 'status' => 'paid', 'source' => ['type' => 'gcash']]]]]]]]];
}

function payMongoWebhook($test, array $event, bool $valid = true): TestResponse
{
    $body = json_encode($event, JSON_UNESCAPED_UNICODE);
    $timestamp = (string) time();
    $signature = $valid ? hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_example') : str_repeat('0', 64);

    return $test->call('POST', route('webhooks.paymongo'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature},li="], $body);
}
