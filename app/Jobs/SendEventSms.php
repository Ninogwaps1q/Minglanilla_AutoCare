<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\User;
use App\Models\WorkflowAlert;
use App\Notifications\ServiceAlert;
use App\Notifications\Workflow\CustomerWorkflowNotification;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendEventSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public ?int $notificationId = null, public ?int $workflowAlertId = null) {}

    public function handle(SmsService $sms): void
    {
        $workflowId = $this->workflowAlertId ?? null;
        $workflow = $workflowId ? WorkflowAlert::query()->find($workflowId) : null;
        $notification = $this->notificationId ? Notification::query()->find($this->notificationId) : null;
        $event = $workflow?->event->stage() ?? ($notification ? ServiceAlert::forNotification($notification) : null);
        if (! $event?->supportsSms() || (! $workflow && $event === ServiceAlert::Booking) || ! config('services.semaphore.api_key')) {
            return;
        }

        $record = $workflow ? ['workflow_alert_id' => $workflow->id] : ['notification_id' => $notification->id];
        $userId = $workflow?->customer_id ?? $notification->user_id;
        $recipient = DB::transaction(function () use ($record, $userId, $event, $sms): ?array {
            $user = User::query()->lockForUpdate()->find($userId);
            $phone = $sms->phone($user?->phone);
            if ($user?->status !== 'active' || ! $phone || ! $user->receivesEventVia($event->value, 'sms')) {
                return null;
            }

            $day = now('Asia/Manila')->toDateString();
            $deliveries = DB::table('sms_deliveries');
            if ((clone $deliveries)->where($record)->exists()
                || (clone $deliveries)->where('user_id', $user->id)->where('delivery_day', $day)->count() >= config('services.semaphore.daily_limit')) {
                return null;
            }
            $deliveries->insert(['user_id' => $user->id, ...$record, 'delivery_day' => $day, 'status' => 'sending']);

            return ['phone' => $phone, 'user' => $user];
        });
        if (! $recipient) {
            return;
        }

        try {
            if ($workflow) {
                $class = $workflow->event->notificationClass();
                /** @var CustomerWorkflowNotification $message */
                $message = new $class($workflow);
                $text = $message->smsText($recipient['user']);
            } elseif ($event === ServiceAlert::Messages) {
                $text = __('extensions.chat.sms', [], $recipient['user']->locale ?? 'en').' '.$notification->destinationFor($recipient['user']);
            } else {
                $text = __('alerts.sms_'.$event->value, [], $recipient['user']->locale ?? 'en');
            }

            $result = $sms->send($recipient['phone'], $text);
        } catch (Throwable $exception) {
            $result = ['accepted' => false, 'response' => ['exception_type' => $exception::class], 'cost' => null];
        }
        if (! $result['accepted']) {
            Log::warning('sms.delivery_failed', $record + $result['response']);
        }
        DB::table('sms_deliveries')->where($record)->update([
            'status' => $result['accepted'] ? 'accepted' : 'failed',
            'provider_response' => json_encode($result['response'], JSON_THROW_ON_ERROR),
            'cost' => $result['cost'],
        ]);
    }
}
