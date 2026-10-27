<?php

use App\Models\Quotation;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires customer approval before charging for extra repair work', function () {
    [$customer, $owner, $job] = supplementalFixture();

    $this->actingAs($owner)->post(route('shop.quotations.supplement.store', $job), supplementalInput())
        ->assertRedirect()->assertSessionHasNoErrors();

    $revised = Quotation::query()->where('repair_job_id', $job->id)->sole();
    expect($revised->total)->toBe('1250.00')
        ->and($job->fresh()->status)->toBe('waiting_approval')
        ->and($job->items()->count())->toBe(1);

    $this->actingAs($customer)->get(route('customer.repairs.index', ['repair_job_id' => $job->id]))
        ->assertOk()->assertSee('Review revised estimate');
    $this->patch(route('customer.quotations.update', $revised), ['status' => 'accepted'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($job->fresh()->status)->toBe('approved')
        ->and($job->fresh()->quotation_id)->toBe($revised->id)
        ->and($job->items()->count())->toBe(2)
        ->and($job->fresh()->actual_cost)->toBe('1250.00');
});

it('restores the previous repair stage when extra work is declined or expires', function () {
    [$customer, $owner, $job] = supplementalFixture();
    $this->actingAs($owner)->post(route('shop.quotations.supplement.store', $job), supplementalInput())
        ->assertRedirect()->assertSessionHasNoErrors();
    $revised = Quotation::query()->where('repair_job_id', $job->id)->sole();

    $this->actingAs($customer)->patch(route('customer.quotations.update', $revised), ['status' => 'rejected'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($job->fresh()->status)->toBe('repairing')
        ->and($job->items()->count())->toBe(1)
        ->and($job->fresh()->actual_cost)->toBe('1000.00');

    $this->actingAs($owner)->post(route('shop.quotations.supplement.store', $job), supplementalInput())
        ->assertRedirect()->assertSessionHasNoErrors();
    $expiring = Quotation::query()->where('repair_job_id', $job->id)->latest('id')->firstOrFail();
    $expiring->update(['valid_until' => today()->subDay()]);

    $this->artisan('app:expire-quotations')->assertExitCode(0);
    expect($expiring->fresh()->status)->toBe('expired')
        ->and($job->fresh()->status)->toBe('repairing')
        ->and($job->items()->count())->toBe(1);
    $this->artisan('app:expire-quotations')->assertExitCode(0);
    $this->assertDatabaseCount('repair_job_status_histories', 4);
});

/** @return array{User, User, RepairJob} */
function supplementalFixture(): array
{
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::factory()->create(['owner_id' => $owner->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $vehicle = $customer->vehicles()->create(['plate_number' => 'SUP-001', 'make' => 'Honda', 'model' => 'Civic', 'year' => 2021]);
    $job = RepairJob::factory()->forVehicle($vehicle)->create([
        'shop_id' => $shop->id, 'job_number' => 'RJ-SUP-001', 'status' => 'repairing',
        'estimated_cost' => 1000, 'actual_cost' => 1000, 'started_at' => now(),
    ]);
    $original = Quotation::query()->create([
        'customer_id' => $customer->id, 'shop_id' => $shop->id, 'vehicle_id' => $vehicle->id,
        'quotation_number' => 'QT-SUP-BASE', 'status' => 'accepted', 'subtotal' => 1000, 'total' => 1000,
    ]);
    $job->update(['quotation_id' => $original->id]);
    $job->items()->create([
        'type' => 'labor', 'description' => 'Original approved labor',
        'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000,
    ]);

    return [$customer, $owner, $job];
}

/** @return array<string, mixed> */
function supplementalInput(): array
{
    return [
        'items' => [['type' => 'labor', 'description' => 'Extra diagnostic labor', 'quantity' => 1, 'unit_price' => 250]],
        'notes' => 'Additional fault found during inspection.',
        'valid_until' => today()->addDays(7)->toDateString(),
    ];
}
