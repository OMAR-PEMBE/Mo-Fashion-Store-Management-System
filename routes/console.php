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

// Sends queued customer messages (WhatsApp receipts, later SMS) from the same cron entry, so no
// separate worker process is needed on shared hosting. A long-running worker can replace it later.
Schedule::command('queue:work --stop-when-empty --max-time=55')->everyMinute()->withoutOverlapping();
