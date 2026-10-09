<?php

use App\Models\Barangay;
use App\Models\MaintenanceReminder;
use App\Models\Quotation;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
    $this->assertDatabaseCount('notifications', 1);
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
