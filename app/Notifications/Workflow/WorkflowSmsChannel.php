<?php

namespace App\Notifications\Workflow;

use App\Jobs\SendEventSms;
use App\Models\User;
use App\Services\SmsService;

class WorkflowSmsChannel
{
    public function __construct(private SmsService $sms) {}

    public function send(User $user, CustomerWorkflowNotification $notification): void
    {
        (new SendEventSms(workflowAlertId: $notification->alert->id))->handle($this->sms);
    }
}
