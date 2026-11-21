<?php

use App\Models\EmergencyRequest;
use App\Models\Invoice;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\ImportantEventMail;
use App\Services\AppointmentSchedule;
use App\Services\EmergencyDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('reserves a shop transfer before completion and blocks refund requests while reserved', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active']);
    $customer = User::factory()->create(['role' => 'customer']);
    $invoice = Invoice::query()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-RESERVE', 'subtotal' => 500, 'total' => 500, 'status' => 'paid']);
    $payment = $invoice->payments()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'amount' => 500, 'payment_method' => 'paymongo', 'payment_date' => now(), 'status' => 'confirmed', 'paymongo_payment_id' => 'pay_reserve', 'paymongo_fee' => 10, 'paymongo_net_amount' => 490, 'settlement_status' => 'pending']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->post(route('admin.paymongo-settlements.reserve', $payment), ['platform_fee' => 20, 'settlement_amount' => 470])->assertRedirect();
    expect($payment->fresh()->settlement_status)->toBe('reserved');
    $this->actingAs($customer)->post(route('customer.payments.refund.request', $payment), ['amount' => 100, 'reason' => 'Service was cancelled'])->assertSessionHasErrors('refund');
    $this->actingAs($admin)->post(route('admin.paymongo-settlements.release', $payment), ['reason' => 'Bank transfer was not sent'])->assertRedirect();
    expect($payment->fresh()->settlement_status)->toBe('pending');
});

it('applies a refund credit note without creating a replacement balance', function () {
    $shop = Shop::factory()->create();
    $customer = User::factory()->create(['role' => 'customer']);
    $invoice = Invoice::query()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-CREDIT', 'subtotal' => 500, 'total' => 500, 'status' => 'paid']);
    $invoice->payments()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'amount' => 500, 'payment_method' => 'paymongo', 'payment_date' => now(), 'status' => 'confirmed', 'refund_status' => 'succeeded', 'refund_amount' => 100, 'refund_resolution' => 'credit']);

    expect($invoice->creditNoteTotal())->toBe(100.0)
        ->and($invoice->payableTotal())->toBe(400.0)
        ->and($invoice->remainingBalance())->toBe(0.0);
});

it('keeps a paid invoice paid when an approved PayMongo refund is a service credit', function () {
    config()->set('services.paymongo.secret_key', 'sk_test_example');
    $shop = Shop::factory()->create();
    $customer = User::factory()->create(['role' => 'customer']);
    $invoice = Invoice::query()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-SERVICE-CREDIT', 'subtotal' => 500, 'total' => 500, 'status' => 'paid']);
    $payment = $invoice->payments()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'amount' => 500, 'payment_method' => 'paymongo', 'payment_date' => now(), 'status' => 'confirmed', 'paymongo_payment_id' => 'pay_credit', 'settlement_status' => 'pending']);
    $this->actingAs($customer)->post(route('customer.payments.refund.request', $payment), ['amount' => 100, 'reason' => 'Cancelled part of service'])->assertRedirect();
    Http::fake(['api.paymongo.com/*' => Http::response(['data' => ['id' => 'ref_credit', 'attributes' => ['payment_id' => 'pay_credit', 'amount' => 10000, 'currency' => 'PHP', 'status' => 'succeeded']]])]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('admin.paymongo-refunds.update', $payment), ['decision' => 'approved', 'refund_resolution' => 'credit'])->assertRedirect();
    expect($payment->fresh()->refund_status)->toBe('succeeded')->and($invoice->fresh()->status)->toBe('paid')->and($invoice->remainingBalance())->toBe(0.0);
    $this->actingAs($customer)->get(route('customer.invoices.show', $invoice))->assertOk()->assertSee('Credit note applied to invoice');
});

it('refuses booking on closed weekdays and blocked hours', function () {
    $friday = now()->next('Friday')->toDateString();
    $sunday = now()->next('Sunday')->toDateString();
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'operating_days' => [1, 2, 3, 4, 5], 'schedule_blocks' => [['id' => 'holiday', 'date' => $friday, 'start' => '09:00', 'end' => '12:00', 'reason' => 'Maintenance']]]);
    $schedule = app(AppointmentSchedule::class);

    expect($schedule->isAvailable($shop, $sunday, '10:00', 60))->toBeFalse()
        ->and($schedule->isAvailable($shop, $friday, '10:00', 60))->toBeFalse()
        ->and($schedule->isAvailable($shop, $friday, '13:00', 60))->toBeTrue();
});

it('dispatches roadside requests only to an on-call shop within its radius', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active', 'latitude' => 10, 'longitude' => 123, 'roadside_available' => false, 'roadside_radius_km' => 10]);
    $request = EmergencyRequest::query()->create(['customer_id' => $customer->id, 'emergency_type' => 'breakdown', 'latitude' => 11, 'longitude' => 123, 'status' => 'pending']);
    $dispatch = app(EmergencyDispatchService::class);
    expect($dispatch->assignNext($request))->toBeNull();
    $shop->update(['roadside_available' => true]);
    expect($dispatch->assignNext($request))->toBeNull();
    $shop->update(['roadside_radius_km' => 150]);
    expect($dispatch->assignNext($request)?->id)->toBe($shop->id);
});

it('queues email for important in-app updates', function () {
    Notification::fake();
    $user = User::factory()->create(['role' => 'shop_owner', 'status' => 'active']);
    $user->notifications()->create(['type' => 'appointment_requested', 'title' => 'New appointment', 'message' => 'A customer is waiting.', 'data' => ['appointment_id' => 4]]);

    Notification::assertSentTo($user, ImportantEventMail::class);
});

it('scopes staff routes to their shop and role', function () {
    $shop = Shop::factory()->create(['verification_status' => 'approved', 'shop_status' => 'active']);
    $staff = User::factory()->create(['role' => 'shop_staff', 'staff_shop_id' => $shop->id, 'staff_role' => 'receptionist', 'status' => 'active']);
    $technician = User::factory()->create(['role' => 'shop_staff', 'staff_shop_id' => $shop->id, 'staff_role' => 'technician', 'status' => 'active']);
    $cashier = User::factory()->create(['role' => 'shop_staff', 'staff_shop_id' => $shop->id, 'staff_role' => 'cashier', 'status' => 'active']);
    $this->actingAs($staff)->get(route('shop.appointments.index'))->assertOk();
    $this->get(route('shop.invoices.index'))->assertForbidden();
    $this->get(route('shop.profile.edit'))->assertForbidden();
    $this->actingAs($technician)->get(route('shop.dashboard'))->assertOk()->assertSee('technician workspace');
    $this->get(route('shop.repair-jobs.index'))->assertOk();
    $this->get(route('shop.invoices.index'))->assertForbidden();
    $this->actingAs($cashier)->get(route('shop.dashboard'))->assertOk()->assertSee('cashier workspace');
    $this->get(route('shop.invoices.index'))->assertOk();
    $this->get(route('shop.appointments.index'))->assertForbidden();
});

it('requires a second factor after an admin signs in', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

    $this->post(route('login'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.two-factor.create'));
    $this->get(route('admin.two-factor.create'))->assertOk()->assertSee('Verify admin sign-in');
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.two-factor.create'));
    Notification::assertSentTo($admin, ImportantEventMail::class);
    $this->withSession(['admin_two_factor' => ['user_id' => $admin->id, 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10)->timestamp]])
        ->post(route('admin.two-factor.store'), ['code' => '123456'])->assertRedirect(route('admin.dashboard'));
    $this->get(route('admin.dashboard'))->assertOk();
});

it('requires recent password confirmation before a financial action', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $shop = Shop::factory()->create();
    $this->actingAs($admin)->patch(route('admin.shops.plan', $shop), ['plan_slug' => 'basic'])->assertRedirect(route('password.confirm'));
    $this->post(route('password.confirm.store'), ['password' => 'wrong'])->assertSessionHasErrors('password');
    $this->post(route('password.confirm.store'), ['password' => 'password'])->assertRedirect(route('admin.shop-plans.index'));
});

it('creates a shop-scoped staff invitation and refuses another owners update', function () {
    Notification::fake();
    $shop = Shop::factory()->create();
    $other = Shop::factory()->create();
    $email = 'new-staff@example.test';
    $this->actingAs($shop->owner)->get(route('shop.staff.index'))->assertOk()->assertSee('Invite staff');
    $this->actingAs($shop->owner)->post(route('shop.staff.store'), ['name' => 'New Cashier', 'email' => $email, 'staff_role' => 'cashier'])->assertRedirect();
    $staff = User::query()->where('email', $email)->firstOrFail();
    expect($staff->status)->toBe('invited')->and($staff->staff_shop_id)->toBe($shop->id);
    $this->actingAs($other->owner)->patch(route('shop.staff.update', $staff), ['staff_role' => 'technician', 'status' => 'active'])->assertNotFound();
});
