<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\ShopPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopPricingController extends Controller
{
    public function index(Request $request): View
    {
        $plans = ShopPlan::query()->orderBy('sort_order')->get();
        $shop = $request->user()?->role === 'shop_owner' ? $request->user()->shops()->first() : null;

        return view('shop.pricing', [
            'plans' => $plans,
            'shop' => $shop,
            'requestedPlan' => $plans->firstWhere('slug', $shop?->requested_plan),
            'payments' => $shop?->planPayments()->latest()->limit(10)->get() ?? collect(),
            'pendingPayment' => $shop?->planPayments()->where('status', 'pending')->first(),
        ]);
    }

    public function startTrial(Request $request): RedirectResponse
    {
        $shop = $this->ownedShop($request);
        if ($shop->verification_status !== 'approved' || $shop->shop_status !== 'active') {
            return back()->withErrors(['trial' => 'Your shop must be approved and active before the trial can start.']);
        }
        if ($shop->trial_started_at || $shop->activePlanSlug() === 'premium') {
            return back()->withErrors(['trial' => 'The Premium trial has already been used or Premium is active.']);
        }

        $shop->update(['trial_started_at' => now()]);

        return back()->with('status', 'Your 30-day Premium trial has started.');
    }

    public function requestPlan(Request $request, ShopPlan $shopPlan): RedirectResponse
    {
        abort_unless(in_array($shopPlan->slug, ['basic', 'premium'], true), 404);
        $shop = $this->ownedShop($request);
        if ($shop->verification_status !== 'approved' || $shop->shop_status !== 'active') {
            return back()->withErrors(['plan' => 'Your shop must be approved and active before requesting a paid plan.']);
        }
        if ($shop->planPayments()->where('status', 'pending')->exists()) {
            return back()->withErrors(['plan' => 'Your payment submission is waiting for review. Please wait before changing plans.']);
        }
        $shop->update(['requested_plan' => $shopPlan->slug]);

        return back()->with('status', "Your {$shopPlan->name} request was sent to the administrator.");
    }

    private function ownedShop(Request $request): Shop
    {
        return $request->user()->shops()->firstOrFail();
    }
}
