<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopPlan;
use App\Models\ShopPlanPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopPlanPaymentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'reference' => ['nullable', 'required_without:proof', 'string', 'max:100'],
            'proof' => ['nullable', 'required_without:reference', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        $shop = $request->user()->shops()->firstOrFail();
        $proofPath = $request->hasFile('proof') ? $request->file('proof')->store("shop-plan-payments/{$shop->id}") : null;

        try {
            DB::transaction(function () use ($shop, $attributes, $proofPath): void {
                $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
                $plan = ShopPlan::query()->where('slug', $shop->requested_plan)->first();
                if ($shop->verification_status !== 'approved' || $shop->shop_status !== 'active' || ! $plan || $plan->slug === 'free') {
                    throw ValidationException::withMessages(['plan' => 'Request a paid plan for an approved, active shop before submitting payment details.']);
                }
                if (! $plan->payment_instructions) {
                    throw ValidationException::withMessages(['payment' => 'Payment instructions are not available yet. Please wait for the administrator to add them.']);
                }
                if ($shop->planPayments()->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages(['payment' => 'A payment submission is already waiting for review.']);
                }

                $shop->planPayments()->create([
                    'plan_slug' => $plan->slug,
                    'amount' => $plan->monthly_price,
                    'reference' => $attributes['reference'] ?? null,
                    'proof_path' => $proofPath,
                    'status' => 'pending',
                ]);
            });
        } catch (\Throwable $exception) {
            if ($proofPath) {
                Storage::delete($proofPath);
            }

            throw $exception;
        }

        return back()->with('status', 'Payment details submitted for admin review. Your plan activates after approval.');
    }

    public function download(Request $request, ShopPlanPayment $shopPlanPayment): StreamedResponse
    {
        abort_unless($shopPlanPayment->shop->owner_id === $request->user()->id && $shopPlanPayment->proof_path, 404);

        return Storage::download($shopPlanPayment->proof_path);
    }
}
