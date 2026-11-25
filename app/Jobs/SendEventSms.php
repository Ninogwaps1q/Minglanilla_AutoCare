<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\User;
use App\Notifications\ServiceAlert;
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

    public function __construct(public int $notificationId) {}

    public function handle(SmsService $sms): void
    {
        $notification = Notification::query()->find($this->notificationId);
        $event = $notification ? ServiceAlert::forNotification($notification) : null;
        if (! $event || ! config('services.semaphore.api_key')) {
            return;
        }

        $recipient = DB::transaction(function () use ($notification, $event, $sms): ?array {
            $user = User::query()->lockForUpdate()->find($notification->user_id);
            $phone = $sms->phone($user?->phone);
            if ($user?->status !== 'active' || ! $phone || ! $user->receivesEventVia($event->value, 'sms')) {
                return null;
            }

            $day = now('Asia/Manila')->toDateString();
            $deliveries = DB::table('sms_deliveries');
            if ((clone $deliveries)->where('notification_id', $notification->id)->exists()
                || (clone $deliveries)->where('user_id', $user->id)->where('delivery_day', $day)->count() >= config('services.semaphore.daily_limit')) {
                return null;
            }
            $deliveries->insert(['user_id' => $user->id, 'notification_id' => $notification->id, 'delivery_day' => $day, 'status' => 'sending']);

            return ['phone' => $phone, 'locale' => $user->locale ?? 'en'];
        });
        if (! $recipient) {
            return;
        }

        try {
            $sms->send($recipient['phone'], __('alerts.sms_'.$event->value, [], $recipient['locale']));
            $status = 'accepted';
        } catch (Throwable $exception) {
            Log::warning('sms.delivery_failed', ['notification_id' => $this->notificationId, 'exception_type' => $exception::class]);
            $status = 'failed';
        }
        DB::table('sms_deliveries')->where('notification_id', $this->notificationId)->update(['status' => $status]);
    }
}
