<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bookings:expire-unpaid')->everyMinute()->withoutOverlapping();
Schedule::command('subscriptions:expire-unpaid')->hourly()->withoutOverlapping();
Schedule::command('subscriptions:expire')->hourly()->withoutOverlapping();
Schedule::command('enrollments:end-scheduled')->hourly()->withoutOverlapping();
Schedule::command('waitlist:expire-offers')->hourly()->withoutOverlapping();
Schedule::command('bookings:send-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('subscriptions:send-expiry-notices')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('sessions:generate')->dailyAt('02:00')->withoutOverlapping();
