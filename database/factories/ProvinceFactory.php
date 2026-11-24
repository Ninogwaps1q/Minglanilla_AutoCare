<?php

namespace Database\Factories;

use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Province>
 */
class ProvinceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'name' => fake()->unique()->city().' Province',
            'code' => fake()->unique()->lexify('???'),
        ];
    }
}
