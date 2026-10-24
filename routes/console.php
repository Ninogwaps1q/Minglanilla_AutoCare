<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:expire-quotations')
    ->daily()
    ->withoutOverlapping(30);

Schedule::command('app:send-maintenance-reminders')
    ->daily()
    ->withoutOverlapping(30);

Schedule::command('app:reassign-unanswered-emergency-requests')
    ->everyFiveMinutes()
    ->withoutOverlapping(5);

if (config('backup.enabled')) {
    Schedule::command('app:backup')->dailyAt('02:30')->withoutOverlapping(180);
}
