<?php

use App\Models\Appointment;
use App\Models\Barangay;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the administrative dashboard and reports to an administrator', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('System overview');

    $this->actingAs($admin)->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSee('System reports')
        ->assertSee('Confirmed payments');
});

it('does not allow a customer to view administrative reports', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)->get(route('admin.reports.index'))->assertForbidden();
});

it('filters administrative report activity by the requested date range', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $fixture = reportingFixture();
    $service = Service::factory()->create();

    Appointment::query()->create([
        'customer_id' => $fixture['customer']->id,
        'shop_id' => $fixture['shop']->id,
        'vehicle_id' => $fixture['vehicle']->id,
        'service_id' => $service->id,
        'appointment_date' => '2026-10-03',
        'appointment_time' => '10:00',
        'status' => 'confirmed',
    ]);
    Payment::query()->create([
        'invoice_id' => $fixture['invoice']->id,
        'customer_id' => $fixture['customer']->id,
        'shop_id' => $fixture['shop']->id,
        'amount' => 1500,
        'payment_method' => 'cash',
        'payment_date' => '2026-10-03 10:00:00',
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.reports.index', ['from' => '2026-10-01', 'to' => '2026-10-05']));

    $response->assertOk()
        ->assertSee('Oct 1, 2026')
        ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['Appointments'] === 1 && (float) $metrics['Confirmed payments'] === 1500.0);
    expect(Report::query()->where('generated_by', $admin->id)->doesntExist())->toBeTrue();

    $download = $this->actingAs($admin)->get(route('admin.reports.download', ['from' => '2026-10-01', 'to' => '2026-10-05']));
    $download->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('platform-report-2026-10-01-2026-10-05.pdf');

    $report = Report::query()->where('generated_by', $admin->id)->sole();
    expect($report->report_type)->toBe('platform_operations')
        ->and($report->date_from->toDateString())->toBe('2026-10-01')
        ->and($report->date_to->toDateString())->toBe('2026-10-05');
});

it('lets a shop owner download only their own performance report', function () {
    $fixture = reportingFixture();
    $owner = $fixture['shop']->owner;

    $response = $this->actingAs($owner)->get(route('shop.reports.download', [
        'from' => '2026-10-01',
        'to' => '2026-10-05',
    ]));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload("shop-report-{$fixture['shop']->id}-2026-10-01-2026-10-05.pdf");
    $this->assertDatabaseHas('reports', [
        'generated_by' => $owner->id,
        'report_type' => 'shop_performance',
        'date_from' => '2026-10-01 00:00:00',
        'date_to' => '2026-10-05 00:00:00',
    ]);
});

it('counts appointment statuses within the selected dates and report scope', function (string $role, int $confirmedCount) {
    $fixture = reportingFixture();
    $otherShop = Shop::factory()->create();
    $service = Service::factory()->create();
    $viewer = $role === 'admin' ? User::factory()->create(['role' => 'admin']) : $fixture['shop']->owner;

    foreach ([
        [$fixture['shop']->id, '2026-10-01', 'pending'],
        [$fixture['shop']->id, '2026-10-31', 'completed'],
        [$fixture['shop']->id, '2026-09-30', 'cancelled'],
        [$fixture['shop']->id, '2026-11-01', 'cancelled'],
        [$otherShop->id, '2026-10-15', 'confirmed'],
    ] as [$shopId, $date, $status]) {
        Appointment::query()->create([
            'customer_id' => $fixture['customer']->id,
            'shop_id' => $shopId,
            'vehicle_id' => $fixture['vehicle']->id,
            'service_id' => $service->id,
            'appointment_date' => $date,
            'appointment_time' => '10:00',
            'status' => $status,
        ]);
    }

    $response = $this->actingAs($viewer)->get(route($role === 'admin' ? 'admin.reports.index' : 'shop.reports.index', [
        'from' => '2026-10-01',
        'to' => '2026-10-31',
    ]));

    $response->assertOk()->assertViewHas('appointmentStatusCounts', [
        'pending' => 1,
        'confirmed' => $confirmedCount,
        'in_progress' => 0,
        'completed' => 1,
        'cancelled' => 0,
    ])->assertSee('Appointment status')
        ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['Appointments'] === 2 + $confirmedCount)
        ->assertViewHas('appointmentSeries', fn (array $series): bool => $series['values'][0] === 1.0 && $series['values'][30] === 1.0)
        ->assertViewHas('recentAppointments', fn ($appointments): bool => $appointments->count() === 2 + $confirmedCount);
})->with(['platform report' => ['admin', 1], 'own shop report' => ['shop_owner', 0]]);

it('rejects invalid report date ranges', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['from' => 'invalid-date', 'to' => '2026-10-05']))
        ->assertSessionHasErrors('from');
});

it('limits shop reports to the authenticated owners shop', function () {
    $fixture = reportingFixture();
    $otherOwner = User::factory()->create(['role' => 'shop_owner']);
    $otherShop = Shop::query()->create([
        'owner_id' => $otherOwner->id,
        'barangay_id' => $fixture['shop']->barangay_id,
        'name' => 'Other reporting shop',
        'phone' => '09170000002',
        'email' => 'other-reporting@example.test',
        'address' => 'Other Street',
    ]);

    Payment::query()->create([
        'invoice_id' => $fixture['invoice']->id,
        'customer_id' => $fixture['customer']->id,
        'shop_id' => $fixture['shop']->id,
        'amount' => 800,
        'payment_method' => 'cash',
        'payment_date' => now(),
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($otherOwner)->get(route('shop.reports.index'));

    $response->assertOk()
        ->assertViewHas('shop', fn (Shop $shop): bool => $shop->is($otherShop))
        ->assertViewHas('metrics', fn (array $metrics): bool => (float) $metrics['Confirmed revenue'] === 0.0);
});

it('lets an administrator suspend a non-admin account and records the action', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $customer), ['status' => 'suspended'])
        ->assertRedirect();

    $this->assertDatabaseHas('users', ['id' => $customer->id, 'status' => 'suspended']);
    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $admin->id,
        'action' => 'updated_account_status',
        'module' => 'user_management',
    ]);
});

it('does not authenticate a suspended account', function () {
    $suspendedUser = User::factory()->create(['status' => 'suspended']);

    $this->post(route('login'), ['email' => $suspendedUser->email, 'password' => 'password'])
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

/** @return array{customer: User, shop: Shop, invoice: Invoice, vehicle: Vehicle} */
function reportingFixture(): array
{
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::query()->create([
        'owner_id' => $owner->id,
        'barangay_id' => Barangay::factory()->create()->id,
        'name' => 'Reporting Shop',
        'phone' => '09170000001',
        'email' => 'reporting@example.test',
        'address' => 'Report Street',
    ]);
    $invoice = Invoice::query()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'invoice_number' => 'INV-REPORT-001',
        'subtotal' => 1500,
        'total' => 1500,
    ]);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);

    return compact('customer', 'shop', 'invoice', 'vehicle');
}
