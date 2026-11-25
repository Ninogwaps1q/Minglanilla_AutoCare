<?php

namespace App\Notifications\Workflow;

use App\Models\User;
use App\Services\WebPushDelivery;

class WorkflowPushChannel
{
    public function __construct(private WebPushDelivery $push) {}

    public function send(User $user, CustomerWorkflowNotification $notification): void
    {
        $this->push->send($user, [
            'title' => $notification->title($user),
            'body' => $notification->message($user),
            'url' => $notification->alert->destination(),
            'tag' => 'workflow-'.$notification->alert->id,
        ]);
    }
}
