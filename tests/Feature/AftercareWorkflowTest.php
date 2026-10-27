<?php

use App\Models\RepairJob;
use App\Models\ServiceCase;
use App\Models\Shop;
use App\Models\User;
use App\Models\VehicleServiceRecord;
use App\Services\AppointmentSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('stores private before and after repair photos and refuses unrelated users', function () {
    Storage::fake('local');
    [$customer, $owner, $shop, $job] = aftercareFixture();
    $otherCustomer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($owner)->post(route('shop.repair-photos.store', $job), [
        'stage' => 'after', 'photo' => aftercareImage('after.png'),
    ])->assertSessionHasErrors('stage');
    $this->assertDatabaseCount('repair_photos', 0);

    $this->post(route('shop.repair-photos.store', $job), [
        'stage' => 'before', 'photo' => aftercareImage('before.png'), 'caption' => 'Damaged bumper',
    ])->assertRedirect();
    $photo = $job->photos()->sole();
    Storage::disk('local')->assertExists($photo->path);
    $this->actingAs($customer)->get(route('repair-photos.show', $photo))->assertOk();
    $this->actingAs($otherCustomer)->get(route('repair-photos.show', $photo))->assertNotFound();

    $job->update(['status' => 'repairing', 'started_at' => now()]);
    $this->actingAs($owner)->post(route('shop.repair-photos.store', $job), [
        'stage' => 'before', 'photo' => aftercareImage('late-before.png'),
    ])->assertSessionHasErrors('stage');
    $this->actingAs($owner)->post(route('shop.repair-photos.store', $job), [
        'stage' => 'after', 'photo' => aftercareImage('after.png'),
    ])->assertRedirect();
    $this->assertDatabaseCount('repair_photos', 2);
    $this->actingAs($customer)->get(route('customer.repairs.index'))->assertSee('Before & after photos', false);
});

it('issues an explicit warranty and tracks a customer follow-up through the shop', function () {
    [$customer, $owner, $shop, $job] = aftercareFixture();
    $job->update(['status' => 'completed', 'started_at' => now()->subDay(), 'completed_at' => now()]);
    VehicleServiceRecord::query()->create([
        'repair_job_id' => $job->id, 'vehicle_id' => $job->vehicle_id,
        'shop_id' => $shop->id, 'customer_id' => $customer->id,
        'service_date' => today(), 'service_type' => 'Brake repair',
        'total_cost' => 1500,
    ]);

    $this->actingAs($owner)->post(route('shop.repair-warranties.store', $job), [
        'coverage' => 'Brake pads and labor on this repair only', 'duration_days' => 90,
    ])->assertRedirect();
    $this->assertDatabaseHas('repair_warranties', ['repair_job_id' => $job->id, 'coverage' => 'Brake pads and labor on this repair only']);
    $this->post(route('shop.repair-warranties.store', $job), [
        'coverage' => 'Changed coverage', 'duration_days' => 60,
    ])->assertSessionHasErrors('coverage');

    $this->actingAs($customer)->post(route('customer.service-cases.store', $job), [
        'type' => 'warranty_claim', 'subject' => 'Brake noise returned',
        'description' => 'The brake noise returned three days after the repair.',
    ])->assertRedirect(route('customer.service-cases.index'));
    $case = ServiceCase::query()->sole();
    $this->post(route('customer.service-cases.store', $job), [
        'type' => 'warranty_claim', 'subject' => 'Duplicate request',
        'description' => 'This should not make a second open case.',
    ])->assertSessionHasErrors('type');

    $this->actingAs($owner)->patch(route('shop.service-cases.update', $case), [
        'status' => 'scheduled', 'shop_response' => 'Please bring the vehicle for a free inspection.',
        'scheduled_at' => today()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i:s'),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('service_cases', ['id' => $case->id, 'status' => 'scheduled', 'schedule_status' => 'pending']);
    $this->get(route('shop.service-cases.index'))->assertSee('Brake noise returned');
    $this->get(route('shop.today-work.index'))->assertSee('Brake noise returned');
    $this->actingAs($customer)->get(route('customer.service-cases.index'))->assertSee('Please bring the vehicle for a free inspection.');
    $this->patch(route('customer.service-cases.schedule', $case), ['decision' => 'confirm'])->assertRedirect();
    $this->assertDatabaseHas('service_cases', ['id' => $case->id, 'schedule_status' => 'confirmed']);
    $appointment = $case->fresh()->appointment;
    expect($appointment)->not->toBeNull();
    expect($appointment->status)->toBe('confirmed');
    expect($appointment->service_id)->toBeNull();
    $this->assertDatabaseHas('appointments', [
        'id' => $appointment->id, 'customer_id' => $customer->id,
        'shop_id' => $shop->id, 'vehicle_id' => $job->vehicle_id,
    ]);
    $this->actingAs($customer)->get(route('customer.appointments.index'))->assertOk()->assertSee('Follow-up visit');
    $this->actingAs($owner)->get(route('shop.appointments.index'))->assertOk()->assertSee('Follow-up visit');
    $this->get(route('shop.repair-jobs.create'))->assertOk()->assertSee('Follow-up visit');
    $this->actingAs($customer)->get(route('customer.services.index'))
        ->assertSee('Warranty active')
        ->assertSee('repair_job_id='.$job->id, false);
    $this->get(route('customer.repairs.index', ['repair_job_id' => $job->id]))
        ->assertViewHas('repairJobs', fn ($jobs): bool => $jobs->total() === 1);
});

it('reserves a proposed follow-up slot and keeps the case aligned with its appointment', function () {
    [$customer, $owner, $shop, $job] = aftercareFixture();
    $shop->update(['booking_capacity' => 1]);
    $firstVisit = today()->addDays(2)->setTime(10, 0);
    $case = ServiceCase::factory()->forRepair($job)->create([
        'status' => 'scheduled', 'schedule_status' => 'pending', 'scheduled_at' => $firstVisit,
    ]);
    $schedule = app(AppointmentSchedule::class);
    expect($schedule->bookedIntervals($shop, $firstVisit->toDateString()))->toHaveCount(1);
    expect($schedule->hasCapacity($schedule->bookedIntervals($shop, $firstVisit->toDateString()), '10:00', 60, 1))->toBeFalse();

    $this->actingAs($customer)->patch(route('customer.service-cases.schedule', $case), [
        'decision' => 'confirm',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $appointment = $case->fresh()->appointment;
    expect($schedule->bookedIntervals($shop, $firstVisit->toDateString()))->toHaveCount(1);
    $this->assertDatabaseCount('appointments', 1);
    $this->patch(route('customer.service-cases.schedule', $case), ['decision' => 'confirm'])->assertSessionHasErrors('decision');
    $this->actingAs($owner)->patch(route('shop.service-cases.update', $case), [
        'status' => 'resolved', 'shop_response' => 'We checked the vehicle.',
    ])->assertSessionHasErrors('status');

    $newVisit = today()->addDays(3)->setTime(11, 0);
    $this->actingAs($customer)->post(route('customer.appointments.reschedule.request', $appointment), [
        'requested_date' => $newVisit->toDateString(), 'requested_time' => '11:00',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($owner)->patch(route('shop.appointments.reschedule.decide', $appointment), [
        'decision' => 'approve',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($case->fresh()->scheduled_at->format('Y-m-d H:i'))->toBe($newVisit->format('Y-m-d H:i'));
    $this->patch(route('shop.appointments.update', $appointment), ['status' => 'cancelled'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $case->refresh();
    expect($case->status)->toBe('reviewing');
    expect($case->schedule_status)->toBe('cancelled');
    expect($case->appointment_id)->toBeNull();
    expect($schedule->bookedIntervals($shop, $newVisit->toDateString()))->toBeEmpty();
});

it('lets only admins resolve complaints and records the decision history', function () {
    [$customer, $owner, $shop, $job] = aftercareFixture();
    $job->update(['status' => 'completed', 'started_at' => now()->subDay(), 'completed_at' => now()]);
    $admin = User::factory()->create(['role' => 'admin']);
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    Shop::factory()->create(['owner_id' => $otherOwner->id]);

    $this->actingAs($customer)->post(route('customer.service-cases.store', $job), [
        'type' => 'complaint', 'subject' => 'Unresolved repair',
        'description' => 'The original problem is still present after the repair.',
    ])->assertRedirect(route('customer.service-cases.index'));
    $case = ServiceCase::query()->sole();

    $this->actingAs($otherOwner)->patch(route('shop.service-cases.update', $case), [
        'status' => 'resolved', 'shop_response' => 'Incorrect shop',
    ])->assertNotFound();
    $this->actingAs($owner)->patch(route('shop.service-cases.update', $case), [
        'status' => 'resolved', 'shop_response' => 'We reviewed the job notes.',
    ])->assertSessionHasErrors('status');
    $this->actingAs($admin)->patch(route('admin.service-cases.update', $case), [
        'status' => 'resolved',
    ])->assertSessionHasErrors(['admin_resolution' => 'Write a reason before resolving or dismissing this complaint.']);
    $this->get(route('admin.service-cases.index'))->assertSee('Unresolved repair')->assertSee('1 complaint needs review')
        ->assertSee('Resolve complaint')->assertSee('Dismiss complaint');
    $this->patch(route('admin.service-cases.update', $case), [
        'status' => 'resolved', 'admin_resolution' => 'The shop will inspect the vehicle again without a diagnostic fee.',
    ])->assertRedirect();

    $this->assertDatabaseHas('service_cases', ['id' => $case->id, 'status' => 'resolved', 'resolved_by' => $admin->id]);
    $this->assertDatabaseHas('service_case_events', ['service_case_id' => $case->id, 'to_status' => 'resolved', 'actor_id' => $admin->id]);
    $this->actingAs($customer)->get(route('customer.service-cases.index'))->assertSee('The shop will inspect the vehicle again');
});

it('requires a clear reason before dismissing a complaint and records the final outcome', function () {
    [$customer, $owner, $shop, $job] = aftercareFixture();
    $admin = User::factory()->create(['role' => 'admin']);
    $case = ServiceCase::factory()->forRepair($job)->create(['type' => 'complaint', 'subject' => 'Disputed repair']);

    $this->actingAs($admin)->patch(route('admin.service-cases.update', $case), [
        'case_id' => $case->id, 'status' => 'dismissed',
    ])->assertSessionHasErrors(['admin_resolution' => 'Write a reason before resolving or dismissing this complaint.']);
    $this->assertDatabaseHas('service_cases', ['id' => $case->id, 'status' => 'open']);
    $this->assertDatabaseCount('service_case_events', 0);

    $this->patch(route('admin.service-cases.update', $case), [
        'case_id' => $case->id, 'status' => 'dismissed', 'admin_resolution' => 'Short',
    ])->assertSessionHasErrors(['admin_resolution' => 'Write at least 10 characters so the customer and shop understand your decision.']);

    $this->patch(route('admin.service-cases.update', $case), [
        'case_id' => $case->id, 'status' => 'dismissed',
        'admin_resolution' => 'The submitted evidence does not support a further repair claim.',
    ])->assertRedirect()->assertSessionHas('status', 'Complaint dismissed. The customer and shop have been notified.');

    $this->assertDatabaseHas('service_cases', ['id' => $case->id, 'status' => 'dismissed', 'resolved_by' => $admin->id]);
    $this->assertDatabaseHas('service_case_events', ['service_case_id' => $case->id, 'to_status' => 'dismissed', 'actor_id' => $admin->id]);
    expect($customer->notifications()->count())->toBe(1);
    expect($owner->notifications()->count())->toBe(1);

    $this->patch(route('admin.service-cases.update', $case), [
        'status' => 'resolved', 'admin_resolution' => 'A second decision should not be saved.',
    ])->assertSessionHasErrors('status');
    $this->assertDatabaseCount('service_case_events', 1);
});

it('lets an admin mark a complaint as reviewing without a closing reason', function () {
    [$customer, $owner, $shop, $job] = aftercareFixture();
    $admin = User::factory()->create(['role' => 'admin']);
    $case = ServiceCase::factory()->forRepair($job)->create(['type' => 'complaint']);

    $this->actingAs($admin)->patch(route('admin.service-cases.update', $case), [
        'status' => 'reviewing',
    ])->assertRedirect()->assertSessionHas('status', 'Complaint marked as reviewing. The customer and shop have been notified.');

    $this->assertDatabaseHas('service_cases', ['id' => $case->id, 'status' => 'reviewing', 'admin_resolution' => null]);
    $this->assertDatabaseHas('service_case_events', ['service_case_id' => $case->id, 'to_status' => 'reviewing']);
});

/** @return array{User, User, Shop, RepairJob} */
function aftercareFixture(): array
{
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::factory()->create(['owner_id' => $owner->id, 'verification_status' => 'approved', 'shop_status' => 'active']);
    $vehicle = $customer->vehicles()->create(['plate_number' => 'AFTER-001', 'make' => 'Toyota', 'model' => 'Vios', 'year' => 2020]);
    $job = RepairJob::factory()->forVehicle($vehicle)->create(['shop_id' => $shop->id, 'job_number' => 'RJ-AFTER-001']);

    return [$customer, $owner, $shop, $job];
}

function aftercareImage(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JQqkAAAAASUVORK5CYII='));
}
