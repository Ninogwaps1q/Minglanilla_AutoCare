<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\ShopPromotion;
use Illuminate\Database\Seeder;

class ShopPromotionSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $shop = Shop::query()->where('verification_status', 'approved')->get()->first(fn (Shop $candidate): bool => $candidate->hasPlanAtLeast('premium'));
        if ($shop) {
            ShopPromotion::query()->firstOrCreate(['shop_id' => $shop->id, 'code' => 'DEMO-SAVE10'], [
                'name' => 'Demo booking discount', 'kind' => 'percent', 'value' => 10,
                'starts_on' => today()->toDateString(), 'ends_on' => today()->addWeek()->toDateString(),
            ]);
        }
    }
}
