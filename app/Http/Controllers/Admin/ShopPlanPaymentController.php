<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Shop;
use App\Models\ShopPlanPayment;
use App\Services\ShopSubscriptionBilling;
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
            $review = [
                'status' => $attributes['decision'],
                'review_notes' => $attributes['review_notes'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ];
            if ($attributes['decision'] === 'approved') {
                app(ShopSubscriptionBilling::class)->approve($shop, $payment, $review);
            } else {
                $payment->update($review);
            }
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
                'title' => $payment->status === 'approved' ? 'Shop plan payment confirmed' : 'Shop plan payment needs attention',
                'message' => $payment->status === 'approved'
                    ? ucfirst($payment->plan_slug).' coverage: '.$payment->period_starts_at->format('M j, Y').' to '.$payment->period_ends_at->format('M j, Y').'.'
                    : 'Your payment details were not approved. Check the review note and submit again.',
                'data' => ['shop_plan_payment_id' => $payment->id],
            ]);
        });

        return back()->with('status', 'Plan payment review saved.');
    }

    public function download(ShopPlanPayment $shopPlanPayment): StreamedResponse
    {
        abort_unless($shopPlanPayment->hasProof(), 404);

        return Storage::download($shopPlanPayment->proof_path);
    }

    public function preview(ShopPlanPayment $shopPlanPayment): StreamedResponse
    {
        abort_unless($shopPlanPayment->hasImageProof(), 404);
        $mimeType = Storage::mimeType($shopPlanPayment->proof_path);
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return Storage::response($shopPlanPayment->proof_path, headers: [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
