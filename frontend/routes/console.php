<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep the DB cache warm and tidy so user traffic is served from the database
// and the upstream anime-sama site is only touched on a schedule.
// (Requires the scheduler to be running: `php artisan schedule:work` or a cron
// entry calling `php artisan schedule:run` every minute.)
Schedule::command('anime:cache-refresh')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('anime:cache-prune --days=30')
    ->daily();
