<?php

use App\Models\Appointment;
use App\Models\Barangay;
use App\Models\Invoice;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AI\AiServiceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders every monitoring module for administrators', function (string $module) {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.monitoring.index', ['module' => $module]))->assertOk()->assertSee('Operations monitoring');
})->with(['appointments', 'repairs', 'quotations', 'invoices', 'payments', 'emergencies']);

it('denies monitoring access to non-administrators', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.monitoring.index'))->assertForbidden();
})->with(['customer', 'shop_owner']);

it('redirects monitoring guests to sign in', function () {
    $this->get(route('admin.monitoring.index'))->assertRedirect(route('login'));
});

it('filters persisted invoices by status and customer while escaping names', function () {
    $customer = User::factory()->create(['role' => 'customer', 'name' => '<script>monitor</script>']);
    $shop = Shop::factory()->create([
        'owner_id' => User::factory()->create(['role' => 'shop_owner'])->id,
        'barangay_id' => Barangay::factory()->create()->id,
        'name' => 'Monitoring Shop', 'phone' => '09170000000',
        'email' => 'monitoring@example.test', 'address' => 'Minglanilla',
    ]);
    foreach (['paid', 'unpaid'] as $status) {
        Invoice::query()->create([
            'customer_id' => $customer->id, 'shop_id' => $shop->id,
            'invoice_number' => 'AUDIT-'.$status, 'status' => $status, 'total' => 125,
        ]);
    }
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.monitoring.index', ['module' => 'invoices', 'status' => 'paid', 'search' => 'monitor']))
        ->assertSee('AUDIT-paid')->assertDontSee('AUDIT-unpaid')
        ->assertSee('<script>monitor</script>')->assertDontSee('<script>monitor</script>', false);
});

it('shows read-only appointment details and a linked status timeline', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $shop = Shop::factory()->create();
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id, 'plate_number' => 'MON-1234']);
    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id, 'shop_id' => $shop->id,
        'vehicle_id' => $vehicle->id, 'appointment_date' => today()->addDay(),
        'appointment_time' => '10:00', 'status' => 'confirmed',
    ]);
    $appointment->statusHistories()->create([
        'from_status' => 'pending', 'to_status' => 'confirmed',
        'changed_by' => $shop->owner_id, 'changed_at' => now(),
    ]);

    $this->actingAs($admin)->get(route('admin.monitoring.index', [
        'module' => 'appointments', 'search' => (string) $appointment->id,
    ]))->assertOk()->assertSee('View record #'.$appointment->id.' details and timeline')
        ->assertSee('MON-1234')->assertSee('Status history')
        ->assertSee('This view is read-only.');
});

it('rejects unsupported monitoring modules and statuses', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->get(route('admin.monitoring.index', ['module' => 'users']))->assertSessionHasErrors('module');
    $this->get(route('admin.monitoring.index', ['module' => 'payments', 'status' => 'released']))->assertSessionHasErrors('status');
});

it('shows the last observed AI state to administrators without exposing its key', function () {
    config(['ai.api_key' => 'private-test-key', 'ai.provider' => 'gemini', 'ai.model' => 'gemini-2.0-flash']);
    app(AiServiceStatus::class)->record('rate_limited', 429);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.monitoring.index'))
        ->assertOk()->assertSee('AI assistant service')->assertSee('Rate limited')
        ->assertSee('HTTP 429')->assertDontSee('private-test-key');
});
