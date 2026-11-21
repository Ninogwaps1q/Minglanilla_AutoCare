<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\ShopSubscriptionBilling;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncShopSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-shop-subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Activate paid subscription periods when their scheduled start arrives.';

    /**
     * Execute the console command.
     */
    public function handle(ShopSubscriptionBilling $billing): int
    {
        Shop::query()->where('next_plan_starts_at', '<=', now())->orderBy('id')->chunkById(100, function ($shops) use ($billing): void {
            foreach ($shops as $shop) {
                DB::transaction(function () use ($shop, $billing): void {
                    $billing->sync(Shop::query()->lockForUpdate()->findOrFail($shop->id));
                });
            }
        });
        $this->info('Scheduled subscription periods synchronized.');

        return self::SUCCESS;
    }
}
