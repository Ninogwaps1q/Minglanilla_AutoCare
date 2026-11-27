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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopPlanController extends Controller
{
    public function index(): View
    {
        return view('admin.shop-plans.index', [
            'plans' => ShopPlan::query()->orderBy('sort_order')->get(),
            'payments' => ShopPlanPayment::query()->with(['shop.owner:id,name'])->where('status', 'pending')->oldest()->paginate(10, ['*'], 'payments_page'),
            'reviewedPayments' => ShopPlanPayment::query()->with('shop:id,name')->whereIn('status', ['approved', 'rejected'])->latest('reviewed_at')->paginate(15, ['*'], 'history_page'),
            'subscribers' => Shop::query()->with('owner:id,name')->withCount(['planPayments as paid_coverage_count' => fn ($query) => $query->validPeriods()->where('period_ends_at', '>', now())])
                ->where(function ($query): void {
                    $query->where('plan_slug', '!=', 'free')->orWhereNotNull('trial_started_at')->orWhereNotNull('requested_plan')->orWhereNotNull('next_plan_starts_at');
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
            'payment_account_name' => ['nullable', 'required_with:payment_account_number', 'string', 'max:255'],
            'payment_account_number' => ['nullable', 'required_with:payment_account_name', 'string', 'max:100'],
            'payment_qr' => ['nullable', 'prohibited_if:remove_payment_qr,1', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
            'remove_payment_qr' => ['sometimes', 'boolean'],
            'mechanic_entitlements' => ['sometimes', 'array:monthly_limit,travel_pricing,live_tracking,portfolio_limit,reminders'],
            'mechanic_entitlements.monthly_limit' => ['present_with:mechanic_entitlements', 'nullable', 'integer', 'between:0,1000'],
            'mechanic_entitlements.travel_pricing' => ['required_with:mechanic_entitlements', 'boolean'],
            'mechanic_entitlements.live_tracking' => ['sometimes', 'boolean'],
            'mechanic_entitlements.portfolio_limit' => ['sometimes', 'integer', 'between:0,100'],
            'mechanic_entitlements.reminders' => ['sometimes', 'boolean'],
        ]);
        unset($attributes['payment_qr'], $attributes['remove_payment_qr']);
        if (isset($attributes['mechanic_entitlements'])) {
            $attributes['mechanic_entitlements']['travel_pricing'] = (bool) $attributes['mechanic_entitlements']['travel_pricing'];
            foreach (['travel_pricing', 'live_tracking', 'reminders'] as $feature) {
                if (array_key_exists($feature, $attributes['mechanic_entitlements'])) {
                    $attributes['mechanic_entitlements'][$feature] = (bool) $attributes['mechanic_entitlements'][$feature];
                }
            }
            $limit = $attributes['mechanic_entitlements']['monthly_limit'] ?? null;
            $attributes['mechanic_entitlements']['monthly_limit'] = $limit === null ? null : (int) $limit;
        }
        if ($shopPlan->slug === 'free') {
            $attributes['monthly_price'] = 0;
            unset($attributes['payment_instructions'], $attributes['payment_account_name'], $attributes['payment_account_number']);
        } elseif ($attributes['monthly_price'] < 1) {
            throw ValidationException::withMessages(['monthly_price' => 'A paid plan must cost at least PHP 1 per month.']);
        }

        $qrPath = null;
        $oldQrPath = null;
        if ($shopPlan->slug !== 'free' && $request->hasFile('payment_qr')) {
            $qrPath = $request->file('payment_qr')->store('shop-plan-qrs', 'local');
            if (! $qrPath) {
                throw ValidationException::withMessages(['payment_qr' => 'The QR image could not be saved. Please upload it again.']);
            }
        }

        try {
            DB::transaction(function () use ($shopPlan, $attributes, $request, $qrPath, &$oldQrPath): void {
                $plan = ShopPlan::query()->lockForUpdate()->findOrFail($shopPlan->id);
                if ($plan->slug !== 'free' && ($qrPath || $request->boolean('remove_payment_qr'))) {
                    $oldQrPath = $plan->payment_qr_path;
                    $attributes['payment_qr_path'] = $qrPath;
                }
                $plan->update($attributes);
                ActivityLog::query()->create([
                    'user_id' => $request->user()->id,
                    'action' => 'updated_shop_plan_pricing',
                    'module' => 'shop_plans',
                    'description' => "{$plan->name} pricing and payment details updated; monthly price PHP {$plan->monthly_price}.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            });
        } catch (\Throwable $exception) {
            if ($qrPath) {
                Storage::disk('local')->delete($qrPath);
            }
            throw $exception;
        }
        if ($oldQrPath) {
            Storage::disk('local')->delete($oldQrPath);
        }

        return back()->with('status', "{$shopPlan->name} pricing and payment details updated.");
    }

    public function activate(Request $request, Shop $shop): RedirectResponse
    {
        $attributes = $request->validate([
            'plan_slug' => ['required', 'in:free,basic,premium'],
            'plan_expires_at' => ['exclude_if:plan_slug,free', 'required', 'date', 'after:today'],
        ]);
        DB::transaction(function () use ($shop, $request, $attributes): void {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            if ($shop->planPayments()->whereIn('status', ['pending', 'checkout_pending'])->exists()) {
                throw ValidationException::withMessages(['plan' => 'Review the pending payment before changing this shop’s plan.']);
            }

            if ($shop->planPayments()->validPeriods()->where('period_ends_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['plan' => 'This shop has paid coverage. Use the next billing period for a plan change, or complete a full refund before removing access.']);
            }

            $shop->update([
                'plan_slug' => $attributes['plan_slug'],
                'plan_expires_at' => $attributes['plan_slug'] === 'free' ? null : Carbon::parse($attributes['plan_expires_at'])->endOfDay(),
                'trial_revoked_at' => $shop->trialIsActive() ? now() : $shop->trial_revoked_at,
                'requested_plan' => null,
                'plan_payment_id' => null,
                'next_plan_starts_at' => null,
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
