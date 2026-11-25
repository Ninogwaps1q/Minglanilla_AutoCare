<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Notifications\ServiceAlert;
use App\Services\WebPushDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $notificationId) {}

    public function handle(WebPushDelivery $push): void
    {
        $notification = Notification::query()->with('user')->find($this->notificationId);
        $user = $notification?->user;
        if (! $notification || $user?->status !== 'active'
            || ! $user->receivesEventVia(ServiceAlert::forEmailType($notification->type)->value, 'push')) {
            return;
        }

        try {
            $locale = $user->locale ?? 'en';
            $push->send($user, [
                'title' => $notification->localizedTitle($locale),
                'body' => __('workflow.open_update', [], $locale),
                'url' => $notification->destinationFor($user),
                'tag' => 'notice-'.$notification->id,
            ]);
        } catch (Throwable $exception) {
            Log::warning('notification.push_failed', ['notification_id' => $notification->id, 'exception_type' => $exception::class]);
        }
    }
}
