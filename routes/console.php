<?php

use App\Jobs\QueueHeartbeat;
use App\Services\OperationsAlert;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:expire-quotations')
    ->daily()
    ->withoutOverlapping(30);

Schedule::command('app:dispatch-workflow-alerts')->everyMinute()->withoutOverlapping(5);

Schedule::command('app:send-maintenance-reminders')
    ->daily()
    ->withoutOverlapping(30)
    ->onFailure(fn () => app(OperationsAlert::class)->scheduleFailure('app:send-maintenance-reminders'));

Schedule::command('app:send-shop-plan-reminders')
    ->daily()
    ->withoutOverlapping(30)
    ->onFailure(fn () => app(OperationsAlert::class)->scheduleFailure('app:send-shop-plan-reminders'));

Schedule::command('app:sync-shop-subscriptions')->everyMinute()->withoutOverlapping(5)
    ->onFailure(fn () => app(OperationsAlert::class)->scheduleFailure('app:sync-shop-subscriptions'));

Schedule::command('app:reconcile-paymongo-payments')->everyMinute()->withoutOverlapping(5)
    ->onFailure(fn () => app(OperationsAlert::class)->scheduleFailure('app:reconcile-paymongo-payments'));

if (config('monitoring.require_queue_heartbeat') && config('queue.default') !== 'sync') {
    Schedule::job(new QueueHeartbeat)->everyMinute();
}

Schedule::command('app:alert-stale-paymongo-payments')
    ->everyFiveMinutes()
    ->withoutOverlapping(5);

Schedule::command('app:reassign-unanswered-emergency-requests')
    ->everyFiveMinutes()
    ->withoutOverlapping(5);

if (config('backup.enabled')) {
    Schedule::command('app:backup')->dailyAt('02:30')->withoutOverlapping(180);
}
