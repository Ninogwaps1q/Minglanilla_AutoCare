<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PayMongoRefunds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PayMongoRefundRequestController extends Controller
{
    public function __invoke(Request $request, Payment $payment, PayMongoRefunds $refunds): RedirectResponse
    {
        abort_unless($payment->customer_id === $request->user()->id, 404);
        $attributes = $request->validate(['amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'], 'reason' => ['required', 'string', 'max:500']]);
        $refunds->request($payment, $request->user()->id, $attributes['reason'], (int) round((float) $attributes['amount'] * 100));

        return back()->with('status', 'Refund request sent to the administrator for review.');
    }
}
