<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires the server cron entry: * * * * * php artisan schedule:run
Schedule::command('app:backup-database')->dailyAt(config('backup.daily_at'))->withoutOverlapping();
Schedule::command('app:verify-backup')->weeklyOn(0, config('backup.verify_weekly_at'))->withoutOverlapping();
