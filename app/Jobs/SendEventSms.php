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
use Illuminate\Support\Str;
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
        $mechanicEvent = $notification ? ServiceAlert::forMechanicNotification($notification) : null;
        $event = $workflow?->event->stage() ?? $mechanicEvent ?? ($notification ? ServiceAlert::forNotification($notification) : null);
        if (! $event?->supportsSms() || (! $workflow && ! $mechanicEvent && in_array($event, [ServiceAlert::Booking, ServiceAlert::Roadside, ServiceAlert::Plan], true)) || ! $sms->configured()) {
            return;
        }

        $record = $workflow ? ['workflow_alert_id' => $workflow->id] : ['notification_id' => $notification->id];
        $userId = $workflow?->customer_id ?? $notification->user_id;
        $recipient = DB::transaction(function () use ($record, $userId, $event, $sms, $mechanicEvent): ?array {
            $user = User::query()->lockForUpdate()->find($userId);
            $phone = $sms->phone($user?->phone);
            if ($user?->status !== 'active' || ! $phone || ! $user->receivesEventVia($event->value, 'sms')
                || ($mechanicEvent && ! ($user->communication_preferences['sms'][$event->value] ?? false))) {
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
            } elseif ($mechanicEvent) {
                $locale = $recipient['user']->locale ?? 'en';
                $key = $notification->type === 'appointment_status' && ($notification->data['status'] ?? '') === 'en_route' ? 'en_route' : $notification->type;
                $link = $notification->destinationFor($recipient['user']);
                $text = Str::limit(Str::ascii(__('mechanic.sms.'.$key, [], $locale)), max(0, 159 - strlen($link)), '').' '.$link;
            } elseif ($event === ServiceAlert::Messages) {
                $text = __('extensions.chat.sms', [], $recipient['user']->locale ?? 'en').' '.$notification->destinationFor($recipient['user']);
            } else {
                $text = __('alerts.sms_'.$event->value, [], $recipient['user']->locale ?? 'en');
            }

            $result = $sms->send($recipient['phone'], $text);
        } catch (Throwable $exception) {
            $result = ['accepted' => false, 'response' => ['exception_type' => $exception::class], 'cost' => null];
        }
        $simulated = ($result['response']['provider'] ?? null) === 'demo';
        if (! $result['accepted'] && ! $simulated) {
            Log::warning('sms.delivery_failed', $record + $result['response']);
        }
        DB::table('sms_deliveries')->where($record)->update([
            'status' => $simulated ? 'simulated' : ($result['accepted'] ? 'accepted' : 'failed'),
            'provider_response' => json_encode($result['response'], JSON_THROW_ON_ERROR),
            'cost' => $result['cost'],
        ]);
    }
}
