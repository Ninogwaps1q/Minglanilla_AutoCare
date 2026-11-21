<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\ShopPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopPricingController extends Controller
{
    public function index(Request $request): View
    {
        $plans = ShopPlan::query()->orderBy('sort_order')->get();
        $shop = $request->user()?->isProviderOwner() ? $request->user()->shops()->first() : null;
        $defaultPlan = $shop && $shop->activePlanSlug() !== 'free' ? $shop->activePlanSlug() : 'basic';
        $selectedSlug = $request->old('plan_slug', $request->query('plan', $shop?->requested_plan ?? $defaultPlan));
        $selectedPlan = $plans->whereIn('slug', ['basic', 'premium'])->firstWhere('slug', $selectedSlug)
            ?? $plans->firstWhere('slug', 'basic');

        return view('shop.pricing', [
            'plans' => $plans,
            'shop' => $shop,
            'selectedPlan' => $selectedPlan,
            'payments' => $shop?->planPayments()->latest()->paginate(10)->withQueryString() ?? collect(),
            'scheduledPayments' => $shop?->planPayments()->validPeriods()->where('period_starts_at', '>', now())->oldest('period_starts_at')->get() ?? collect(),
            'pendingPayment' => $shop?->planPayments()->whereIn('status', ['pending', 'checkout_pending'])->first(),
        ]);
    }

    public function qr(ShopPlan $shopPlan): StreamedResponse
    {
        abort_unless($shopPlan->hasPaymentQr(), 404);

        return Storage::disk('local')->response($shopPlan->payment_qr_path, headers: [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function startTrial(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $shop = $request->user()->shops()->lockForUpdate()->firstOrFail();
            if ($shop->verification_status !== 'approved' || $shop->shop_status !== 'active') {
                throw ValidationException::withMessages(['trial' => 'Your shop must be approved and active before the trial can start.']);
            }
            if ($shop->trial_started_at || $shop->activePlanSlug() !== 'free' || $shop->planPayments()->validPeriods()->where('period_ends_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['trial' => 'The trial can start only once, before paid coverage. Your paid days remain unchanged.']);
            }
            $shop->update(['trial_started_at' => now()]);
        });

        return back()->with('status', 'Your 30-day Premium trial has started.');
    }

    public function requestPlan(Request $request, ShopPlan $shopPlan): RedirectResponse
    {
        abort_unless(in_array($shopPlan->slug, ['basic', 'premium'], true), 404);
        $shop = $this->ownedShop($request);
        if ($shop->verification_status !== 'approved' || $shop->shop_status !== 'active') {
            return back()->withErrors(['plan' => 'Your shop must be approved and active before requesting a paid plan.']);
        }
        if ($shop->planPayments()->whereIn('status', ['pending', 'checkout_pending'])->exists()) {
            return back()->withErrors(['plan' => 'Your payment submission is waiting for review. Please wait before changing plans.']);
        }

        return redirect()->to(route('shop.pricing', ['plan' => $shopPlan->slug]).'#plan-payment');
    }

    private function ownedShop(Request $request): Shop
    {
        return $request->user()->shops()->firstOrFail();
    }
}
