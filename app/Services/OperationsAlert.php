<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OperationsAlert
{
    public function scheduleFailure(string $command): void
    {
        Log::channel('operations')->error('schedule.failed', ['command' => $command]);
        $recipient = config('monitoring.alert_email');
        if (! $recipient) {
            return;
        }

        try {
            if (! Cache::add('operations.schedule_failure.'.$command, true, now()->addHour())) {
                return;
            }
            Mail::raw("Scheduled task failed: {$command}. Check the AutoCare operations log.", function ($message) use ($recipient): void {
                $message->to($recipient)->subject('AutoCare scheduled task failed');
            });
        } catch (Throwable $exception) {
            Log::channel('operations')->warning('schedule.alert_failed', ['command' => $command, 'exception_type' => $exception::class]);
        }
    }
}
