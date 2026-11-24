<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Artisan::call('catalog:import-psgc', ['--no-interaction' => true]) !== 0) {
            throw new RuntimeException(trim(Artisan::output()));
        }
    }
}
