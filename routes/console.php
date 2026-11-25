<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// cPanel has no long-running workers: one cron entry runs `schedule:run` every minute,
// which drains the database queue (leave emails, password links) and exits.
\Illuminate\Support\Facades\Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();
