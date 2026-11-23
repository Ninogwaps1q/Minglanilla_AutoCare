<?php

namespace Database\Seeders;

use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;

class VehicleModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $models = [
            'car' => [
                'Toyota' => ['Vios' => ['J', 'E', 'G', 'GR-S'], 'Wigo' => ['J', 'E', 'G'], 'Innova' => ['J', 'E', 'G', 'V'], 'Fortuner' => ['G', 'V', 'Q', 'GR-S'], 'Hilux' => ['J', 'E', 'G', 'Conquest']],
                'Honda' => ['City' => ['S', 'V', 'RS'], 'Civic' => ['V', 'RS'], 'BR-V' => ['S', 'V', 'VX'], 'CR-V' => ['V', 'VX', 'RS']],
                'Mitsubishi' => ['Mirage' => ['GLX', 'GLS'], 'Mirage G4' => ['GLX', 'GLS'], 'Xpander' => ['GLX', 'GLS', 'Cross'], 'Montero Sport' => ['GLX', 'GLS', 'GT'], 'L300' => ['Base']],
                'Suzuki' => ['Dzire' => ['GL', 'GLX'], 'Ertiga' => ['GA', 'GL', 'GLX'], 'Jimny' => ['GL', 'GLX']],
                'Nissan' => ['Almera' => ['EL', 'VE', 'VL'], 'Navara' => ['EL', 'VE', 'VL', 'Pro-4X'], 'Terra' => ['EL', 'VE', 'VL']],
                'Ford' => ['Ranger' => ['XL', 'XLS', 'XLT', 'Wildtrak', 'Raptor'], 'Everest' => ['Trend', 'Sport', 'Titanium']],
                'Isuzu' => ['D-Max' => ['LS-A', 'LS-E'], 'mu-X' => ['LS-A', 'LS-E']],
                'Hyundai' => ['Accent' => ['GL', 'GLS'], 'Stargazer' => ['GL', 'GLS', 'Premium']],
                'Kia' => ['Soluto' => ['LX', 'EX'], 'Sonet' => ['LX', 'EX', 'SX']],
                'MG' => ['ZS' => ['Style', 'Alpha'], '5' => ['Core', 'Style']],
                'BYD' => ['Atto 3' => ['Dynamic', 'Premium'], 'Dolphin' => ['Dynamic', 'Premium']],
            ],
            'motorcycle' => [
                'Honda' => ['Click 125' => ['Standard'], 'Beat' => ['Standard'], 'PCX 160' => ['CBS', 'ABS'], 'TMX 125' => ['Standard']],
                'Yamaha' => ['Mio i 125' => ['Standard'], 'NMAX' => ['Standard', 'ABS'], 'Aerox' => ['Standard', 'ABS']],
                'Suzuki' => ['Raider R150' => ['Carb', 'FI'], 'Burgman Street' => ['Standard', 'EX']],
                'Kawasaki' => ['Barako II' => ['Standard'], 'Rouser NS160' => ['Standard']],
                'Kymco' => ['Like 150i' => ['Standard'], 'Xciting S 400i' => ['Standard']],
                'Rusi' => ['Classic 250' => ['Standard']],
            ],
        ];

        foreach ($models as $type => $makes) {
            foreach ($makes as $makeName => $entries) {
                $make = VehicleMake::query()->where('name', $makeName)->where('vehicle_type', $type)->firstOrFail();
                foreach ($entries as $name => $variants) {
                    VehicleModel::query()->firstOrCreate(
                        ['vehicle_make_id' => $make->id, 'name' => $name],
                        ['variants' => $variants, 'source' => 'philippines'],
                    );
                }
            }
        }
    }
}
