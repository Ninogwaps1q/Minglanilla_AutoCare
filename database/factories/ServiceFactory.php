<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => ServiceCategory::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'default_price' => fake()->randomFloat(2, 100, 5000),
            'estimated_duration' => fake()->numberBetween(15, 180),
        ];
    }
}
