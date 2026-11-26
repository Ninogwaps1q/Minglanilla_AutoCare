<?php

use App\Jobs\DeliverWorkflowAlert;
use App\Models\Appointment;
use App\Models\Barangay;
use App\Models\MaintenanceReminder;
use App\Models\Quotation;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkflowAlert;
use App\Notifications\Workflow\AppointmentReminder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('records a no-show after the scheduled time and requires acknowledgment to cancel late', function () {
    $this->travelTo(Carbon::parse('2026-12-01 10:00:00', 'Asia/Manila'));
    $fixture = automationFixture();
    $details = [
        'customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id, 'status' => 'confirmed',
        'appointment_date' => '2026-12-01', 'appointment_time' => '11:00:00', 'cancellation_notice_hours' => 24,
    ];
    $appointment = Appointment::query()->create($details);
    $this->actingAs($fixture['shop']->owner)->patch(route('shop.appointments.update', $appointment), ['status' => 'no_show', 'note' => 'Did not arrive'])
        ->assertSessionHasErrors('status');
    $this->actingAs($fixture['customer'])->delete(route('customer.appointments.destroy', $appointment))
        ->assertSessionHasErrors('acknowledge_late_change');
    $this->delete(route('customer.appointments.destroy', $appointment), ['acknowledge_late_change' => '1'])->assertRedirect();
    expect($appointment->fresh()->status)->toBe('cancelled');

    $missed = Appointment::query()->create(array_replace($details, ['appointment_time' => '09:00:00']));
    $this->actingAs($fixture['shop']->owner)->patch(route('shop.appointments.update', $missed), ['status' => 'no_show'])
        ->assertSessionHasErrors('note');
    $this->patch(route('shop.appointments.update', $missed), ['status' => 'no_show', 'note' => 'Did not arrive'])->assertRedirect();
    expect($missed->fresh()->status)->toBe('no_show')
        ->and($missed->statusHistories()->sole()->note)->toBe('Did not arrive');
    $this->assertDatabaseHas('notifications', ['user_id' => $fixture['customer']->id, 'title' => 'The shop marked your appointment as a no-show']);
});

it('queues each Manila booking reminder once and suppresses stale notices after a time change', function () {
    Bus::fake([DeliverWorkflowAlert::class]);
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-12-01 10:00:00', 'Asia/Manila'));
    $fixture = automationFixture();
    $appointment = Appointment::query()->create([
        'customer_id' => $fixture['customer']->id, 'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id, 'status' => 'confirmed',
        'appointment_date' => '2026-12-02', 'appointment_time' => '10:00:00',
    ]);

    $this->artisan('app:dispatch-workflow-alerts')->assertSuccessful();
    $this->artisan('app:dispatch-workflow-alerts')->assertSuccessful();
    $first = WorkflowAlert::query()->sole();
    (new DeliverWorkflowAlert($first->id))->handle();
    Notification::assertSentTo($fixture['customer'], AppointmentReminder::class,
        fn (AppointmentReminder $notice) => str_contains($notice->message($fixture['customer']), '10:00 AM PHT'));
    $this->travel(22)->hours();
    $this->artisan('app:dispatch-workflow-alerts')->assertSuccessful();
    $second = WorkflowAlert::query()->where('id', '>', $first->id)->sole();
    $appointment->update(['appointment_date' => '2026-12-03']);
    (new DeliverWorkflowAlert($second->id))->handle();
    Notification::assertCount(2);
});

it('expires only sent quotations whose validity date has passed', function () {
    $fixture = automationFixture();
    $expired = Quotation::query()->create([
        'customer_id' => $fixture['customer']->id,
        'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id,
        'quotation_number' => 'QT-EXPIRED-001',
        'valid_until' => today()->subDay(),
        'status' => 'sent',
    ]);
    $current = Quotation::query()->create([
        'customer_id' => $fixture['customer']->id,
        'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id,
        'quotation_number' => 'QT-CURRENT-001',
        'valid_until' => today(),
        'status' => 'sent',
    ]);

    $this->artisan('app:expire-quotations')->assertExitCode(0);

    $this->assertDatabaseHas('quotations', ['id' => $expired->id, 'status' => 'expired']);
    $this->assertDatabaseHas('quotations', ['id' => $current->id, 'status' => 'sent']);
    $this->assertDatabaseHas('notifications', ['user_id' => $fixture['customer']->id, 'type' => 'quotation_expired']);

    $this->artisan('app:expire-quotations')->assertExitCode(0);
    expect($fixture['customer']->notifications()->where('type', 'quotation_expired')->count())->toBe(1);
});

it('sends one notification for a pending maintenance reminder that is due', function () {
    $fixture = automationFixture();
    $dueReminder = MaintenanceReminder::query()->create([
        'customer_id' => $fixture['customer']->id,
        'vehicle_id' => $fixture['vehicle']->id,
        'title' => 'Oil change',
        'message' => 'Replace the engine oil.',
        'reminder_date' => today(),
        'type' => 'date',
        'status' => 'pending',
    ]);
    MaintenanceReminder::query()->create([
        'customer_id' => $fixture['customer']->id,
        'vehicle_id' => $fixture['vehicle']->id,
        'title' => 'Future maintenance',
        'message' => 'Not due yet.',
        'reminder_date' => today()->addDay(),
        'type' => 'date',
        'status' => 'pending',
    ]);

    $this->artisan('app:send-maintenance-reminders')->assertExitCode(0);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $fixture['customer']->id,
        'type' => 'maintenance_due',
        'message' => 'Oil change is due for your vehicle.',
    ]);

    $this->artisan('app:send-maintenance-reminders')->assertExitCode(0);
    $this->assertDatabaseCount('notifications', 1);
    expect($dueReminder->fresh()->status)->toBe('pending');
});

it('notifies each due reminder independently even when titles match', function () {
    $fixture = automationFixture();
    foreach ([1, 2] as $number) {
        MaintenanceReminder::query()->create([
            'customer_id' => $fixture['customer']->id, 'vehicle_id' => $fixture['vehicle']->id,
            'title' => 'Oil change', 'message' => "Maintenance {$number}", 'reminder_date' => today(),
            'type' => 'date', 'status' => 'pending',
        ]);
    }

    $this->artisan('app:send-maintenance-reminders')->assertExitCode(0);
    $this->artisan('app:send-maintenance-reminders')->assertExitCode(0);

    $this->assertDatabaseCount('notifications', 2);
});

/**
 * @return array{customer: User, shop: Shop, vehicle: Vehicle}
 */
function automationFixture(): array
{
    $barangay = Barangay::factory()->create();
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Automation Shop',
        'phone' => '09170000000',
        'email' => 'automation@example.test',
        'address' => 'Demo Street',
        'barangay_id' => $barangay->id,
        'verification_status' => 'approved',
        'shop_status' => 'active',
    ]);
    $vehicle = $customer->vehicles()->create([
        'plate_number' => 'AUTO-001',
        'make' => 'Toyota',
        'model' => 'Vios',
        'year' => 2020,
    ]);

    return compact('customer', 'shop', 'vehicle');
}
