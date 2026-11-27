<?php

namespace App\Models;

use App\Jobs\SendEventSms;
use App\Jobs\SendNotificationPush;
use App\Notifications\ImportantEventMail;
use App\Notifications\ServiceAlert;
use App\Services\SmsService;
use App\Services\WebPushDelivery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Throwable;

class Notification extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::created(function (self $notification): void {
            if ($notification->workflow_alert_id || $notification->type === 'message_received') {
                return;
            }
            $connection = $notification->getConnection();
            if ($connection->transactionLevel() > 0) {
                $connection->afterCommit(fn () => $notification->dispatchLegacyUpdates());
            } else {
                $notification->dispatchLegacyUpdates();
            }
        });
    }

    private function dispatchLegacyUpdates(): void
    {
        $user = $this->user;
        if ($user?->status !== 'active') {
            return;
        }
        $stage = ServiceAlert::forEmailType($this->type);
        if (app(WebPushDelivery::class)->configured() && $user->receivesEventVia($stage->value, 'push')) {
            try {
                SendNotificationPush::dispatch($this->id);
            } catch (Throwable $exception) {
                Log::warning('notification.push_queue_failed', ['notification_id' => $this->id, 'exception_type' => $exception::class]);
            }
        }

        $smsEvent = ServiceAlert::forNotification($this);
        $mechanicEvent = ServiceAlert::forMechanicNotification($this);
        if ($mechanicEvent && app(SmsService::class)->configured() && $user->receivesEventVia($mechanicEvent->value, 'sms')
            && ($user->communication_preferences['sms'][$mechanicEvent->value] ?? false)) {
            try {
                SendEventSms::dispatch($this->id);
            } catch (Throwable $exception) {
                Log::warning('notification.sms_queue_failed', ['notification_id' => $this->id, 'exception_type' => $exception::class]);
            }
        }
        if (! in_array($this->type, [
            'appointment_requested', 'appointment_status', 'appointment_reschedule_requested', 'appointment_reschedule_decided', 'appointment_cancelled',
            'quotation_sent', 'quotation_requested', 'quotation_response', 'quotation_expired', 'quotation_reminder', 'repair_status', 'shop_plan_reminder',
            'shop_plan_payment', 'emergency_request', 'emergency_status', 'payment_verification', 'appointment_reminder', 'invoice_due', 'maintenance_due',
        ], true) || ($this->type === 'payment_verification' && $smsEvent !== ServiceAlert::Payment)) {
            return;
        }
        if ($user->email && $user->receivesEventVia(($smsEvent ?? $stage)->value, 'email')) {
            try {
                $locale = $user->locale ?? 'en';
                $user->notify((new ImportantEventMail($this->localizedTitle($locale), $this->localizedMessage($locale), $this->destinationFor($user)))->locale($locale));
            } catch (Throwable $exception) {
                Log::warning('notification.mail_queue_failed', ['notification_id' => $this->id, 'exception_type' => $exception::class]);
            }
        }
    }

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function localizedTitle(?string $locale = null): string
    {
        $key = 'ui.'.$this->title;

        return app('translator')->has($key, $locale) ? __($key, [], $locale) : $this->title;
    }

    public function localizedMessage(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        if ($locale === 'en') {
            return $this->message;
        }

        $data = $this->data ?? [];
        $status = $data['status'] ?? '';
        $statusKey = 'notice.'.$status;
        $status = app('translator')->has($statusKey, $locale) ? __($statusKey, [], $locale) : $status;

        return match (true) {
            $this->type === 'appointment_status' && $status !== '' && $status !== 'no_show' => __('notice.appointment_status', ['status' => $status], $locale),
            $this->type === 'quotation_sent' && isset($data['number']) => __('notice.estimate', ['number' => $data['number']], $locale),
            $this->type === 'repair_status' && $status !== '' && isset($data['number']) => __('notice.repair_status', ['status' => $status, 'number' => $data['number']], $locale),
            default => $this->message,
        };
    }

    public function destinationFor(User $user): string
    {
        $data = $this->data ?? [];
        $id = static fn (string $key): ?int => filter_var($data[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

        if ($user->role === 'customer') {
            if ($this->workflow_alert_id) {
                $alert = WorkflowAlert::query()->whereKey($this->workflow_alert_id)->where('customer_id', $user->id)->first();
                if ($alert) {
                    return $alert->destination();
                }
            }
            if ($threadId = $id('thread_id')) {
                $shopId = MessageThread::query()->whereKey($threadId)->where('customer_id', $user->id)->value('shop_id');
                if ($shopId) {
                    return route('customer.messages.show', $shopId);
                }
            }

            if ($repairId = $id('repair_job_id')) {
                return route('customer.repairs.index', ['repair_job_id' => $repairId]).'#repair-'.$repairId;
            }

            foreach (['appointment_id' => 'customer.appointments.index', 'quotation_id' => 'customer.quotations.index', 'invoice_id' => 'customer.invoices.index', 'service_case_id' => 'customer.service-cases.index', 'emergency_request_id' => 'customer.emergency-requests.index', 'reminder_id' => 'customer.reminders.index', 'review_id' => 'customer.reviews.index'] as $key => $routeName) {
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

        if ($user->isProviderOwner()) {
            if (in_array($this->type, ['shop_plan_payment', 'shop_plan_reminder'], true)) {
                return route('shop.pricing');
            }
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
