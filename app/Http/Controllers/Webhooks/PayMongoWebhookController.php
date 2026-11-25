<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\ShopPlanPayment;
use App\Models\User;
use App\Services\PayMongoRefunds;
use App\Services\ShopSubscriptionBilling;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayMongoWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (! $this->validSignature($request)) {
            return response('Invalid signature', 401);
        }

        $attributes = $request->input('data.attributes');
        if (! is_array($attributes) && $request->input('event_type') === 'send.webhook') {
            $attributes = $request->input('data');
        }
        $live = str_starts_with((string) config('services.paymongo.secret_key'), 'sk_live_');
        if (! is_array($attributes) || ($attributes['livemode'] ?? null) !== $live) {
            return response('Invalid event mode', 400);
        }
        if (($attributes['type'] ?? null) !== 'checkout_session.payment.paid') {
            if (in_array($attributes['type'] ?? null, ['dispute.created', 'dispute.resolved', 'payment.refund.updated'], true)) {
                return $this->recordPaymentEvent($attributes);
            }

            return response('Ignored', 200);
        }

        return $this->processSession($attributes['data'] ?? []);
    }

    /** @param array<string, mixed> $session */
    public function processSession(array $session): Response
    {
        $sessionId = $session['id'] ?? null;
        $reference = $session['attributes']['reference_number'] ?? null;
        $paid = collect($session['attributes']['payments'] ?? [])->first(fn (mixed $payment): bool => is_array($payment) && ($payment['attributes']['status'] ?? null) === 'paid');
        $amount = $paid['attributes']['amount'] ?? null;
        $currency = $paid['attributes']['currency'] ?? null;
        $paymentId = $paid['id'] ?? null;
        $method = $paid['attributes']['source']['type'] ?? null;
        $method = is_string($method) && strlen($method) <= 50 ? $method : null;

        if (! is_string($sessionId) || ! str_starts_with($sessionId, 'cs_') || ! is_string($paymentId) || ! str_starts_with($paymentId, 'pay_') || ! is_int($amount) || $currency !== 'PHP' || ! is_string($reference)) {
            return response('Invalid payment data', 422);
        }

        if (preg_match('/^shop-plan-(\d+)$/', $reference, $matches)) {
            return $this->activatePlan((int) $matches[1], $sessionId, $paymentId, $amount, $method);
        }
        if (preg_match('/^invoice-(\d+)$/', $reference, $matches)) {
            return $this->confirmInvoice((int) $matches[1], $sessionId, $paymentId, $amount, $paid['attributes']);
        }

        return response('Unknown reference', 422);
    }

    /** @param array<string, mixed> $attributes */
    private function recordPaymentEvent(array $attributes): Response
    {
        $resource = $attributes['data'] ?? [];
        $eventType = $attributes['type'];
        $paymentId = $resource['attributes']['payment_id'] ?? $resource['attributes']['payment']['id'] ?? null;
        if ($eventType === 'payment.refund.updated') {
            if (! is_string($paymentId)) {
                return response('Invalid refund payment', 422);
            }
            $refundPayment = Payment::query()->where('paymongo_payment_id', $paymentId)->first()
                ?? ShopPlanPayment::query()->where('paymongo_payment_id', $paymentId)->first();
            if ($refundPayment && ! app(PayMongoRefunds::class)->apply($refundPayment, $resource)) {
                return response('Refund mismatch', 422);
            }

            return response('Processed', 200);
        }
        $payment = is_string($paymentId) ? Payment::query()->where('paymongo_payment_id', $paymentId)->first() : null;
        if (! $payment && str_starts_with($eventType, 'dispute.') && is_string($resource['id'] ?? null)) {
            $payment = Payment::query()->where('paymongo_dispute_id', $resource['id'])->first();
        }
        if (! $payment && ! is_string($paymentId)) {
            Log::warning('PayMongo event has no payment reference', ['event_type' => $eventType, 'resource_id' => $resource['id'] ?? null]);
            $resourceId = $resource['id'] ?? null;
            if (is_string($resourceId) && Cache::add('paymongo-unmatched-event-'.$resourceId, true, now()->addDay())) {
                $this->alertAdmins('PayMongo '.$eventType.' event '.$resourceId.' needs manual payment matching in the dashboard.', 'PayMongo event needs review');
            }

            return response('Unmatched event', 200);
        }
        if (! $payment) {
            return response('Unknown payment', 200);
        }
        DB::transaction(function () use ($payment, $resource, $eventType): void {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $dispute = $resource['attributes'] ?? [];
            if ($eventType === 'dispute.created' && $payment->paymongo_dispute_id === ($resource['id'] ?? null) && in_array($payment->dispute_status, ['won', 'lost', 'resolved'], true)) {
                return;
            }
            $payment->update(['dispute_status' => $eventType === 'dispute.created' ? 'open' : ($dispute['status'] ?? 'resolved'), 'paymongo_dispute_id' => $resource['id'] ?? null, 'dispute_amount' => is_int($dispute['amount'] ?? null) ? $dispute['amount'] / 100 : $payment->dispute_amount, 'dispute_reason' => $dispute['reason'] ?? $payment->dispute_reason]);
            if ($eventType === 'dispute.created') {
                $this->alertAdmins('PayMongo dispute '.($resource['id'] ?? '').' opened for payment '.$payment->paymongo_payment_id.'. Review the shop settlement.', 'PayMongo dispute opened');
            }
        });

        return response('Processed', 200);
    }

    private function validSignature(Request $request): bool
    {
        $secret = config('services.paymongo.webhook_secret');
        $header = $request->header('Paymongo-Signature', '');
        if (! is_string($secret) || $secret === '' || ! is_string($header)) {
            return false;
        }
        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }
        $timestamp = $parts['t'] ?? '';
        $mode = str_starts_with((string) config('services.paymongo.secret_key'), 'sk_live_') ? 'li' : 'te';
        $signature = $parts[$mode] ?? '';
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300 || ! preg_match('/^[a-f0-9]{64}$/i', $signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret), $signature);
    }

    private function activatePlan(int $id, string $sessionId, string $paymentId, int $amount, ?string $method): Response
    {
        return DB::transaction(function () use ($id, $sessionId, $paymentId, $amount, $method): Response {
            $payment = ShopPlanPayment::query()->lockForUpdate()->find($id);
            if (! $payment || ($payment->paymongo_session_id && $payment->paymongo_session_id !== $sessionId) || $payment->amount * 100 !== $amount) {
                Log::warning('PayMongo shop plan payment mismatch', ['payment_id' => $id, 'session_id' => $sessionId]);
                $this->alertAdmins('Shop plan payment #'.$id.' has a PayMongo amount or session mismatch.');

                return response('Payment mismatch', 422);
            }
            if ($payment->status === 'approved' && $payment->paymongo_payment_id === $paymentId) {
                if ($method && ! $payment->paymongo_method) {
                    $payment->update(['paymongo_method' => $method]);
                }

                return response('Already processed', 200);
            }
            if ($payment->status !== 'checkout_pending' || ShopPlanPayment::query()->where('paymongo_payment_id', $paymentId)->whereKeyNot($id)->exists()) {
                return response('Payment state conflict', 409);
            }

            $shop = Shop::query()->lockForUpdate()->findOrFail($payment->shop_id);
            app(ShopSubscriptionBilling::class)->approve($shop, $payment, ['paymongo_session_id' => $sessionId, 'paymongo_payment_id' => $paymentId, 'paymongo_method' => $method, 'reference' => $paymentId, 'reviewed_at' => now()]);
            $shop->owner->notifications()->create(['type' => 'shop_plan_payment', 'title' => 'Shop plan payment confirmed', 'message' => ucfirst($payment->plan_slug).' coverage: '.$payment->period_starts_at->format('M j, Y').' to '.$payment->period_ends_at->format('M j, Y').'. Your current paid days are preserved.', 'data' => ['shop_plan_payment_id' => $payment->id]]);

            return response('Processed', 200);
        });
    }

    /** @param array<string, mixed> $gatewayPayment */
    private function confirmInvoice(int $id, string $sessionId, string $paymentId, int $amount, array $gatewayPayment): Response
    {
        return DB::transaction(function () use ($id, $sessionId, $paymentId, $amount, $gatewayPayment): Response {
            $payment = Payment::query()->lockForUpdate()->find($id);
            if (! $payment || ($payment->paymongo_session_id && $payment->paymongo_session_id !== $sessionId) || (int) round((float) $payment->amount * 100) !== $amount) {
                Log::warning('PayMongo invoice payment mismatch', ['payment_id' => $id, 'session_id' => $sessionId]);
                $this->alertAdmins('Invoice payment #'.$id.' has a PayMongo amount or session mismatch.');

                return response('Payment mismatch', 422);
            }
            if ($payment->status === 'confirmed' && $payment->paymongo_payment_id === $paymentId) {
                $fee = $gatewayPayment['fee'] ?? null;
                $netAmount = $gatewayPayment['net_amount'] ?? null;
                $method = $gatewayPayment['source']['type'] ?? null;
                if (is_string($method) && strlen($method) <= 50 && ! $payment->paymongo_method) {
                    $payment->update(['paymongo_method' => $method]);
                }
                if (is_int($fee) && is_int($netAmount) && ($payment->paymongo_fee === null || $payment->paymongo_net_amount === null)) {
                    $payment->update(['paymongo_fee' => $fee / 100, 'paymongo_net_amount' => $netAmount / 100]);
                }

                return response('Already processed', 200);
            }
            if ($payment->status !== 'checkout_pending' || Payment::query()->where('paymongo_payment_id', $paymentId)->whereKeyNot($id)->exists()) {
                return response('Payment state conflict', 409);
            }
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
            $confirmedCents = (int) round($invoice->confirmedPaymentsTotal() * 100);
            if ($confirmedCents + $amount > (int) round($invoice->payableTotal() * 100)) {
                return response('Invoice total conflict', 409);
            }
            $fee = $gatewayPayment['fee'] ?? null;
            $netAmount = $gatewayPayment['net_amount'] ?? null;
            $method = $gatewayPayment['source']['type'] ?? null;
            $payment->update(['status' => 'confirmed', 'paymongo_session_id' => $sessionId, 'paymongo_payment_id' => $paymentId, 'paymongo_method' => is_string($method) && strlen($method) <= 50 ? $method : null, 'paymongo_fee' => is_int($fee) ? $fee / 100 : null, 'paymongo_net_amount' => is_int($netAmount) ? $netAmount / 100 : null, 'reference_number' => $paymentId, 'settlement_status' => 'pending', 'reviewed_at' => now()]);
            $invoice->update(['status' => $confirmedCents + $amount >= (int) round($invoice->payableTotal() * 100) ? 'paid' : 'unpaid']);
            $payment->shop->owner->notifications()->create(['type' => 'payment_verification', 'title' => 'Online payment received', 'message' => 'A customer invoice payment was received by the platform. Settlement is pending.', 'data' => ['payment_id' => $payment->id]]);

            return response('Processed', 200);
        });
    }

    private function alertAdmins(string $message, string $title = 'PayMongo payment mismatch'): void
    {
        User::query()->where('role', 'admin')->each(function (User $admin) use ($message, $title): void {
            $admin->notifications()->create(['type' => 'paymongo_alert', 'title' => $title, 'message' => $message, 'data' => []]);
        });
    }
}
