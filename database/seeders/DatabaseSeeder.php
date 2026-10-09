<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
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
        $cebu = Province::query()->firstOrCreate(['code' => 'CEB'], ['name' => 'Cebu']);
        $minglanilla = Municipality::query()->firstOrCreate(['province_id' => $cebu->id, 'code' => 'MING'], ['name' => 'Minglanilla']);
        foreach (['Cadulawan', 'Calajo-an', 'Camp 7', 'Camp 8', 'Cuanos', 'Guindaruhan', 'Linao', 'Manduang', 'Pakigne', 'Poblacion Ward I', 'Poblacion Ward II', 'Poblacion Ward III', 'Poblacion Ward IV', 'Tubod', 'Tulay', 'Tunghaan', 'Tungkil', 'Tungkop', 'Vito'] as $name) {
            Barangay::query()->firstOrCreate(['municipality_id' => $minglanilla->id, 'name' => $name], ['status' => 'active']);
        }
        $this->call(ServiceCatalogSeeder::class);

        User::factory()->create(['name' => 'System Administrator', 'email' => 'admin@minglanillaautocare.test', 'password' => 'Admin123!', 'role' => 'admin']);
        User::factory()->create(['name' => 'Demo Customer', 'email' => 'customer@minglanillaautocare.test', 'password' => 'Customer123!', 'role' => 'customer']);
        User::factory()->create(['name' => 'Demo Shop Owner', 'email' => 'shop@minglanillaautocare.test', 'password' => 'Shop123!', 'role' => 'shop_owner']);
    }
}
