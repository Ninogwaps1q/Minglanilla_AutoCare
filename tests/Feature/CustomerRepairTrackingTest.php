<?php

use App\Models\Barangay;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires a customer account to access repair tracking', function () {
    $this->get('/customer/repairs')->assertRedirect('/login');
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $this->actingAs($owner)->get('/customer/repairs')->assertForbidden();
});

it('shows an empty state when the customer has no repairs', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)->get('/customer/repairs')->assertOk()->assertSee('No repair jobs yet');
});

it('shows the customers repair costs and status while hiding other customers repairs', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $shop = Shop::query()->create([
        'owner_id' => User::factory()->create(['role' => 'shop_owner'])->id,
        'barangay_id' => Barangay::factory()->create()->id,
        'name' => 'Demo Repair Shop', 'phone' => '09170000000',
        'email' => 'repair@example.test', 'address' => 'Demo Street',
    ]);
    foreach ([$customer, $otherCustomer] as $repairCustomer) {
        $vehicle = Vehicle::factory()->create(['customer_id' => $repairCustomer->id]);
        RepairJob::query()->create([
            'customer_id' => $repairCustomer->id, 'vehicle_id' => $vehicle->id, 'shop_id' => $shop->id,
            'job_number' => $repairCustomer->is($customer) ? 'RJ-VISIBLE' : 'RJ-PRIVATE',
            'description' => '<script>alert(1)</script>', 'status' => 'completed',
            'actual_cost' => 1250, 'completed_at' => '2026-10-07 09:00:00',
        ]);
    }

    $this->actingAs($customer)->get('/customer/repairs')->assertOk()
        ->assertSee('RJ-VISIBLE')->assertDontSee('RJ-PRIVATE')
        ->assertSee('PHP 1,250.00')->assertSee('Oct 7, 2026')
        ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
});
