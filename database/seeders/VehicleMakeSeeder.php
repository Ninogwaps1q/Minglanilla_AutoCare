<?php

namespace Database\Seeders;

use App\Models\VehicleMake;
use Illuminate\Database\Seeder;

class VehicleMakeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $makes = [
            'car' => ['BYD', 'Chery', 'Chevrolet', 'Ford', 'GAC', 'Geely', 'Honda', 'Hyundai', 'Isuzu', 'Kia', 'Mazda', 'MG', 'Mitsubishi', 'Nissan', 'Subaru', 'Suzuki', 'Toyota'],
            'motorcycle' => ['Honda', 'Kawasaki', 'Kymco', 'Rusi', 'Suzuki', 'Yamaha'],
        ];

        foreach ($makes as $type => $names) {
            foreach ($names as $name) {
                VehicleMake::query()->firstOrCreate(['name' => $name, 'vehicle_type' => $type], ['source' => 'philippines']);
            }
        }
    }
}
