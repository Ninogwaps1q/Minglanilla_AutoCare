<?php

namespace App\Services\Messaging;

use App\Jobs\SendConversationAlert;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MessageAlerts
{
    public function sent(Message $message): void
    {
        try {
            $thread = $message->thread;
            $recipient = $message->sender_id === $thread->customer_id ? $thread->shop?->owner : $thread->customer;
            if (! $recipient || $recipient->trashed() || $recipient->status !== 'active') {
                return;
            }

            $locale = $recipient->locale ?? 'en';
            if (! $recipient->notifications()->where('type', 'message_received')->where('data->thread_id', $thread->id)->whereNull('read_at')->exists()) {
                $recipient->notifications()->create([
                    'type' => 'message_received', 'data' => ['thread_id' => $thread->id],
                    'title' => __('extensions.chat.title', [], $locale), 'message' => __('extensions.chat.open', [], $locale),
                ]);
            }
            if (! $this->isViewing($thread->id, $recipient->id)) {
                if ($recipient->receivesEventVia('messages', 'push')) {
                    SendConversationAlert::dispatch($thread->id, $recipient->id, true)->delay(now()->addSeconds(5))->afterCommit();
                }
                if ($recipient->receivesEventVia('messages', 'email') || $recipient->receivesEventVia('messages', 'sms')) {
                    SendConversationAlert::dispatch($thread->id, $recipient->id, false)->delay(now()->addMinutes(2))->afterCommit();
                }
            }
        } catch (Throwable $exception) {
            Log::warning('chat.alert_queue_failed', ['message_id' => $message->id, 'exception_type' => $exception::class]);
        }
    }

    public function viewed(MessageThread $thread, User $viewer): void
    {
        try {
            Cache::put($this->presenceKey($thread->id, $viewer->id), true, 30);
            if (! $thread->messages()->where('sender_id', '!=', $viewer->id)->whereNull('read_at')->exists()) {
                $viewer->notifications()->where('type', 'message_received')
                    ->where('data->thread_id', $thread->id)->whereNull('read_at')->update(['read_at' => now()]);
            }
        } catch (Throwable $exception) {
            Log::warning('chat.presence_failed', ['thread_id' => $thread->id, 'exception_type' => $exception::class]);
        }
    }

    public function isViewing(int $thread, int $viewer): bool
    {
        return Cache::has($this->presenceKey($thread, $viewer));
    }

    private function presenceKey(int $thread, int $viewer): string
    {
        return "chat:presence:{$thread}:{$viewer}";
    }
}
