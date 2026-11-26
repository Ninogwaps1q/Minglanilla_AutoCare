<?php

use App\Models\Quotation;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('downloads a private estimate for the customer, issuing shop, and admin', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $owner = User::factory()->create(['role' => 'shop_owner']);
    $shop = Shop::factory()->create(['owner_id' => $owner->id]);
    $admin = User::factory()->create(['role' => 'admin']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $estimate = Quotation::query()->create([
        'shop_id' => $shop->id, 'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id,
        'quotation_number' => 'QT-DOWNLOAD', 'status' => 'sent',
        'valid_until' => today()->subDay(), 'subtotal' => 500, 'total' => 500,
    ]);
    $estimate->items()->create(['description' => 'Brake labor', 'type' => 'labor', 'quantity' => 1, 'unit_price' => 500, 'subtotal' => 500]);

    foreach (['customer' => $customer, 'shop' => $owner, 'admin' => $admin] as $portal => $user) {
        $response = $this->actingAs($user)->get(route($portal.'.quotations.pdf', $estimate))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($response->getContent())->toStartWith('%PDF-')
            ->and($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
    }

    expect($estimate->documentStatus())->toBe('expired');
    $this->actingAs(User::factory()->create(['role' => 'customer']))
        ->get(route('customer.quotations.pdf', $estimate))->assertNotFound();
});
