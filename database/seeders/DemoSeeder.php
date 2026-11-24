<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('The demo seeder is for local and test databases only.');
        }

        $barangayId = Barangay::query()->whereNotNull('psgc_code')->inRandomOrder()->value('id');
        foreach ([
            ['System Administrator', 'admin', 'Admin123!'],
            ['Demo Customer', 'customer', 'Customer123!'],
            ['Demo Shop Owner', 'shop_owner', 'Shop123!'],
        ] as [$name, $role, $password]) {
            $emailPrefix = $role === 'shop_owner' ? 'shop' : $role;
            $user = User::query()->firstOrNew(['email' => $emailPrefix.'@minglanillaautocare.test']);
            if (! $user->exists) {
                $user->forceFill([
                    'name' => $name,
                    'role' => $role,
                    'password' => $password,
                    'email_verified_at' => now(),
                    'barangay_id' => $role === 'customer' ? $barangayId : null,
                ])->save();
            }
        }
    }
}
