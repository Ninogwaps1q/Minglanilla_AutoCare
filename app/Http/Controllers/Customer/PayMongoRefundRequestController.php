<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PayMongoRefundRequestController extends Controller
{
    public function __invoke(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->customer_id === $request->user()->id, 404);
        $attributes = $request->validate(['amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'], 'reason' => ['required', 'string', 'max:500']]);
        if ($payment->status !== 'confirmed' || ! $payment->paymongo_payment_id || ($payment->refund_status && $payment->refund_status !== 'rejected') || $payment->settlement_status === 'settled' || (float) $attributes['amount'] > (float) $payment->amount) {
            throw ValidationException::withMessages(['amount' => 'This payment is not eligible for this refund request.']);
        }
        $payment->update(['refund_amount' => $attributes['amount'], 'refund_reason' => $attributes['reason'], 'refund_status' => 'requested', 'refund_requested_at' => now()]);

        return back()->with('status', 'Refund request sent to the administrator for review.');
    }
}
