<?php

use App\Models\Appointment;
use App\Models\Barangay;
use App\Models\RepairJob;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Shop;
use App\Models\ShopService;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a pending appointment for an available public shop', function () {
    $fixture = appointmentFixture();

    $response = $this->actingAs($fixture['customer'])->post(route('customer.appointments.store'), appointmentPayload($fixture));

    $response->assertRedirect(route('customer.appointments.index'));
    $this->assertDatabaseHas('notifications', [
        'user_id' => $fixture['owner']->id,
        'type' => 'appointment_requested',
    ]);
    $this->assertDatabaseHas('appointments', [
        'customer_id' => $fixture['customer']->id,
        'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id,
        'service_id' => $fixture['service']->id,
        'service_price' => '900.00',
        'status' => 'pending',
    ]);
});

it('includes the booked service price once in the approved estimate and final bill', function () {
    $fixture = appointmentFixture();
    $this->actingAs($fixture['customer'])->post(route('customer.appointments.store'), appointmentPayload($fixture))
        ->assertRedirect(route('customer.appointments.index'));
    $appointment = Appointment::query()->sole();
    expect($appointment->service_price)->toBe('900.00');
    expect($appointment->service_labor_included)->toBeTrue();
    expect($appointment->service_parts_included)->toBeFalse();
    expect($appointment->service_scope)->toBe('Standard oil change labor only; filter is extra.');

    ShopService::query()->where('shop_id', $fixture['shop']->id)
        ->where('service_id', $fixture['service']->id)->update([
            'price' => 1500, 'labor_included' => false, 'parts_included' => true,
            'description' => 'Changed after booking',
        ]);

    $this->actingAs($fixture['owner'])->patch(route('shop.appointments.update', $appointment), ['status' => 'confirmed'])
        ->assertRedirect();
    $this->post(route('shop.repair-jobs.store'), [
        'appointment_id' => $appointment->id, 'description' => 'Oil change with additional filter work',
        'estimated_cost' => 1,
    ])->assertRedirect(route('shop.repair-jobs.index'))->assertSessionHasNoErrors();

    $job = RepairJob::query()->where('appointment_id', $appointment->id)->sole();
    $serviceItem = $job->items()->sole();
    expect($serviceItem->description)->toBe('Oil Change');
    expect($serviceItem->service_id)->toBe($fixture['service']->id);
    expect($serviceItem->unit_price)->toBe('900.00');
    expect($job->estimated_cost)->toBe('900.00');
    expect($job->actual_cost)->toBe('900.00');
    $this->delete(route('shop.repair-job-items.destroy', $serviceItem))->assertConflict();

    $this->post(route('shop.repair-job-items.store', $job), [
        'type' => 'part', 'description' => 'Oil filter', 'quantity' => 1, 'unit_price' => 250,
    ])->assertRedirect();
    expect($job->fresh()->actual_cost)->toBe('1150.00');

    $this->post(route('shop.quotations.store', $job), [
        'items' => [['description' => 'Tampered price', 'quantity' => 1, 'unit_price' => 1]],
    ])->assertRedirect(route('shop.repair-jobs.index'))->assertSessionHasNoErrors();
    $quote = $job->fresh()->quotation;
    expect($quote->total)->toBe('1150.00');
    expect($quote->items()->orderBy('id')->pluck('description')->all())->toBe(['Oil Change', 'Oil filter']);

    $this->actingAs($fixture['customer'])->patch(route('customer.quotations.update', $quote), ['status' => 'accepted'])
        ->assertRedirect();
    $this->get(route('customer.repairs.index', ['repair_job_id' => $job->id]))
        ->assertSee('Booked price: PHP 900.00')
        ->assertSee('Labor: included')
        ->assertSee('Parts: quoted separately');
    $this->actingAs($fixture['owner'])->patch(route('shop.repair-jobs.update', $job), ['status' => 'repairing'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->patch(route('shop.repair-jobs.update', $job), ['status' => 'completed'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('shop.invoices.store', $job))->assertRedirect(route('shop.invoices.index'))
        ->assertSessionHasNoErrors();

    $invoice = $job->fresh()->invoice;
    expect($invoice->total)->toBe('1150.00');
    expect($invoice->items()->orderBy('id')->pluck('description')->all())->toBe(['Service: Oil Change', 'Part: Oil filter']);
    $this->actingAs($fixture['customer'])->get(route('customer.invoices.show', $invoice))
        ->assertSee('labor included; parts quoted separately')
        ->assertDontSee('Changed after booking');
});

it('uses the current shop offer for a legacy appointment with no saved price', function () {
    $fixture = appointmentFixture();
    $appointment = $fixture['customer']->appointments()->create(appointmentPayload($fixture, ['status' => 'confirmed']));

    $this->actingAs($fixture['owner'])->post(route('shop.repair-jobs.store'), [
        'appointment_id' => $appointment->id, 'description' => 'Oil change',
    ])->assertRedirect(route('shop.repair-jobs.index'))->assertSessionHasNoErrors();

    $job = RepairJob::query()->where('appointment_id', $appointment->id)->sole();
    expect($job->items()->sole()->unit_price)->toBe('900.00');
});

it('lets the shop reconcile an old draft repair before sending a service-inclusive estimate', function () {
    $fixture = appointmentFixture();
    $appointment = $fixture['customer']->appointments()->create(appointmentPayload($fixture, ['status' => 'in_progress']));
    $job = RepairJob::query()->create([
        'customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id, 'appointment_id' => $appointment->id,
        'job_number' => 'RJ-OLD-BOOKING', 'status' => 'diagnosis', 'description' => 'Oil change',
    ]);
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    Shop::factory()->create(['owner_id' => $otherOwner->id]);
    $this->actingAs($otherOwner)->post(route('shop.repair-jobs.booked-service', $job))->assertNotFound();

    $this->actingAs($fixture['owner'])->get(route('shop.repair-jobs.index'))
        ->assertSee('Booked service needs review')->assertSee('Add booked service');
    $this->post(route('shop.quotations.store', $job), [
        'items' => [['description' => 'Miscellaneous', 'quantity' => 1, 'unit_price' => 100]],
    ])->assertSessionHasErrors('items');
    $manual = $job->items()->create([
        'type' => 'service', 'description' => 'Oil Change', 'quantity' => 1,
        'unit_price' => 900, 'subtotal' => 900,
    ]);
    $this->post(route('shop.repair-jobs.booked-service', $job))->assertSessionHasErrors('service');
    $this->delete(route('shop.repair-job-items.destroy', $manual))->assertRedirect();

    $this->post(route('shop.repair-jobs.booked-service', $job))->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('shop.repair-jobs.booked-service', $job))->assertRedirect()->assertSessionHasNoErrors();
    expect($job->items()->count())->toBe(1);
    expect($job->items()->sole()->unit_price)->toBe('900.00');
    $this->post(route('shop.quotations.store', $job), [])->assertRedirect()->assertSessionHasNoErrors();
    expect($job->fresh()->quotation->total)->toBe('900.00');
    $this->post(route('shop.repair-jobs.booked-service', $job))->assertSessionHasErrors('service');
});

it('rejects a booking that uses another customer vehicle', function () {
    $fixture = appointmentFixture();
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $otherVehicle = $otherCustomer->vehicles()->create([
        'plate_number' => 'OTHER-001',
        'make' => 'Honda',
        'model' => 'City',
        'year' => 2022,
    ]);

    $response = $this->actingAs($fixture['customer'])->from(route('customer.appointments.create'))
        ->post(route('customer.appointments.store'), appointmentPayload($fixture, ['vehicle_id' => $otherVehicle->id]));

    $response->assertRedirect(route('customer.appointments.create'));
    $response->assertSessionHasErrors('vehicle_id');
    $this->assertDatabaseMissing('appointments', ['vehicle_id' => $otherVehicle->id]);
});

it('rejects a conflicting active appointment slot for the same shop', function () {
    $fixture = appointmentFixture();
    Appointment::query()->create(array_merge(appointmentPayload($fixture), ['customer_id' => $fixture['customer']->id, 'status' => 'confirmed']));
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $otherVehicle = $otherCustomer->vehicles()->create(['plate_number' => 'SLOT-002', 'make' => 'Honda', 'model' => 'City', 'year' => 2021]);

    $response = $this->actingAs($otherCustomer)->from(route('customer.appointments.create'))
        ->post(route('customer.appointments.store'), appointmentPayload($fixture, ['vehicle_id' => $otherVehicle->id]));

    $response->assertRedirect(route('customer.appointments.create'));
    $response->assertSessionHasErrors('appointment_time');
    $this->assertDatabaseCount('appointments', 1);
});

it('blocks overlapping service time and saves the duration shown at booking', function () {
    $fixture = appointmentFixture();
    $fixture['service']->update(['estimated_duration' => 90]);
    $fixture['shop']->update(['opening_time' => '08:00', 'closing_time' => '17:00']);
    Appointment::query()->create(array_merge(appointmentPayload($fixture), [
        'customer_id' => $fixture['customer']->id, 'appointment_time' => '10:00',
        'duration_minutes' => 90, 'status' => 'confirmed',
    ]));
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $otherCustomer->id]);
    $this->actingAs($otherCustomer);

    $this->post(route('customer.appointments.store'), appointmentPayload($fixture, ['vehicle_id' => $vehicle->id, 'appointment_time' => '11:00']))
        ->assertSessionHasErrors('appointment_time');
    $this->post(route('customer.appointments.store'), appointmentPayload($fixture, ['vehicle_id' => $vehicle->id, 'appointment_time' => '11:30']))
        ->assertRedirect(route('customer.appointments.index'));
    $this->assertDatabaseHas('appointments', ['customer_id' => $otherCustomer->id, 'appointment_time' => '11:30', 'duration_minutes' => 90]);
});

it('allows only the configured number of overlapping shop appointments', function () {
    $fixture = appointmentFixture();
    $fixture['shop']->update(['booking_capacity' => 2]);
    foreach (['10:00', '10:30'] as $time) {
        Appointment::query()->create(array_merge(appointmentPayload($fixture), [
            'customer_id' => $fixture['customer']->id, 'appointment_time' => $time,
            'duration_minutes' => 90, 'status' => 'confirmed',
        ]));
    }
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $otherCustomer->id]);

    $this->actingAs($otherCustomer)->post(route('customer.appointments.store'), appointmentPayload($fixture, ['vehicle_id' => $vehicle->id, 'appointment_time' => '11:00']))
        ->assertSessionHasErrors('appointment_time');
    $this->assertDatabaseCount('appointments', 2);
});

it('rejects past times today and services that would finish after closing', function () {
    $fixture = appointmentFixture();
    $fixture['service']->update(['estimated_duration' => 90]);
    $fixture['shop']->update(['opening_time' => '08:00', 'closing_time' => '17:00']);
    $this->travelTo(today()->setTime(13, 0));
    $this->actingAs($fixture['customer']);

    $this->post(route('customer.appointments.store'), appointmentPayload($fixture, ['appointment_date' => today()->toDateString(), 'appointment_time' => '12:00']))
        ->assertSessionHasErrors('appointment_time');
    $this->post(route('customer.appointments.store'), appointmentPayload($fixture, ['appointment_time' => '16:00']))
        ->assertSessionHasErrors('appointment_time');
    $this->assertDatabaseCount('appointments', 0);
});

it('shows booked times and shop hours without exposing customer details', function () {
    $fixture = appointmentFixture();
    $fixture['shop']->update(['opening_time' => '08:00', 'closing_time' => '17:00']);
    $date = today()->addDay()->toDateString();
    Appointment::query()->create(array_merge(appointmentPayload($fixture), ['customer_id' => $fixture['customer']->id, 'appointment_date' => $date, 'appointment_time' => '10:00', 'status' => 'confirmed']));
    Appointment::query()->create(array_merge(appointmentPayload($fixture), ['customer_id' => $fixture['customer']->id, 'appointment_date' => $date, 'appointment_time' => '11:00', 'status' => 'cancelled']));

    $response = $this->actingAs($fixture['customer'])->get(route('customer.appointments.availability', ['shop_id' => $fixture['shop']->id, 'date' => $date]));

    $response->assertOk()->assertJsonPath('opening_time', '08:00')->assertJsonPath('closing_time', '17:00')
        ->assertJsonPath('taken_times', ['10:00'])->assertDontSee($fixture['customer']->name);
});

it('does not show availability for inactive shops or to guests', function () {
    $fixture = appointmentFixture();
    $url = route('customer.appointments.availability', ['shop_id' => $fixture['shop']->id, 'date' => today()->addDay()->toDateString()]);

    $this->get($url)->assertRedirect(route('login'));
    $fixture['shop']->update(['shop_status' => 'suspended']);
    $this->actingAs($fixture['customer'])->get($url)->assertNotFound();
});

it('rejects appointment times outside published shop hours', function (string $time) {
    $fixture = appointmentFixture();
    $fixture['shop']->update(['opening_time' => '08:00', 'closing_time' => '17:00']);

    $this->actingAs($fixture['customer'])->post(route('customer.appointments.store'), appointmentPayload($fixture, ['appointment_time' => $time]))
        ->assertSessionHasErrors('appointment_time');
    $this->assertDatabaseCount('appointments', 0);
})->with(['07:30', '17:00']);

it('paginates appointments and preserves a status filter for both customer and shop', function () {
    $fixture = appointmentFixture();
    foreach (range(1, 11) as $day) {
        Appointment::query()->create(array_merge(appointmentPayload($fixture), [
            'customer_id' => $fixture['customer']->id,
            'appointment_date' => today()->addDays($day)->toDateString(),
            'status' => 'pending',
        ]));
    }
    Appointment::query()->create(array_merge(appointmentPayload($fixture), [
        'customer_id' => $fixture['customer']->id,
        'appointment_date' => today()->addDays(12)->toDateString(),
        'status' => 'confirmed',
    ]));

    $this->actingAs($fixture['customer'])->get(route('customer.appointments.index'))
        ->assertOk()->assertViewHas('appointments', fn ($appointments) => $appointments->count() === 10 && $appointments->total() === 12);
    $this->get(route('customer.appointments.index', ['status' => 'confirmed']))
        ->assertOk()->assertViewHas('appointments', fn ($appointments) => $appointments->count() === 1 && str_contains($appointments->nextPageUrl() ?? $appointments->url(1), 'status=confirmed'));
    $this->actingAs($fixture['owner'])->get(route('shop.appointments.index', ['status' => 'pending']))
        ->assertOk()->assertViewHas('appointments', fn ($appointments) => $appointments->count() === 10 && $appointments->total() === 11);
});

it('does not let a customer cancel another customer appointment', function () {
    $fixture = appointmentFixture();
    $appointment = Appointment::query()->create(array_merge(appointmentPayload($fixture), ['customer_id' => $fixture['customer']->id]));
    $otherCustomer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($otherCustomer)
        ->delete(route('customer.appointments.destroy', $appointment))
        ->assertNotFound();
});

it('lets a customer request a new time and the shop approve it', function () {
    $fixture = appointmentFixture();
    $appointment = $fixture['customer']->appointments()->create(appointmentPayload($fixture, ['status' => 'confirmed']));
    $newDate = today()->addDays(2)->toDateString();

    $this->actingAs($fixture['customer'])->post(route('customer.appointments.reschedule.request', $appointment), [
        'requested_date' => $newDate, 'requested_time' => '11:00', 'reason' => 'Work schedule changed',
    ])->assertRedirect();
    expect($appointment->fresh()->appointment_date->toDateString())->toBe(today()->addDay()->toDateString());
    $this->assertDatabaseHas('appointment_reschedule_requests', [
        'appointment_id' => $appointment->id, 'status' => 'pending',
    ]);
    expect($appointment->rescheduleRequests()->firstOrFail()->requested_date->toDateString())->toBe($newDate);
    $this->actingAs($fixture['owner'])->patch(route('shop.appointments.reschedule.decide', $appointment), ['decision' => 'approve'])->assertRedirect();
    expect($appointment->fresh()->appointment_date->toDateString())->toBe($newDate);
    expect($appointment->fresh()->appointment_time->format('H:i'))->toBe('11:00');
    $this->assertDatabaseHas('appointment_reschedule_requests', [
        'appointment_id' => $appointment->id, 'status' => 'approved', 'decided_by' => $fixture['owner']->id,
    ]);
    $this->assertDatabaseHas('notifications', ['user_id' => $fixture['customer']->id, 'type' => 'appointment_reschedule_decided']);
});

it('keeps the original time when the shop declines and prevents duplicate pending requests', function () {
    $fixture = appointmentFixture();
    $appointment = $fixture['customer']->appointments()->create(appointmentPayload($fixture, ['status' => 'confirmed']));
    $payload = ['requested_date' => today()->addDays(2)->toDateString(), 'requested_time' => '11:00'];

    $this->actingAs($fixture['customer'])->post(route('customer.appointments.reschedule.request', $appointment), $payload)->assertRedirect();
    $this->post(route('customer.appointments.reschedule.request', $appointment), $payload)->assertSessionHasErrors('requested_date');
    $this->assertDatabaseCount('appointment_reschedule_requests', 1);
    $this->actingAs($fixture['owner'])->patch(route('shop.appointments.reschedule.decide', $appointment), ['decision' => 'decline'])->assertRedirect();
    expect($appointment->fresh()->appointment_date->toDateString())->toBe(today()->addDay()->toDateString());
    $this->assertDatabaseHas('appointment_reschedule_requests', ['appointment_id' => $appointment->id, 'status' => 'declined']);
});

it('rechecks capacity at approval and keeps the original booking if the new slot fills', function () {
    $fixture = appointmentFixture();
    $appointment = $fixture['customer']->appointments()->create(appointmentPayload($fixture, ['status' => 'confirmed']));
    $newDate = today()->addDays(2)->toDateString();
    $this->actingAs($fixture['customer'])->post(route('customer.appointments.reschedule.request', $appointment), [
        'requested_date' => $newDate, 'requested_time' => '11:00',
    ])->assertRedirect();
    Appointment::query()->create(array_merge(appointmentPayload($fixture), [
        'customer_id' => $fixture['customer']->id, 'appointment_date' => $newDate, 'appointment_time' => '11:00', 'status' => 'confirmed',
    ]));

    $this->actingAs($fixture['owner'])->patch(route('shop.appointments.reschedule.decide', $appointment), ['decision' => 'approve'])
        ->assertSessionHasErrors('requested_time');
    expect($appointment->fresh()->appointment_date->toDateString())->toBe(today()->addDay()->toDateString());
    $this->assertDatabaseHas('appointment_reschedule_requests', ['appointment_id' => $appointment->id, 'status' => 'pending']);
});

it('does not let another customer request a change to an appointment they do not own', function () {
    $fixture = appointmentFixture();
    $appointment = $fixture['customer']->appointments()->create(appointmentPayload($fixture, ['status' => 'confirmed']));
    $stranger = User::factory()->create(['role' => 'customer']);

    $this->actingAs($stranger)->post(route('customer.appointments.reschedule.request', $appointment), [
        'requested_date' => today()->addDays(2)->toDateString(), 'requested_time' => '11:00',
    ])->assertNotFound();
    $this->assertDatabaseCount('appointment_reschedule_requests', 0);
});

it('does not let another shop decide a time change or start repair while one is pending', function () {
    $fixture = appointmentFixture();
    $appointment = $fixture['customer']->appointments()->create(appointmentPayload($fixture, ['status' => 'confirmed']));
    $this->actingAs($fixture['customer'])->post(route('customer.appointments.reschedule.request', $appointment), [
        'requested_date' => today()->addDays(2)->toDateString(), 'requested_time' => '11:00',
    ])->assertRedirect();
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    Shop::factory()->create(['owner_id' => $otherOwner->id]);
    $this->actingAs($otherOwner)->patch(route('shop.appointments.reschedule.decide', $appointment), ['decision' => 'approve'])->assertNotFound();
    $this->actingAs($fixture['owner'])->post(route('shop.repair-jobs.store'), [
        'appointment_id' => $appointment->id, 'description' => 'Oil change',
    ])->assertSessionHasErrors('appointment_id');
    $this->assertDatabaseCount('repair_jobs', 0);
});

it('updates the appointment status for the shop owner', function () {
    $fixture = appointmentFixture();
    $appointment = Appointment::query()->create(array_merge(appointmentPayload($fixture), ['customer_id' => $fixture['customer']->id]));

    $response = $this->actingAs($fixture['owner'])
        ->patch(route('shop.appointments.update', $appointment), ['status' => 'confirmed']);

    $response->assertRedirect();
    $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'confirmed']);
    $this->assertDatabaseHas('appointment_status_histories', [
        'appointment_id' => $appointment->id,
        'from_status' => 'pending',
        'to_status' => 'confirmed',
        'changed_by' => $fixture['owner']->id,
    ]);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $fixture['customer']->id,
        'type' => 'appointment_status',
    ]);
});

it('connects a confirmed appointment directly to its repair for both roles', function () {
    $fixture = appointmentFixture();
    $appointment = $fixture['customer']->appointments()->create(appointmentPayload($fixture, ['status' => 'confirmed']));

    $this->actingAs($fixture['owner'])->get(route('shop.appointments.index', ['status' => 'confirmed']))
        ->assertSee(route('shop.repair-jobs.create', ['appointment_id' => $appointment->id]));
    $this->get(route('shop.repair-jobs.create', ['appointment_id' => $appointment->id]))
        ->assertViewHas('selectedAppointmentId', $appointment->id);

    $this->post(route('shop.repair-jobs.store'), [
        'appointment_id' => $appointment->id,
        'description' => 'Oil change service',
    ])->assertRedirect(route('shop.repair-jobs.index'));

    $repairJob = RepairJob::query()->where('appointment_id', $appointment->id)->firstOrFail();
    expect($appointment->fresh()->status)->toBe('in_progress');
    $this->get(route('shop.appointments.index', ['status' => 'in_progress']))
        ->assertSee(route('shop.repair-jobs.index', ['appointment_id' => $appointment->id]));
    $this->get(route('shop.repair-jobs.index', ['appointment_id' => $appointment->id]))
        ->assertSee($repairJob->job_number);

    $this->actingAs($fixture['customer'])->get(route('customer.appointments.index', ['status' => 'in_progress']))
        ->assertSee('In repair')
        ->assertSee(route('customer.repairs.index', ['appointment_id' => $appointment->id]));
    $this->get(route('customer.repairs.index', ['appointment_id' => $appointment->id]))
        ->assertSee($repairJob->job_number);
});

it('does not show another customers repair through an appointment link', function () {
    $fixture = appointmentFixture();
    $appointment = $fixture['customer']->appointments()->create(appointmentPayload($fixture, ['status' => 'in_progress']));
    RepairJob::query()->create([
        'customer_id' => $fixture['customer']->id,
        'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id,
        'appointment_id' => $appointment->id,
        'job_number' => 'RJ-PRIVATE-001',
        'description' => 'Private repair',
        'status' => 'repairing',
    ]);
    $otherCustomer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($otherCustomer)->get(route('customer.repairs.index', ['appointment_id' => $appointment->id]))
        ->assertDontSee('RJ-PRIVATE-001');
    $this->get(route('customer.appointments.index', ['status' => 'in_progress']))
        ->assertDontSee($fixture['vehicle']->plate_number);
});

it('blocks invalid appointment status transitions', function () {
    $fixture = appointmentFixture();
    $appointment = Appointment::query()->create(array_merge(appointmentPayload($fixture), ['customer_id' => $fixture['customer']->id, 'status' => 'pending']));

    $this->actingAs($fixture['owner'])
        ->patch(route('shop.appointments.update', $appointment), ['status' => 'completed'])
        ->assertSessionHasErrors('status');

    expect($appointment->fresh()->status)->toBe('pending');
    $this->assertDatabaseCount('appointment_status_histories', 0);
});

it('does not let a shop owner update another shop appointment', function () {
    $fixture = appointmentFixture();
    $appointment = Appointment::query()->create(array_merge(appointmentPayload($fixture), ['customer_id' => $fixture['customer']->id]));
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    $otherShop = Shop::query()->create([
        'owner_id' => $otherOwner->id,
        'name' => 'Other Shop',
        'phone' => '09170000002',
        'email' => 'other-shop@example.test',
        'address' => 'Other Street',
        'barangay_id' => $fixture['barangay']->id,
        'verification_status' => 'approved',
        'shop_status' => 'active',
    ]);
    $appointment->update(['shop_id' => $otherShop->id]);

    $this->actingAs($fixture['owner'])
        ->patch(route('shop.appointments.update', $appointment), ['status' => 'confirmed'])
        ->assertNotFound();
});

it('records customer cancellation history and notifies the shop exactly once', function () {
    $fixture = appointmentFixture();
    $appointment = $fixture['customer']->appointments()->create(appointmentPayload($fixture));

    $this->actingAs($fixture['customer'])->delete(route('customer.appointments.destroy', $appointment))->assertRedirect();
    $this->delete(route('customer.appointments.destroy', $appointment))->assertNotFound();

    $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'cancelled']);
    $this->assertDatabaseHas('appointment_status_histories', [
        'appointment_id' => $appointment->id, 'from_status' => 'pending',
        'to_status' => 'cancelled', 'changed_by' => $fixture['customer']->id,
    ]);
    $this->assertDatabaseCount('appointment_status_histories', 1);
    $this->assertDatabaseHas('notifications', ['user_id' => $fixture['owner']->id, 'type' => 'appointment_cancelled']);
    $this->assertDatabaseCount('notifications', 1);
});

/**
 * @return array{barangay: Barangay, customer: User, owner: User, shop: Shop, vehicle: Vehicle, service: Service}
 */
function appointmentFixture(): array
{
    $barangay = Barangay::factory()->create();
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Appointment Shop',
        'phone' => '09170000000',
        'email' => 'appointment-shop@example.test',
        'address' => 'Demo Street',
        'barangay_id' => $barangay->id,
        'verification_status' => 'approved',
        'shop_status' => 'active',
    ]);
    $vehicle = $customer->vehicles()->create([
        'plate_number' => 'BOOK-001',
        'make' => 'Toyota',
        'model' => 'Vios',
        'year' => 2020,
    ]);
    $category = ServiceCategory::query()->create(['name' => 'Maintenance']);
    $service = Service::query()->create(['category_id' => $category->id, 'name' => 'Oil Change']);
    ShopService::query()->create([
        'shop_id' => $shop->id,
        'service_id' => $service->id,
        'price' => 900,
        'labor_included' => true,
        'parts_included' => false,
        'description' => 'Standard oil change labor only; filter is extra.',
        'availability' => true,
    ]);

    return compact('barangay', 'customer', 'owner', 'shop', 'vehicle', 'service');
}

/**
 * @param  array{shop: Shop, vehicle: Vehicle, service: Service}  $fixture
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function appointmentPayload(array $fixture, array $overrides = []): array
{
    return array_merge([
        'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id,
        'service_id' => $fixture['service']->id,
        'appointment_date' => today()->addDay()->toDateString(),
        'appointment_time' => '10:00',
        'reason' => 'Routine maintenance',
    ], $overrides);
}
