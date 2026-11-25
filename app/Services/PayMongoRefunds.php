<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\ShopPlanPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PayMongoRefunds
{
    public const HOLDS_SETTLEMENT = ['requested', 'submitting', 'needs_review', 'pending', 'processing'];

    public function __construct(private PayMongoCheckout $checkout, private ShopSubscriptionBilling $subscriptions) {}

    public function request(Payment|ShopPlanPayment $payment, int $userId, string $reason, ?int $amountCentavos = null): void
    {
        DB::transaction(function () use ($payment, $userId, $reason, $amountCentavos): void {
            $payment = $payment->newQuery()->lockForUpdate()->findOrFail($payment->id);
            $isSubscription = $payment instanceof ShopPlanPayment;
            $paidAmount = (int) round((float) $payment->amount * 100);
            $refundAmount = $isSubscription ? $paidAmount : $amountCentavos;
            if ($payment->status !== ($isSubscription ? 'approved' : 'confirmed') || ! $payment->paymongo_payment_id
                || ! in_array($payment->refund_status, [null, 'rejected', 'failed'], true)
                || (! $isSubscription && in_array($payment->settlement_status, ['reserved', 'settled'], true))
                || ! $refundAmount || $refundAmount > $paidAmount || $refundAmount < 1) {
                throw ValidationException::withMessages(['refund' => 'This payment cannot be refunded here. Check its refund and settlement status.']);
            }
            $payment->update(['refund_amount' => $refundAmount / 100, 'refund_reason' => $reason, 'refund_status' => 'requested', 'refund_requested_at' => now(), 'refund_reviewed_by' => null, 'refund_reviewed_at' => null, 'refund_review_notes' => null, 'refund_request_key' => (string) Str::uuid(), 'paymongo_refund_id' => null, 'refunded_at' => null]);
            $this->audit($payment, 'requested', $userId);
        });
    }

    public function review(Payment|ShopPlanPayment $payment, int $adminId, string $decision, ?string $notes, ?string $resolution = null): void
    {
        $payment = DB::transaction(function () use ($payment, $adminId, $decision, $notes, $resolution): Payment|ShopPlanPayment {
            $payment = $payment->newQuery()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->refund_status !== 'requested' || ! $payment->paymongo_payment_id
                || $payment->status !== ($payment instanceof ShopPlanPayment ? 'approved' : 'confirmed')
                || (float) $payment->refund_amount <= 0 || (float) $payment->refund_amount > (float) $payment->amount
                || ($payment instanceof Payment && in_array($payment->settlement_status, ['reserved', 'settled'], true))) {
                throw ValidationException::withMessages(['refund' => 'This refund has already been reviewed or the shop has been settled.']);
            }
            if ($payment instanceof ShopPlanPayment && (int) round((float) $payment->refund_amount * 100) !== $payment->amount * 100) {
                throw ValidationException::withMessages(['refund' => 'Subscription refunds must match the full original payment.']);
            }
            if ($decision === 'approved' && ! $this->checkout->configured()) {
                throw ValidationException::withMessages(['refund' => 'PayMongo is unavailable. The refund is still awaiting approval.']);
            }
            if ($payment instanceof Payment && $decision === 'approved' && ! in_array($resolution, ['credit', 'replacement'], true)) {
                throw ValidationException::withMessages(['refund_resolution' => 'Choose whether this refund reduces the invoice or requires a replacement payment.']);
            }
            $updates = ['refund_status' => $decision === 'approved' ? 'submitting' : 'rejected', 'refund_reviewed_by' => $adminId, 'refund_reviewed_at' => now(), 'refund_review_notes' => $notes, 'refund_request_key' => $payment->refund_request_key ?? (string) Str::uuid()];
            if ($payment instanceof Payment) {
                $updates['refund_resolution'] = $decision === 'approved' ? $resolution : null;
            }
            $payment->update($updates);
            $this->audit($payment, $decision, $adminId);

            return $payment;
        });
        if ($decision === 'rejected') {
            return;
        }

        try {
            $refund = $this->checkout->refund($payment->paymongo_payment_id, (int) round((float) $payment->refund_amount * 100), 'others', $payment->refund_request_key);
        } catch (RuntimeException $exception) {
            $this->markSubmission($payment, 'failed');
            throw ValidationException::withMessages(['refund' => $exception->getMessage()]);
        }
        if (! $refund || ! $this->apply($payment, $refund)) {
            $this->markSubmission($payment, 'needs_review');
            throw ValidationException::withMessages(['refund' => 'The refund result is uncertain. Settlement remains on hold. Check PayMongo and match the refund ID; do not submit a second refund.']);
        }
    }

    /** @param array<string, mixed> $resource */
    public function apply(Payment|ShopPlanPayment $payment, array $resource): bool
    {
        return DB::transaction(function () use ($payment, $resource): bool {
            $payment = $payment->newQuery()->lockForUpdate()->findOrFail($payment->id);
            $attributes = $resource['attributes'] ?? [];
            $refundId = $resource['id'] ?? null;
            $status = $attributes['status'] ?? null;
            if (! is_string($refundId) || ! preg_match('/^ref_[A-Za-z0-9_-]+$/', $refundId)
                || ($attributes['payment_id'] ?? null) !== $payment->paymongo_payment_id
                || ! is_int($attributes['amount'] ?? null) || $attributes['amount'] !== (int) round((float) $payment->refund_amount * 100)
                || (isset($attributes['currency']) && $attributes['currency'] !== 'PHP')
                || ! in_array($status, ['pending', 'processing', 'succeeded', 'failed'], true)
                || ($payment->paymongo_refund_id && $payment->paymongo_refund_id !== $refundId)
                || (! $payment->paymongo_refund_id && ! in_array($payment->refund_status, ['submitting', 'needs_review'], true))) {
                return false;
            }
            if ($payment->refund_status === 'succeeded' || ($payment->refund_status === 'failed' && $status !== 'succeeded')) {
                return true;
            }
            if ($payment->refund_status === 'processing' && $status === 'pending') {
                return true;
            }
            if ($payment->refund_status === $status && $payment->paymongo_refund_id === $refundId) {
                return true;
            }
            $payment->update(['paymongo_refund_id' => $refundId, 'refund_status' => $status, 'refunded_at' => $status === 'succeeded' ? now() : null]);
            if ($status === 'succeeded') {
                if ($payment instanceof ShopPlanPayment) {
                    $shop = Shop::query()->lockForUpdate()->findOrFail($payment->shop_id);
                    $this->subscriptions->sync($shop);
                    $payment->shop->owner->notifications()->create(['type' => 'payment_refund', 'title' => 'Refund confirmed', 'message' => 'PayMongo confirmed your refund of PHP '.number_format((float) $payment->refund_amount, 2).'. Your payment record has been updated.', 'data' => ['payment_id' => $payment->id, 'payment_type' => 'subscription']]);
                } else {
                    $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
                    $invoice->update(['status' => $invoice->remainingBalance() <= 0 ? 'paid' : 'unpaid']);
                }
            }
            $this->audit($payment, $status, null);

            return true;
        });
    }

    public function check(Payment|ShopPlanPayment $payment, ?string $refundId = null): bool
    {
        $refundId ??= $payment->paymongo_refund_id;
        $resource = $refundId ? $this->checkout->retrieveRefund($refundId) : null;

        return $resource && $this->apply($payment, $resource);
    }

    private function markSubmission(Payment|ShopPlanPayment $payment, string $status): void
    {
        DB::transaction(function () use ($payment, $status): void {
            $payment = $payment->newQuery()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->refund_status === 'submitting') {
                $payment->update(['refund_status' => $status]);
                $this->audit($payment, $status, $payment->refund_reviewed_by);
            }
        });
    }

    private function audit(Payment|ShopPlanPayment $payment, string $status, ?int $userId): void
    {
        $type = $payment instanceof ShopPlanPayment ? 'Subscription' : 'Invoice';
        ActivityLog::query()->create(['user_id' => $userId, 'module' => 'payments', 'action' => 'refund_'.$status, 'description' => $type.' payment #'.$payment->id.': refund '.$status.'; PHP '.$payment->refund_amount.'; '.($payment->paymongo_refund_id ?? $payment->refund_request_key).'.']);
    }
}
