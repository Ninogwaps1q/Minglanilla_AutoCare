<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('stores an authenticated customers validated vehicle photo', function () {
    Storage::fake('public');
    $customer = User::factory()->create(['role' => 'customer']);
    $photo = validVehicleImage();

    $this->actingAs($customer)->post(route('customer.vehicles.store'), vehicleUploadPayload(['photo' => $photo]))
        ->assertRedirect(route('customer.vehicles.index'));

    $path = $customer->vehicles()->sole()->photo;
    expect($path)->toStartWith('vehicles/');
    Storage::disk('public')->assertExists($path);
});

it('rejects executable files and oversized vehicle photos', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)->post(route('customer.vehicles.store'), vehicleUploadPayload([
        'photo' => UploadedFile::fake()->create('vehicle.exe', 100, 'application/octet-stream'),
    ]))->assertSessionHasErrors('photo');

    $this->post(route('customer.vehicles.store'), vehicleUploadPayload([
        'plate_number' => 'UPLOAD-002',
        'photo' => UploadedFile::fake()->createWithContent('large.png', validPng().str_repeat('0', 2_100_000)),
    ]))->assertSessionHasErrors('photo');
});

/** @return array<string, mixed> */
function vehicleUploadPayload(array $overrides = []): array
{
    return array_merge([
        'plate_number' => 'UPLOAD-001',
        'make' => 'Toyota',
        'model' => 'Vios',
        'year' => 2022,
        'current_mileage' => 12000,
    ], $overrides);
}

function validVehicleImage(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('vehicle.png', validPng());
}

function validPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JQqkAAAAASUVORK5CYII=');
}
