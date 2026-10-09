<?php

use App\Models\Barangay;
use App\Models\Invoice;
use App\Models\Shop;
use App\Models\User;
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

it('rejects unsupported monitoring modules and statuses', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->get(route('admin.monitoring.index', ['module' => 'users']))->assertSessionHasErrors('module');
    $this->get(route('admin.monitoring.index', ['module' => 'payments', 'status' => 'released']))->assertSessionHasErrors('status');
});
