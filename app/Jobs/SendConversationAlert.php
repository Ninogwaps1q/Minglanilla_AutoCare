<?php

namespace App\Jobs;

use App\Models\MessageThread;
use App\Models\User;
use App\Notifications\ImportantEventMail;
use App\Services\Messaging\MessageAlerts;
use App\Services\SmsService;
use App\Services\WebPushDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SendConversationAlert implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 600;

    public function __construct(public int $threadId, public int $recipientId, public bool $push) {}

    public function uniqueId(): string
    {
        return "{$this->threadId}:{$this->recipientId}:".(int) $this->push;
    }

    public function handle(MessageAlerts $alerts, WebPushDelivery $delivery, SmsService $sms): void
    {
        $thread = MessageThread::query()->with('shop')->find($this->threadId);
        $user = User::query()->where('status', 'active')->find($this->recipientId);
        if (! $user || ! $thread?->shop || $thread->shop->trashed()
            || ! in_array($user->id, [$thread->customer_id, $thread->shop->owner_id], true)
            || $alerts->isViewing($thread->id, $user->id)) {
            return;
        }
        $notice = $user->notifications()->where('type', 'message_received')->whereNull('read_at')
            ->where('data->thread_id', $thread->id)->latest('id')->first();
        $latest = $thread->messages()->where('sender_id', '!=', $user->id)->whereNull('read_at')->latest('id')->first();
        if (! $notice || ! $latest || ! Cache::add('chat:delivery:'.$this->uniqueId(), true, $this->push ? 60 : 300)) {
            return;
        }

        $locale = $user->locale ?? 'en';
        $title = __('extensions.chat.title', [], $locale);
        $body = __('extensions.chat.open', [], $locale);
        $url = $notice->destinationFor($user);
        if ($this->push) {
            if ($user->receivesEventVia('messages', 'push')) {
                $preview = $user->communication_preferences['message_preview'] ?? false;
                $delivery->send($user, ['title' => $title, 'body' => $preview ? Str::limit(Str::squish($latest->message), 80) : $body,
                    'url' => $url, 'tag' => 'conversation-'.$thread->id]);
            }

            return;
        }

        if ($user->email && $user->receivesEventVia('messages', 'email')) {
            try {
                $user->notify((new ImportantEventMail($title, $body, $url))->locale($locale));
            } catch (Throwable $exception) {
                Log::warning('chat.email_failed', ['notification_id' => $notice->id, 'exception_type' => $exception::class]);
            }
        }
        if ($user->receivesEventVia('messages', 'sms')) {
            (new SendEventSms($notice->id))->handle($sms);
        }
    }
}
