<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Shop;
use App\Models\ShopPlan;
use App\Models\ShopPlanPayment;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopPlanController extends Controller
{
    public function index(): View
    {
        return view('admin.shop-plans.index', [
            'plans' => ShopPlan::query()->orderBy('sort_order')->get(),
            'requests' => Shop::query()->with(['owner:id,name', 'planPayments' => fn ($query) => $query->where('status', 'pending')])->whereNotNull('requested_plan')->orderBy('name')->paginate(10, ['*'], 'requests_page'),
            'payments' => ShopPlanPayment::query()->with(['shop.owner:id,name'])->where('status', 'pending')->oldest()->paginate(10, ['*'], 'payments_page'),
            'reviewedPayments' => ShopPlanPayment::query()->with('shop:id,name')->whereIn('status', ['approved', 'rejected'])->latest('reviewed_at')->paginate(15, ['*'], 'history_page'),
            'subscribers' => Shop::query()->with('owner:id,name')
                ->where(function ($query): void {
                    $query->where('plan_slug', '!=', 'free')->orWhereNotNull('trial_started_at')->orWhereNotNull('requested_plan');
                })->orderBy('name')->paginate(15, ['*'], 'subscribers_page'),
        ]);
    }

    public function update(Request $request, ShopPlan $shopPlan): RedirectResponse
    {
        $attributes = $request->validate([
            'monthly_price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'summary' => ['required', 'string', 'max:255'],
            'features' => ['required', 'array', 'size:3'],
            'features.*' => ['required', 'string', 'max:120'],
            'payment_instructions' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($shopPlan->slug === 'free') {
            $attributes['monthly_price'] = 0;
        }
        $shopPlan->update($attributes);
        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'updated_shop_plan_pricing',
            'module' => 'shop_plans',
            'description' => "{$shopPlan->name} monthly price set to PHP {$shopPlan->monthly_price}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', "{$shopPlan->name} pricing updated.");
    }

    public function activate(Request $request, Shop $shop): RedirectResponse
    {
        $attributes = $request->validate([
            'plan_slug' => ['required', 'in:free,basic,premium'],
            'plan_expires_at' => ['exclude_if:plan_slug,free', 'required', 'date', 'after:today'],
        ]);
        DB::transaction(function () use ($shop, $request, $attributes): void {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            if ($shop->planPayments()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['plan' => 'Review the pending payment before changing this shop’s plan.']);
            }

            $shop->update([
                'plan_slug' => $attributes['plan_slug'],
                'plan_expires_at' => $attributes['plan_slug'] === 'free' ? null : Carbon::parse($attributes['plan_expires_at'])->endOfDay(),
                'trial_revoked_at' => $shop->trialIsActive() ? now() : $shop->trial_revoked_at,
                'requested_plan' => null,
            ]);
            ActivityLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'changed_shop_plan',
                'module' => 'shop_plans',
                'description' => "Shop #{$shop->id} access set to {$attributes['plan_slug']} by admin.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            $shop->owner->notifications()->create([
                'type' => 'shop_plan_payment',
                'title' => 'Shop plan access updated',
                'message' => 'Your shop access is now '.ucfirst($attributes['plan_slug']).'.',
                'data' => ['shop_id' => $shop->id],
            ]);
        });

        return back()->with('status', "{$shop->name} plan updated.");
    }
}
