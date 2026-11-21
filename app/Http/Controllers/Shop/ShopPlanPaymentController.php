<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Webhooks\PayMongoWebhookController;
use App\Models\Shop;
use App\Models\ShopPlan;
use App\Models\ShopPlanPayment;
use App\Services\PayMongoCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopPlanPaymentController extends Controller
{
    public function check(Request $request, ShopPlanPayment $shopPlanPayment, PayMongoCheckout $checkout, PayMongoWebhookController $webhook): JsonResponse
    {
        abort_unless($shopPlanPayment->shop->owner_id === $request->user()->id, 404);
        if ($shopPlanPayment->status === 'checkout_pending' && $shopPlanPayment->paymongo_session_id) {
            $session = $checkout->retrieve($shopPlanPayment->paymongo_session_id);
            if ($session) {
                $webhook->processSession($session);
            }
        }

        return response()->json(['status' => $shopPlanPayment->fresh()->status]);
    }

    public function cancel(Request $request, ShopPlanPayment $shopPlanPayment, PayMongoCheckout $checkout): RedirectResponse
    {
        abort_unless($shopPlanPayment->shop->owner_id === $request->user()->id && $shopPlanPayment->status === 'checkout_pending', 404);
        if (! $shopPlanPayment->paymongo_session_id || ! $checkout->expire($shopPlanPayment->paymongo_session_id)) {
            throw ValidationException::withMessages(['payment' => 'This checkout cannot be cancelled while payment may be processing. Please wait and refresh.']);
        }
        DB::transaction(function () use ($shopPlanPayment): void {
            $payment = ShopPlanPayment::query()->lockForUpdate()->findOrFail($shopPlanPayment->id);
            if ($payment->status === 'checkout_pending') {
                $payment->update(['status' => 'cancelled']);
                $payment->shop->update(['requested_plan' => null]);
            }
        });

        return to_route('shop.pricing')->with('status', 'Checkout cancelled. You can choose another plan.');
    }

    public function checkout(Request $request, PayMongoCheckout $checkout): RedirectResponse
    {
        $attributes = $request->validate(['plan_slug' => ['required', 'in:basic,premium']]);
        $shop = $request->user()->shops()->firstOrFail();
        if (! $checkout->configured()) {
            throw ValidationException::withMessages(['payment' => 'Online checkout is unavailable. Please contact the administrator.']);
        }

        $payment = DB::transaction(function () use ($shop, $attributes): ShopPlanPayment {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            $plan = ShopPlan::query()->where('slug', $attributes['plan_slug'])->firstOrFail();
            if ($shop->verification_status !== 'approved' || $shop->shop_status !== 'active') {
                throw ValidationException::withMessages(['plan' => 'Your shop must be approved and active before paying for a plan.']);
            }
            if ($plan->monthly_price < 1) {
                throw ValidationException::withMessages(['plan' => 'This plan has no online checkout price. Please contact the administrator.']);
            }
            $pending = $shop->planPayments()->whereIn('status', ['pending', 'checkout_pending'])->first();
            if ($pending) {
                if ($pending->status === 'checkout_pending' && $pending->plan_slug === $plan->slug) {
                    return $pending;
                }
                throw ValidationException::withMessages(['payment' => 'Another plan payment is in progress. Please finish it before changing plans.']);
            }

            $shop->update(['requested_plan' => $plan->slug]);

            return $shop->planPayments()->create(['plan_slug' => $plan->slug, 'amount' => $plan->monthly_price, 'status' => 'checkout_pending']);
        });

        if ($payment->paymongo_checkout_url) {
            return redirect()->away($payment->paymongo_checkout_url);
        }

        try {
            $session = $checkout->create('shop-plan-'.$payment->id, ucfirst($payment->plan_slug).' provider plan', $payment->amount * 100, $request->user()->email, route('shop.pricing'), route('shop.pricing'));
        } catch (\Throwable $exception) {
            report($exception);
            if ($exception instanceof RuntimeException && $exception->getCode() >= 400 && $exception->getCode() < 500 && $exception->getCode() !== 429) {
                $payment->update(['status' => 'failed']);
                $shop->update(['requested_plan' => null]);
            }
            throw ValidationException::withMessages(['payment' => 'PayMongo checkout could not start. Please try again.']);
        }
        $payment->update(['paymongo_session_id' => $session['id'], 'paymongo_checkout_url' => $session['url']]);

        return redirect()->away($session['url']);
    }

    public function store(Request $request): RedirectResponse
    {
        $shop = $request->user()->shops()->firstOrFail();
        if (! config('services.paymongo.legacy_manual_payments')) {
            throw ValidationException::withMessages(['payment' => 'New plan payments must use PayMongo checkout. Choose a plan on the pricing page.']);
        }
        $attributes = $request->validate([
            'plan_slug' => ['required', 'in:basic,premium'],
            'reference' => ['nullable', 'required_without:proof', 'string', 'max:100'],
            'proof' => ['nullable', 'required_without:reference', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        $proofPath = $request->hasFile('proof') ? $request->file('proof')->store("shop-plan-payments/{$shop->id}") : null;
        if ($proofPath === false) {
            throw ValidationException::withMessages(['proof' => 'The receipt could not be saved. Please upload it again.']);
        }

        try {
            DB::transaction(function () use ($shop, $attributes, $proofPath): void {
                $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
                $plan = ShopPlan::query()->where('slug', $attributes['plan_slug'])->first();
                if ($shop->verification_status !== 'approved' || $shop->shop_status !== 'active' || ! $plan || $plan->slug === 'free') {
                    throw ValidationException::withMessages(['plan' => 'Your shop must be approved and active before submitting payment details.']);
                }
                if ($shop->planPayments()->whereIn('status', ['pending', 'checkout_pending'])->exists()) {
                    throw ValidationException::withMessages(['payment' => 'A payment submission is already waiting for review.']);
                }
                if (! $plan->hasPaymentDetails()) {
                    throw ValidationException::withMessages(['payment' => 'Payment instructions are not available yet. Please wait for the administrator to add them.']);
                }

                $shop->planPayments()->create([
                    'plan_slug' => $plan->slug,
                    'amount' => $plan->monthly_price,
                    'reference' => $attributes['reference'] ?? null,
                    'proof_path' => $proofPath,
                    'status' => 'pending',
                ]);
                $shop->update(['requested_plan' => $plan->slug]);
            });
        } catch (\Throwable $exception) {
            if ($proofPath) {
                Storage::delete($proofPath);
            }

            throw $exception;
        }

        return to_route('shop.pricing')->with('status', 'Payment details submitted for admin review. Your plan activates after approval.');
    }

    public function download(Request $request, ShopPlanPayment $shopPlanPayment): StreamedResponse
    {
        abort_unless($shopPlanPayment->shop->owner_id === $request->user()->id && $shopPlanPayment->hasProof(), 404);

        return Storage::download($shopPlanPayment->proof_path);
    }
}
