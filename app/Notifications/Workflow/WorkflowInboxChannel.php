<?php

namespace App\Notifications\Workflow;

use App\Models\User;

class WorkflowInboxChannel
{
    public function send(User $user, CustomerWorkflowNotification $notification): void
    {
        $alert = $notification->alert;
        $user->notifications()->firstOrCreate(['workflow_alert_id' => $alert->id], [
            'type' => $alert->event->inboxType(),
            'title' => $notification->title($user),
            'message' => $notification->message($user),
            'data' => $alert->data,
        ]);
    }
}
