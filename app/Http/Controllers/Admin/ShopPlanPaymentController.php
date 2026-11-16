<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Shop;
use App\Models\ShopPlanPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopPlanPaymentController extends Controller
{
    public function update(Request $request, ShopPlanPayment $shopPlanPayment): RedirectResponse
    {
        $attributes = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'review_notes' => ['required_if:decision,rejected', 'nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($shopPlanPayment, $attributes, $request): void {
            $payment = ShopPlanPayment::query()->lockForUpdate()->findOrFail($shopPlanPayment->id);
            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'This payment has already been reviewed.']);
            }
            $shop = Shop::query()->lockForUpdate()->findOrFail($payment->shop_id);
            if ($attributes['decision'] === 'approved' && ($shop->verification_status !== 'approved' || $shop->shop_status !== 'active')) {
                throw ValidationException::withMessages(['payment' => 'Approve and reactivate the shop before activating its paid plan.']);
            }
            $periodStartsAt = null;
            $periodEndsAt = null;

            if ($attributes['decision'] === 'approved') {
                $periodStartsAt = now();
                if ($shop->plan_expires_at?->isFuture()) {
                    $periodStartsAt = $shop->plan_expires_at->copy();
                }
                if ($shop->trialIsActive() && $shop->trialEndsAt()->greaterThan($periodStartsAt)) {
                    $periodStartsAt = $shop->trialEndsAt();
                }
                $periodEndsAt = $periodStartsAt->copy()->addMonthNoOverflow();
                $shop->update([
                    'plan_slug' => $payment->plan_slug,
                    'plan_expires_at' => $periodEndsAt,
                    'requested_plan' => null,
                ]);
            }

            $payment->update([
                'status' => $attributes['decision'],
                'review_notes' => $attributes['review_notes'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'period_starts_at' => $periodStartsAt,
                'period_ends_at' => $periodEndsAt,
            ]);
            ActivityLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'reviewed_shop_plan_payment',
                'module' => 'shop_plans',
                'description' => "Shop #{$shop->id} payment #{$payment->id} {$payment->status}.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            $shop->owner->notifications()->create([
                'type' => 'shop_plan_payment',
                'title' => $payment->status === 'approved' ? 'Shop plan activated' : 'Shop plan payment needs attention',
                'message' => $payment->status === 'approved'
                    ? ucfirst($payment->plan_slug).' is active through '.$periodEndsAt->format('M j, Y').'.'
                    : 'Your payment details were not approved. Check the review note and submit again.',
                'data' => ['shop_plan_payment_id' => $payment->id],
            ]);
        });

        return back()->with('status', 'Plan payment review saved.');
    }

    public function download(ShopPlanPayment $shopPlanPayment): StreamedResponse
    {
        abort_unless($shopPlanPayment->proof_path, 404);

        return Storage::download($shopPlanPayment->proof_path);
    }
}
