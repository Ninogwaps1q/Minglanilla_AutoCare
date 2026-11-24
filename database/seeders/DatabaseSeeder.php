<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ServiceCatalogSeeder::class);
        $this->call([VehicleMakeSeeder::class, VehicleModelSeeder::class]);

        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        User::factory()->create(['name' => 'System Administrator', 'email' => 'admin@minglanillaautocare.test', 'password' => 'Admin123!', 'role' => 'admin']);
        User::factory()->create(['name' => 'Demo Customer', 'email' => 'customer@minglanillaautocare.test', 'password' => 'Customer123!', 'role' => 'customer']);
        User::factory()->create(['name' => 'Demo Shop Owner', 'email' => 'shop@minglanillaautocare.test', 'password' => 'Shop123!', 'role' => 'shop_owner']);
    }
}
