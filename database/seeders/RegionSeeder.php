<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $region = Region::query()->firstOrCreate(['psgc_code' => '070000000'], ['name' => 'Central Visayas']);
        Province::query()->where('name', 'Cebu')->update(['region_id' => $region->id, 'psgc_code' => '072200000']);
    }
}
