<?php

use App\Models\Barangay;
use App\Models\Quotation;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

it('completes the customer request estimate acceptance repair and release workflow', function () {
    $fixture = phaseSevenFixture();
    $this->actingAs($fixture['customer'])->get(route('customer.quotations.create'))->assertOk();
    $this->post(route('customer.quotations.store'), ['shop_id' => $fixture['shop']->id, 'vehicle_id' => $fixture['vehicle']->id, 'notes' => 'Brakes make a noise'])
        ->assertRedirect(route('customer.quotations.index'));
    $quote = Quotation::query()->firstOrFail();
    expect($quote->status)->toBe('draft');
    $this->assertDatabaseHas('notifications', ['user_id' => $fixture['owner']->id, 'type' => 'quotation_requested']);

    $this->actingAs($fixture['owner'])->get(route('shop.quotations.index'))->assertOk()->assertSee('Brakes make a noise');
    $this->get(route('shop.quotations.edit', $quote))->assertOk();
    $this->put(route('shop.quotations.update', $quote), phaseSevenEstimate())->assertRedirect(route('shop.quotations.index'));
    expect($quote->fresh()->total)->toBe('650.30');
    expect($quote->fresh()->status)->toBe('sent');
    $this->assertDatabaseHas('notifications', ['user_id' => $fixture['customer']->id, 'type' => 'quotation_sent']);

    $this->actingAs($fixture['customer'])->get(route('customer.quotations.index'))->assertOk()->assertSee('Approve estimate');
    $this->patch(route('customer.quotations.update', $quote), ['status' => 'accepted'])->assertRedirect();
    expect($quote->fresh()->status)->toBe('accepted');

    $this->actingAs($fixture['owner'])->post(route('shop.quotations.repair', $quote))->assertRedirect(route('shop.repair-jobs.index'));
    $this->post(route('shop.quotations.repair', $quote))->assertRedirect();
    $this->assertDatabaseCount('repair_jobs', 1);
    $job = RepairJob::query()->firstOrFail();
    expect($job->status)->toBe('approved');
    foreach (['repairing', 'waiting_parts', 'repairing', 'completed'] as $status) {
        $this->patch(route('shop.repair-jobs.update', $job), ['status' => $status, 'actual_cost' => 650.30])->assertRedirect()->assertSessionHasNoErrors();
        expect($job->fresh()->status)->toBe($status);
    }
    $this->post(route('shop.invoices.store', $job))->assertRedirect(route('shop.invoices.index'));
    $invoice = $job->invoice()->firstOrFail();
    $this->actingAs($fixture['customer'])->post(route('customer.payments.store', $invoice), [
        'amount' => 650.30, 'payment_method' => 'cash',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $payment = $invoice->payments()->firstOrFail();
    $this->actingAs($fixture['owner'])->patch(route('shop.payments.update', $payment), ['status' => 'confirmed'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'released'])->assertRedirect()->assertSessionHasNoErrors();
    expect($job->fresh()->started_at)->not->toBeNull();
    expect($job->fresh()->completed_at)->not->toBeNull();
    $this->assertDatabaseCount('vehicle_service_records', 1);
    $this->actingAs($fixture['customer'])->get(route('customer.repairs.index'))->assertOk()->assertSee('released');
    $this->post(route('customer.reviews.store'), ['repair_job_id' => $job->id, 'rating' => 5])->assertRedirect()->assertSessionHasNoErrors();
});

it('prevents quotation requests for another customers vehicle or an unapproved shop', function () {
    $fixture = phaseSevenFixture();
    $other = Vehicle::factory()->create();
    $this->actingAs($fixture['customer'])->post(route('customer.quotations.store'), ['shop_id' => $fixture['shop']->id, 'vehicle_id' => $other->id, 'notes' => 'Repair'])
        ->assertSessionHasErrors('vehicle_id');
    $fixture['shop']->update(['verification_status' => 'pending']);
    $this->post(route('customer.quotations.store'), ['shop_id' => $fixture['shop']->id, 'vehicle_id' => $fixture['vehicle']->id, 'notes' => 'Repair'])
        ->assertSessionHasErrors('shop_id');
    $this->assertDatabaseCount('quotations', 0);
});

it('rejects expired estimates and prevents customers accepting another customers quotation', function () {
    $fixture = phaseSevenFixture();
    $quote = phaseSevenQuote($fixture, ['status' => 'sent', 'valid_until' => today()->subDay()]);
    $other = User::factory()->create(['role' => 'customer']);
    $this->actingAs($other)->patch(route('customer.quotations.update', $quote), ['status' => 'accepted'])->assertNotFound();
    $this->actingAs($fixture['customer'])->patch(route('customer.quotations.update', $quote), ['status' => 'accepted'])
        ->assertRedirect()->assertSessionHas('status', 'This quotation has expired. Request a new estimate.');
    expect($quote->fresh()->status)->toBe('expired');
});

it('keeps a repair waiting for approval until its customer accepts the sent quotation', function () {
    $fixture = phaseSevenFixture();
    $job = RepairJob::query()->create(['customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id, 'vehicle_id' => $fixture['vehicle']->id, 'job_number' => 'RJ-APPROVAL', 'status' => 'diagnosis']);
    $this->actingAs($fixture['owner'])->get(route('shop.quotations.create', $job))->assertOk();
    $this->post(route('shop.quotations.store', $job), phaseSevenEstimate())->assertRedirect();
    expect($job->fresh()->status)->toBe('waiting_approval');
    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'repairing'])->assertSessionHasErrors('status');
    $quote = $job->fresh()->quotation;
    $this->actingAs($fixture['customer'])->patch(route('customer.quotations.update', $quote), ['status' => 'accepted'])->assertRedirect();
    expect($job->fresh()->status)->toBe('approved');
    expect($job->fresh()->estimated_cost)->toBe('650.30');
    expect($job->fresh()->actual_cost)->toBe('650.30');
    expect($job->items()->sum('subtotal'))->toBe(650.3);

    $this->actingAs($fixture['owner'])->post(route('shop.repair-job-items.store', $job), [
        'type' => 'part', 'description' => 'Extra part', 'quantity' => 1, 'unit_price' => 100,
    ])->assertConflict();
    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'repairing', 'actual_cost' => 1])->assertRedirect();
    expect($job->fresh()->actual_cost)->toBe('650.30');
});

it('does not leave a repair waiting for approval without a sent estimate', function () {
    $fixture = phaseSevenFixture();
    $job = RepairJob::query()->create(['customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id, 'vehicle_id' => $fixture['vehicle']->id, 'job_number' => 'RJ-NO-ESTIMATE', 'status' => 'diagnosis']);

    $this->actingAs($fixture['owner'])->patch(route('shop.repair-jobs.update', $job), ['status' => 'waiting_approval'])
        ->assertSessionHasErrors(['status' => 'Send an estimate to the customer before waiting for approval.']);

    expect($job->fresh()->status)->toBe('diagnosis');
    $this->get(route('shop.repair-jobs.index'))->assertOk()->assertSee('Send estimate');
});

it('shows a recovery action for a legacy repair waiting without a quotation', function () {
    $fixture = phaseSevenFixture();
    RepairJob::query()->create(['customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id, 'vehicle_id' => $fixture['vehicle']->id, 'job_number' => 'RJ-NEEDS-QUOTE', 'status' => 'waiting_approval']);

    $this->actingAs($fixture['owner'])->get(route('shop.repair-jobs.index'))
        ->assertOk()
        ->assertSee('No estimate has been sent for this repair.')
        ->assertSee('Send estimate for approval');
});

it('prevents owners from modifying another shops quote or opening an unaccepted repair', function () {
    $fixture = phaseSevenFixture();
    $quote = phaseSevenQuote($fixture);
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    Shop::query()->create(['owner_id' => $otherOwner->id, 'barangay_id' => $fixture['shop']->barangay_id, 'name' => 'Other shop', 'phone' => '09170000001', 'email' => 'other@example.test', 'address' => 'Other street']);
    $this->actingAs($otherOwner)->get(route('shop.quotations.edit', $quote))->assertNotFound();
    $this->put(route('shop.quotations.update', $quote), phaseSevenEstimate())->assertNotFound();
    $this->post(route('shop.quotations.repair', $quote))->assertNotFound();
    $this->actingAs($fixture['owner'])->post(route('shop.quotations.repair', $quote))->assertStatus(409);
    $this->assertDatabaseCount('repair_jobs', 0);
});

it('keeps completed and released repairs from returning to active work', function () {
    $fixture = phaseSevenFixture();
    $job = RepairJob::query()->create(['customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id, 'vehicle_id' => $fixture['vehicle']->id, 'job_number' => 'RJ-DONE', 'status' => 'completed']);
    $this->actingAs($fixture['owner'])->patch(route('shop.repair-jobs.update', $job), ['status' => 'repairing'])->assertSessionHasErrors('status');
    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'released'])->assertSessionHasErrors('status');
    $job->invoice()->create([
        'customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id,
        'invoice_number' => 'INV-DONE', 'subtotal' => 100, 'total' => 100, 'status' => 'paid',
    ]);
    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'released'])->assertRedirect()->assertSessionHasNoErrors();
    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'cancelled'])->assertSessionHasErrors('status');
    expect($job->fresh()->status)->toBe('released');
});

it('lets customers decline an estimate and blocks subsequent repair creation', function () {
    $fixture = phaseSevenFixture();
    $quote = phaseSevenQuote($fixture, ['status' => 'sent']);
    $this->actingAs($fixture['customer'])->patch(route('customer.quotations.update', $quote), ['status' => 'rejected'])->assertRedirect();
    expect($quote->fresh()->status)->toBe('rejected');
    $this->patch(route('customer.quotations.update', $quote), ['status' => 'accepted'])->assertNotFound();
    $this->actingAs($fixture['owner'])->post(route('shop.quotations.repair', $quote))->assertStatus(409);
    $this->assertDatabaseCount('repair_jobs', 0);
});

it('rejects invalid estimate values without changing the request', function () {
    $fixture = phaseSevenFixture();
    $quote = phaseSevenQuote($fixture);
    $payload = phaseSevenEstimate();
    $payload['items'][0]['unit_price'] = -5;
    $payload['items'][1]['quantity'] = 0;
    $this->actingAs($fixture['owner'])->put(route('shop.quotations.update', $quote), $payload)
        ->assertSessionHasErrors(['items.0.unit_price', 'items.1.quantity']);
    expect($quote->fresh()->status)->toBe('draft');
    $this->assertDatabaseCount('quotation_items', 0);
});

it('requires the proper roles for quote request and estimate actions', function () {
    $fixture = phaseSevenFixture();
    $quote = phaseSevenQuote($fixture);
    $this->get(route('customer.quotations.create'))->assertRedirect('/login');
    $this->actingAs($fixture['owner'])->post(route('customer.quotations.store'), [])->assertForbidden();
    $this->actingAs($fixture['customer'])->get(route('shop.quotations.index'))->assertForbidden();
    $this->put(route('shop.quotations.update', $quote), phaseSevenEstimate())->assertForbidden();
});

/** @return array{owner: User, customer: User, shop: Shop, vehicle: Vehicle} */
function phaseSevenFixture(): array
{
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $customer = User::factory()->create(['role' => 'customer']);
    $shop = Shop::query()->create(['owner_id' => $owner->id, 'barangay_id' => Barangay::factory()->create()->id, 'name' => 'Demo quote shop', 'phone' => '09170000000', 'email' => 'quote@example.test', 'address' => 'Demo Street', 'verification_status' => 'approved', 'shop_status' => 'active']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);

    return compact('owner', 'customer', 'shop', 'vehicle');
}

/** @return array<string, mixed> */
function phaseSevenEstimate(): array
{
    return ['valid_until' => today()->addDays(7)->toDateString(), 'notes' => 'Parts and labor', 'items' => [
        ['description' => 'Brake pads', 'quantity' => 3, 'unit_price' => '150.10'],
        ['description' => 'Labor', 'quantity' => 1, 'unit_price' => '200.00'],
    ]];
}

/** @param array{owner: User, customer: User, shop: Shop, vehicle: Vehicle} $fixture
 * @param  array<string, mixed>  $attributes
 */
function phaseSevenQuote(array $fixture, array $attributes = []): Quotation
{
    return Quotation::query()->create(['customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id, 'vehicle_id' => $fixture['vehicle']->id, 'quotation_number' => 'QT-TEST-PHASE7', 'status' => 'draft', ...$attributes]);
}
