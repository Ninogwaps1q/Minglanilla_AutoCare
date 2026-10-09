<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $catalog = [
            'Maintenance' => [
                'Oil Change',
                'Oil Filter Replacement',
                'Air Filter Replacement',
                'Fuel Filter Replacement',
                'Tune-Up',
                'Periodic Maintenance Service',
            ],
            'Tires and Wheels' => [
                'Tire Repair',
                'Tire Replacement',
                'Wheel Alignment',
                'Wheel Balancing',
                'Tire Rotation',
            ],
            'Brakes and Suspension' => [
                'Brake Inspection',
                'Brake Pad Replacement',
                'Brake Fluid Replacement',
                'Suspension Inspection',
                'Shock Absorber Replacement',
            ],
            'Engine and Diagnostics' => [
                'Computer Diagnostics',
                'Engine Repair',
                'Cooling System Service',
                'Radiator Repair',
                'Transmission Service',
            ],
            'Electrical and Air Conditioning' => [
                'Battery Replacement',
                'Alternator Repair',
                'Starter Motor Repair',
                'Air Conditioning Service',
                'Electrical System Repair',
            ],
            'Body and Detailing' => [
                'Car Wash',
                'Interior Detailing',
                'Exterior Detailing',
                'Paint Touch-Up',
                'Body Repair',
            ],
        ];

        foreach ($catalog as $categoryName => $serviceNames) {
            $category = ServiceCategory::query()->firstOrCreate(['name' => $categoryName]);

            foreach ($serviceNames as $serviceName) {
                Service::query()->firstOrCreate([
                    'category_id' => $category->id,
                    'name' => $serviceName,
                ]);
            }
        }
    }
}
