<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sms:send-schedule-reminders')
    // Check throughout the reminder day so the next Windows scheduler run can recover a missed check.
    ->everyMinute()
    ->timezone(config('sms.timezone'))
    ->withoutOverlapping();
