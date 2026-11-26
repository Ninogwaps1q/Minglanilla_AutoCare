<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopPromotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(Request $request): View
    {
        $shop = $request->user()->managedShopOrFail();

        return view('shop.promotions', [
            'promotions' => ShopPromotion::query()->where('shop_id', $shop->id)->latest()->paginate(15),
            'services' => $shop->services()->with('service')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $shop = $request->user()->managedShopOrFail();
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:32', 'regex:/^[a-zA-Z0-9_-]+$/D'],
            'kind' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:0.01', 'max:999999', 'decimal:0,2'],
            'minimum' => ['nullable', 'numeric', 'min:0', 'max:999999', 'decimal:0,2'],
            'service_ids' => ['nullable', 'array', 'max:100'],
            'service_ids.*' => ['integer', 'min:1', 'distinct'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'usage_limit' => ['nullable', 'integer', 'between:1,1000000'],
            'customer_limit' => ['nullable', 'integer', 'between:1,1000000'],
        ]);

        DB::transaction(function () use ($shop, $attributes): void {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            abort_unless($shop->hasPlanAtLeast('premium'), 403);
            $code = isset($attributes['code']) ? Str::upper($attributes['code']) : null;
            if ($code !== null && ShopPromotion::query()->where('shop_id', $shop->id)->where('code', $code)->exists()) {
                throw ValidationException::withMessages(['code' => __('extensions.promo.duplicate')]);
            }
            if ($attributes['kind'] === 'percent' && (float) $attributes['value'] > 100) {
                throw ValidationException::withMessages(['value' => __('extensions.promo.percent_limit')]);
            }
            $services = array_map('intval', $attributes['service_ids'] ?? []);
            if ($shop->services()->whereIn('service_id', $services)->distinct()->count('service_id') !== count($services)) {
                throw ValidationException::withMessages(['service_ids' => __('extensions.promo.services_invalid')]);
            }

            ShopPromotion::query()->create(array_replace($attributes, ['code' => $code, 'shop_id' => $shop->id,
                'minimum' => $attributes['minimum'] ?? 0, 'service_ids' => $services]));
        });

        return back()->with('status', __('extensions.promo.created'));
    }

    public function disable(Request $request, ShopPromotion $promotion): RedirectResponse
    {
        if (! $request->user()->isAdmin()) {
            abort_unless($request->user()->managedShop()?->id === $promotion->shop_id, 404);
        }
        ShopPromotion::query()->whereKey($promotion->id)->whereNull('disabled_at')->update(['disabled_at' => now()]);

        return back()->with('status', __('extensions.promo.disabled'));
    }

    public function admin(): View
    {
        return view('admin.promotions', ['promotions' => ShopPromotion::query()->with('shop:id,name')->latest()->paginate(20)]);
    }
}
