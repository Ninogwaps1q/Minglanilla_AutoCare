<?php

use App\Models\Service;
use App\Models\ServiceCategory;
use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the standard automotive service catalog without duplicates', function () {
    $seeder = new ServiceCatalogSeeder;

    $seeder->run();
    $seeder->run();

    expect(ServiceCategory::query()->count())->toBe(6);
    expect(Service::query()->count())->toBe(31);
    $this->assertDatabaseHas('services', ['name' => 'Oil Change']);
    $this->assertDatabaseHas('services', ['name' => 'Wheel Alignment']);
    $this->assertDatabaseHas('services', ['name' => 'Air Conditioning Service']);
});
