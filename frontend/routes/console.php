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

// Refresh the full catalogue snapshot (used by local search) once a day.
Schedule::command('anime:ingest')
    ->dailyAt('04:00')
    ->withoutOverlapping();

Schedule::command('anime:cache-prune --days=30')
    ->daily();

// =========================================================================
// Episode transfer automation
// =========================================================================
// Runs the all-in-one transfer every night:
//   • reliable hosts (vidmoly/doodstream/streamtape/mp4upload) → same-host backup
//   • unreliable hosts (sendvid/sibnet/myvi) → consolidated onto doodstream (paid)
// Quota: 50 EPISODES per anime per day. Resumes from the last position (--auto),
// auto-advances through animes, and re-tries errors on later runs.
//
// Monitor progress & errors from the admin panel (/admin → "Transferts") or:
//   php artisan episodes:auto --auto --status
// Configure target accounts in .env (TRANSFER_*_*) and verify with:
//   php artisan episodes:check-connections
//
// Requires the scheduler to be running (cron: php artisan schedule:run, or
// `php artisan schedule:work`).
// SERVER-TO-SERVER (remote upload) — best for shared hosting / LWS cPanel.
// The target host (doodstream) fetches each video itself; our server only makes
// light API calls. Respects each host's daily cap (TRANSFER_<HOST>_DAILY_LIMIT),
// resumes from where it stopped (--auto), and finalizes queued jobs each run.
// Runs hourly so queued remote jobs get finalized quickly.
// --spread distributes uploads across ALL your monetizing accounts (fills each
// up to its per-host daily cap) instead of sending everything to one host.
// Hosts without credentials in .env are skipped automatically.
Schedule::command('episodes:remote --spread=doodstream,streamtape,vidmoly,mp4upload --auto --update-db --max-minutes=10')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// (Optional legacy) download+re-upload approach — kept available but not scheduled:
// Schedule::command('episodes:auto --auto --consolidate-to=doodstream --update-db --max-minutes=20')
//     ->dailyAt('02:00')->withoutOverlapping()->runInBackground();

// Daily connection health check (logged) so you notice broken credentials.
Schedule::command('episodes:check-connections')
    ->dailyAt('01:55')
    ->withoutOverlapping();
