<?php

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the owner passport and a genuine private SVG QR code', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $this->actingAs($customer)->get(route('customer.vehicles.passport', $vehicle))
        ->assertOk()->assertSee($vehicle->plate_number)->assertSee('Download QR');
    $response = $this->get(route('customer.vehicles.passport.qr', $vehicle));
    $response->assertOk()->assertHeader('Content-Type', 'image/svg+xml')
        ->assertSee('<svg', false)->assertSee('<path', false);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('denies access to another customer passport and QR image', function (string $route) {
    $vehicle = Vehicle::factory()->create();
    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)->get(route($route, $vehicle))->assertForbidden();
})->with(['customer.vehicles.passport', 'customer.vehicles.passport.qr']);

it('requires login before serving the passport or QR image', function (string $route) {
    $vehicle = Vehicle::factory()->create();
    $this->get(route($route, $vehicle))->assertRedirect(route('login'));
})->with(['customer.vehicles.passport', 'customer.vehicles.passport.qr']);
