<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PayMongoRefunds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PayMongoRefundController extends Controller
{
    public function __invoke(Request $request, Payment $payment, PayMongoRefunds $refunds): RedirectResponse
    {
        $attributes = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'notes' => ['nullable', 'string', 'max:1000'], 'refund_resolution' => ['required_if:decision,approved', 'nullable', 'in:credit,replacement']]);
        $refunds->review($payment, $request->user()->id, $attributes['decision'], $attributes['notes'] ?? null, $attributes['refund_resolution'] ?? null);

        return back()->with('status', 'Refund review saved. Status: '.str_replace('_', ' ', $payment->fresh()->refund_status).'.');
    }

    public function check(Request $request, Payment $payment, PayMongoRefunds $refunds): RedirectResponse
    {
        $attributes = $request->validate(['refund_id' => ['nullable', 'string', 'regex:/^ref_[A-Za-z0-9_-]+$/', 'max:100']]);
        if (! $refunds->check($payment, $attributes['refund_id'] ?? null)) {
            throw ValidationException::withMessages(['refund' => 'The refund could not be verified. Check its ID, payment, and amount in PayMongo.']);
        }

        return back()->with('status', 'Refund status checked with PayMongo.');
    }
}
