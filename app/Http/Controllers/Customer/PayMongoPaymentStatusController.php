<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Webhooks\PayMongoWebhookController;
use App\Models\Payment;
use App\Services\PayMongoCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayMongoPaymentStatusController extends Controller
{
    public function __invoke(Request $request, Payment $payment, PayMongoCheckout $checkout, PayMongoWebhookController $webhook): JsonResponse
    {
        abort_unless($payment->customer_id === $request->user()->id, 404);
        if ($payment->status !== 'checkout_pending') {
            return response()->json(['status' => $payment->status]);
        }
        $session = $payment->paymongo_session_id ? $checkout->retrieve($payment->paymongo_session_id) : null;
        if ($session) {
            $webhook->processSession($session);
        }

        return response()->json(['status' => $payment->fresh()->status]);
    }
}
