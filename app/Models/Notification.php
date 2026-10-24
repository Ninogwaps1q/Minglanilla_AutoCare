<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function destinationFor(User $user): string
    {
        $data = $this->data ?? [];
        $id = static fn (string $key): ?int => filter_var($data[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

        if ($user->role === 'customer') {
            if ($threadId = $id('thread_id')) {
                $shopId = MessageThread::query()->whereKey($threadId)->where('customer_id', $user->id)->value('shop_id');
                if ($shopId) {
                    return route('customer.messages.show', $shopId);
                }
            }

            if ($repairId = $id('repair_job_id')) {
                return route('customer.repairs.index', ['repair_job_id' => $repairId]).'#repair-'.$repairId;
            }

            foreach (['appointment_id' => 'customer.appointments.index', 'quotation_id' => 'customer.quotations.index', 'service_case_id' => 'customer.service-cases.index', 'emergency_request_id' => 'customer.emergency-requests.index', 'reminder_id' => 'customer.reminders.index', 'review_id' => 'customer.reviews.index'] as $key => $routeName) {
                if ($id($key)) {
                    return route($routeName, ['focus' => $id($key)]);
                }
            }

            if ($paymentId = $id('payment_id')) {
                $invoiceId = Payment::query()->whereKey($paymentId)->where('customer_id', $user->id)->value('invoice_id');
                if ($invoiceId) {
                    return route('customer.invoices.index', ['focus' => $invoiceId]);
                }
            }

            return route('customer.notifications.index');
        }

        if ($user->role === 'shop_owner') {
            if ($threadId = $id('thread_id')) {
                return route('shop.messages.show', $threadId);
            }

            if ($repairId = $id('repair_job_id')) {
                return route('shop.repair-jobs.index', ['repair_job_id' => $repairId]).'#repair-'.$repairId;
            }

            foreach (['appointment_id' => 'shop.appointments.index', 'quotation_id' => 'shop.quotations.index', 'service_case_id' => 'shop.service-cases.index', 'emergency_request_id' => 'shop.emergency-requests.index', 'review_id' => 'shop.reviews.index'] as $key => $routeName) {
                if ($id($key)) {
                    return route($routeName, ['focus' => $id($key)]);
                }
            }

            if ($paymentId = $id('payment_id')) {
                $invoiceId = Payment::query()->whereKey($paymentId)->whereHas('shop', fn ($query) => $query->where('owner_id', $user->id))->value('invoice_id');
                if ($invoiceId) {
                    return route('shop.invoices.index', ['focus' => $invoiceId]);
                }
            }

            if ($id('shop_id') || $id('shop_document_id')) {
                return route('shop.profile.edit');
            }

            return route('shop.notifications.index');
        }

        return route('dashboard');
    }
}
