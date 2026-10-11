<?php

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->state(['role' => 'shop_owner']),
            'barangay_id' => Barangay::factory(),
            'name' => fake()->company().' AutoCare',
            'phone' => '09'.fake()->numerify('#########'),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->streetAddress(),
            'verification_status' => 'pending',
            'shop_status' => 'inactive',
        ];
    }
}
