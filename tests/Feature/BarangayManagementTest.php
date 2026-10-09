<?php

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('forbids a customer from managing barangays', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)->get('/admin/barangays')->assertForbidden();
});

it('creates a barangay when submitted by an admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $municipality = Municipality::factory()->create();

    $response = $this->actingAs($admin)->post('/admin/barangays', ['municipality_id' => $municipality->id, 'name' => 'New Barangay', 'status' => 'active']);

    $response->assertSessionHas('status', 'Barangay created.');
    $this->assertDatabaseHas('barangays', ['municipality_id' => $municipality->id, 'name' => 'New Barangay', 'status' => 'active']);
});

it('renders editable barangays and saves their status changes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $barangay = Barangay::factory()->create(['name' => 'Original Area', 'status' => 'active']);

    $this->actingAs($admin)->get(route('barangays.index'))->assertOk()->assertSee(route('barangays.update', $barangay));
    $this->patch(route('barangays.update', $barangay), ['name' => 'Renamed Area', 'status' => 'inactive'])->assertRedirect();

    $this->assertDatabaseHas('barangays', ['id' => $barangay->id, 'name' => 'Renamed Area', 'status' => 'inactive']);
});
