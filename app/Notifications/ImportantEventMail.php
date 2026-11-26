<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImportantEventMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $title, public string $message, public string $destination)
    {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return ! ($notifiable instanceof User) || (! $notifiable->trashed() && $notifiable->status === 'active');
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Hello,')
            ->line($this->message)
            ->action('View update', $this->destination)
            ->line('Minglanilla AutoCare');
    }
}
