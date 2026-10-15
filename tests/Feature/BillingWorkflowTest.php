<?php

use App\Models\Barangay;
use App\Models\Invoice;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets a shop owner issue an invoice for a completed repair job', function () {
    $fixture = billingFixture();
    $fixture['repairJob']->update(['status' => 'completed', 'actual_cost' => 1850]);

    $this->actingAs($fixture['owner'])
        ->post(route('shop.invoices.store', $fixture['repairJob']))
        ->assertRedirect(route('shop.invoices.index'));

    $this->assertDatabaseHas('invoices', [
        'repair_job_id' => $fixture['repairJob']->id,
        'customer_id' => $fixture['customer']->id,
        'shop_id' => $fixture['shop']->id,
        'total' => 1850,
        'status' => 'unpaid',
    ]);
    $this->assertDatabaseHas('invoice_items', ['description' => 'Brake inspection', 'unit_price' => 1850]);
});

it('records a customer payment and lets the shop confirm it', function () {
    $fixture = billingFixture();
    $invoice = Invoice::query()->create([
        'customer_id' => $fixture['customer']->id,
        'shop_id' => $fixture['shop']->id,
        'repair_job_id' => $fixture['repairJob']->id,
        'invoice_number' => 'INV-TEST-001',
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $this->actingAs($fixture['customer'])
        ->post(route('customer.payments.store', $invoice), [
            'amount' => 1000,
            'payment_method' => 'gcash',
            'reference_number' => 'GCASH-001',
        ])
        ->assertRedirect();

    $payment = $invoice->payments()->firstOrFail();
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);

    $this->actingAs($fixture['owner'])
        ->patch(route('shop.payments.update', $payment), ['status' => 'confirmed'])
        ->assertRedirect();

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'confirmed']);
    $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid']);
});

it('does not let a customer submit payment for another customers invoice', function () {
    $fixture = billingFixture();
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $invoice = Invoice::query()->create([
        'customer_id' => $otherCustomer->id,
        'shop_id' => $fixture['shop']->id,
        'invoice_number' => 'INV-TEST-002',
        'subtotal' => 500,
        'total' => 500,
    ]);

    $this->actingAs($fixture['customer'])
        ->post(route('customer.payments.store', $invoice), ['amount' => 500, 'payment_method' => 'cash'])
        ->assertNotFound();
});

it('renders printable invoice details only to the owning customer or shop', function () {
    $fixture = billingFixture();
    $invoice = Invoice::query()->create(['customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id, 'repair_job_id' => $fixture['repairJob']->id, 'invoice_number' => 'INV-PRINT-001', 'subtotal' => 1000, 'total' => 1000]);
    $invoice->items()->create(['description' => 'Brake inspection', 'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000]);

    $this->actingAs($fixture['customer'])->get(route('customer.invoices.show', $invoice))->assertOk()->assertSee('Print invoice')->assertSee('PHP 1,000.00');
    $this->actingAs($fixture['owner'])->get(route('shop.invoices.show', $invoice))->assertOk()->assertSee('Print invoice');
    $other = User::factory()->create(['role' => 'customer']);
    $this->actingAs($other)->get(route('customer.invoices.show', $invoice))->assertNotFound();
});

it('supports partial payments and keeps the invoice balance until fully confirmed', function () {
    $fixture = billingFixture();
    $invoice = Invoice::query()->create(['customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id, 'repair_job_id' => $fixture['repairJob']->id, 'invoice_number' => 'INV-PARTIAL-001', 'subtotal' => 1000, 'total' => 1000]);

    foreach ([400, 600] as $amount) {
        $this->actingAs($fixture['customer'])->post(route('customer.payments.store', $invoice), ['amount' => $amount, 'payment_method' => 'other'])->assertRedirect();
        $payment = $invoice->payments()->latest('id')->firstOrFail();
        $this->actingAs($fixture['owner'])->patch(route('shop.payments.update', $payment), ['status' => 'confirmed'])->assertRedirect();
    }

    expect($invoice->fresh()->remainingBalance())->toBe(0.0);
    $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid']);
});

it('does not allow payments after an invoice is paid or amounts over the remaining balance', function () {
    $fixture = billingFixture();
    $invoice = Invoice::query()->create(['customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id, 'invoice_number' => 'INV-OVER-001', 'subtotal' => 500, 'total' => 500, 'status' => 'paid']);
    $this->actingAs($fixture['customer'])->post(route('customer.payments.store', $invoice), ['amount' => 1, 'payment_method' => 'cash'])->assertNotFound();
    $invoice->update(['status' => 'unpaid']);
    $this->post(route('customer.payments.store', $invoice), ['amount' => 501, 'payment_method' => 'cash'])->assertSessionHasErrors('amount');
    $this->assertDatabaseCount('payments', 0);
});

it('accepts decimal instalments that exactly settle the balance', function () {
    $fixture = billingFixture();
    $invoice = Invoice::query()->create([
        'customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id,
        'invoice_number' => 'INV-CENTS', 'subtotal' => '0.30', 'total' => '0.30',
    ]);
    foreach (['0.10', '0.20'] as $amount) {
        $this->actingAs($fixture['customer'])->post(route('customer.payments.store', $invoice), [
            'amount' => $amount, 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $payment = $invoice->payments()->latest('id')->firstOrFail();
        $this->actingAs($fixture['owner'])->patch(route('shop.payments.update', $payment), ['status' => 'confirmed'])->assertRedirect();
    }
    $this->assertDatabaseCount('payments', 2);
    $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid']);
});

it('does not issue a second invoice or duplicate line items for the same repair', function () {
    $fixture = billingFixture();
    $fixture['repairJob']->update(['status' => 'completed', 'actual_cost' => 100]);
    $this->actingAs($fixture['owner'])->post(route('shop.invoices.store', $fixture['repairJob']))->assertRedirect();
    $this->post(route('shop.invoices.store', $fixture['repairJob']))->assertRedirect();

    $this->assertDatabaseCount('invoices', 1);
    $this->assertDatabaseCount('invoice_items', 1);
});

it('does not change an already verified payment or send duplicate verification notices', function () {
    $fixture = billingFixture();
    $invoice = Invoice::query()->create([
        'customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id,
        'invoice_number' => 'INV-REPLAY', 'subtotal' => 100, 'total' => 100,
    ]);
    $this->actingAs($fixture['customer'])->post(route('customer.payments.store', $invoice), ['amount' => 100, 'payment_method' => 'cash'])->assertRedirect();
    $payment = $invoice->payments()->firstOrFail();
    $this->actingAs($fixture['owner'])->patch(route('shop.payments.update', $payment), ['status' => 'confirmed'])->assertRedirect();
    $this->patch(route('shop.payments.update', $payment), ['status' => 'rejected', 'review_note' => 'Duplicate review.'])->assertNotFound();

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'confirmed']);
    $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid']);
    $this->assertDatabaseCount('notifications', 2);
});

it('shows every pending payment and lets the shop review each one separately', function () {
    $fixture = billingFixture();
    $invoice = Invoice::query()->create([
        'customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id,
        'invoice_number' => 'INV-MULTI', 'subtotal' => 1000, 'total' => 1000,
    ]);
    $this->actingAs($fixture['customer']);
    $this->post(route('customer.payments.store', $invoice), ['amount' => 400, 'payment_method' => 'gcash', 'reference_number' => 'GC-111'])->assertRedirect();
    $this->post(route('customer.payments.store', $invoice), ['amount' => 600, 'payment_method' => 'cash'])->assertRedirect();
    $payments = $invoice->payments()->orderBy('id')->get();

    $this->get(route('customer.invoices.index'))->assertOk()->assertSee('Shop review pending')->assertDontSee('I paid the shop');
    $this->actingAs($fixture['owner'])->get(route('shop.invoices.index'))->assertOk()
        ->assertSee('GC-111')->assertSee('Confirm received')
        ->assertSee('name="status" value="confirmed"', false)
        ->assertSee('name="status" value="rejected"', false);
    $this->patch(route('shop.payments.update', $payments[0]), ['status' => 'confirmed'])
        ->assertRedirect(route('shop.invoices.index'));
    $this->patch(route('shop.payments.update', $payments[1]), ['status' => 'confirmed'])
        ->assertRedirect(route('shop.invoices.index'));

    expect($invoice->fresh()->status)->toBe('paid');
    expect($invoice->remainingBalance())->toBe(0.0);
    $this->assertDatabaseHas('payments', ['id' => $payments[0]->id, 'status' => 'confirmed']);
    $this->assertDatabaseHas('payments', ['id' => $payments[1]->id, 'status' => 'confirmed']);
});

it('lets the customer correct a payment after the shop rejects it', function () {
    $fixture = billingFixture();
    $invoice = Invoice::query()->create([
        'customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id,
        'invoice_number' => 'INV-REJECTED', 'subtotal' => 500, 'total' => 500,
    ]);
    $this->actingAs($fixture['customer'])->post(route('customer.payments.store', $invoice), [
        'amount' => 500, 'payment_method' => 'gcash', 'reference_number' => 'WRONG-REF',
    ])->assertRedirect();
    $payment = $invoice->payments()->firstOrFail();

    $this->actingAs($fixture['owner'])->patch(route('shop.payments.update', $payment), ['status' => 'rejected', 'review_note' => 'Reference number did not match our records.'])
        ->assertRedirect(route('shop.invoices.index'));
    $this->actingAs($fixture['customer'])->get(route('customer.invoices.index'))
        ->assertOk()->assertSee('I paid the shop')->assertSee('Reference number did not match our records.');
    $this->post(route('customer.payments.store', $invoice), [
        'amount' => 500, 'payment_method' => 'gcash', 'reference_number' => 'CORRECT-REF',
    ])->assertRedirect();

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'rejected', 'review_note' => 'Reference number did not match our records.']);
    $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'reference_number' => 'CORRECT-REF', 'status' => 'pending']);
});

it('requires a reason before the shop rejects a payment', function () {
    $fixture = billingFixture();
    $invoice = Invoice::query()->create([
        'customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id,
        'invoice_number' => 'INV-REASON', 'subtotal' => 500, 'total' => 500,
    ]);
    $this->actingAs($fixture['customer'])->post(route('customer.payments.store', $invoice), ['amount' => 500, 'payment_method' => 'cash'])->assertRedirect();
    $payment = $invoice->payments()->firstOrFail();

    $this->actingAs($fixture['owner'])->patch(route('shop.payments.update', $payment), ['status' => 'rejected'])
        ->assertSessionHasErrors('review_note');

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
});

/**
 * @return array{customer: User, owner: User, shop: Shop, vehicle: Vehicle, repairJob: RepairJob}
 */
function billingFixture(): array
{
    $barangay = Barangay::factory()->create();
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Billing Shop',
        'phone' => '09170000000',
        'email' => 'billing@example.test',
        'address' => 'Demo Street',
        'barangay_id' => $barangay->id,
        'verification_status' => 'approved',
        'shop_status' => 'active',
    ]);
    $vehicle = $customer->vehicles()->create([
        'plate_number' => 'BILL-001',
        'make' => 'Toyota',
        'model' => 'Vios',
        'year' => 2020,
    ]);
    $repairJob = RepairJob::query()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'vehicle_id' => $vehicle->id,
        'job_number' => 'RJ-BILL-001',
        'description' => 'Brake inspection',
        'status' => 'pending',
    ]);

    return compact('customer', 'owner', 'shop', 'vehicle', 'repairJob');
}
