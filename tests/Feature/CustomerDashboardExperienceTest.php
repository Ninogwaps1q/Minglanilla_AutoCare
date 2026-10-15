<?php

use App\Models\Appointment;
use App\Models\Barangay;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('guides a new customer toward their first useful actions', function () {
    $customer = User::factory()->create(['role' => 'customer', 'name' => 'Jamie Driver']);

    $this->actingAs($customer)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Jamie')
        ->assertSee('Add your vehicle first')
        ->assertSee('Find nearby shops')
        ->assertSee('No upcoming service visit')
        ->assertSee('Add my vehicle');
});

it('shows only the signed-in customers next service and billing attention', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::query()->create([
        'owner_id' => $owner->id,
        'barangay_id' => Barangay::factory()->create()->id,
        'name' => 'Friendly AutoCare',
        'phone' => '09170000000',
        'email' => 'friendly@example.test',
        'address' => 'Minglanilla',
    ]);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id, 'plate_number' => 'EASY-101']);
    $otherVehicle = Vehicle::factory()->create(['customer_id' => $otherCustomer->id]);
    $service = Service::factory()->create(['name' => 'Easy Oil Change']);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'vehicle_id' => $vehicle->id,
        'service_id' => $service->id,
        'appointment_date' => today()->addDay(),
        'appointment_time' => '09:00',
        'status' => 'confirmed',
    ]);
    Appointment::query()->create([
        'customer_id' => $otherCustomer->id,
        'shop_id' => $shop->id,
        'vehicle_id' => $otherVehicle->id,
        'service_id' => $service->id,
        'appointment_date' => today()->addDay(),
        'appointment_time' => '10:00',
        'reason' => 'Private other customer note',
        'status' => 'confirmed',
    ]);
    Invoice::query()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'invoice_number' => 'INV-EASY-001',
        'subtotal' => 500,
        'total' => 500,
        'status' => 'unpaid',
    ]);

    $this->actingAs($customer)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Easy Oil Change')
        ->assertSee('Friendly AutoCare')
        ->assertSee('EASY-101')
        ->assertSee('Unpaid invoices')
        ->assertSee('Review payments')
        ->assertDontSee('Private other customer note');
});
