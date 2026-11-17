<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PayMongoCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PayMongoRefundController extends Controller
{
    public function __invoke(Request $request, Payment $payment, PayMongoCheckout $checkout): RedirectResponse
    {
        $attributes = $request->validate(['decision' => ['required', 'in:approved,rejected']]);
        if ($payment->refund_status !== 'requested' || ! $payment->paymongo_payment_id || $payment->settlement_status === 'settled') {
            throw ValidationException::withMessages(['refund' => 'This refund cannot be reviewed here. Check its settlement and status.']);
        }
        if ($attributes['decision'] === 'rejected') {
            $payment->update(['refund_status' => 'rejected']);

            return back()->with('status', 'Refund request rejected.');
        }
        $refund = $checkout->refund($payment->paymongo_payment_id, (int) round((float) $payment->refund_amount * 100), 'others', 'refund-payment-'.$payment->id);
        if (! is_array($refund) || ! is_string($refund['id'] ?? null) || ! str_starts_with($refund['id'], 'ref_') || ($refund['attributes']['payment_id'] ?? null) !== $payment->paymongo_payment_id) {
            throw ValidationException::withMessages(['refund' => 'PayMongo has not accepted this refund. Check the payment in PayMongo before retrying.']);
        }
        $status = $refund['attributes']['status'] ?? 'processing';
        $payment->update(['paymongo_refund_id' => $refund['id'], 'refund_status' => in_array($status, ['processing', 'succeeded'], true) ? $status : 'processing', 'refunded_at' => $status === 'succeeded' ? now() : null]);
        if ($status === 'succeeded') {
            $payment->invoice->update(['status' => 'unpaid']);
        }

        return back()->with('status', 'PayMongo refund submitted.');
    }
}
