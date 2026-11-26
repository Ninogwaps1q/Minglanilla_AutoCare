<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopPromotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShopPromotion> */
class ShopPromotionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->words(2, true),
            'code' => strtoupper(fake()->unique()->bothify('SAVE-###??')),
            'kind' => 'percent', 'value' => 10,
            'starts_on' => today()->toDateString(), 'ends_on' => today()->addWeek()->toDateString(),
        ];
    }
}
