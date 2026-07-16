<?php

namespace App\Console\Commands;

use App\Models\Anime;
use App\Models\EpisodeLink;
use App\Models\TransferProgress;
use App\Services\PlayerTransferService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Simple daily quota transfer: N successful transfers PER DAY (global),
 * resumes automatically each day, and handles errors with auto-retry.
 *
 * • Daily cap is GLOBAL (default 50/day across all animes & hosts).
 * • Re-run it any time / every day — it counts what was already done TODAY
 *   and only does the remaining amount. The count resets at midnight.
 * • Failed transfers are retried automatically on later runs, up to
 *   --max-attempts times, then marked as permanently failed.
 * • Unreliable hosts (sendvid/sibnet/myvi) are consolidated onto --consolidate-to.
 *
 * Examples:
 *   php artisan episodes:quota
 *   php artisan episodes:quota --limit=50 --consolidate-to=doodstream --update-db
 *   php artisan episodes:quota --status
 *   php artisan episodes:quota --reset-failed        # re-arm permanently failed items
 *
 * Schedule (once a day is enough; safe to run more often — it respects the cap):
 *   Schedule::command('episodes:quota --consolidate-to=doodstream --update-db')->dailyAt('02:00');
 */
class EpisodesQuota extends Command
{
    private const UNRELIABLE = ['sendvid', 'sibnet', 'myvi'];

    protected $signature = 'episodes:quota
        {--limit=50 : Successful transfers allowed per day (global)}
        {--consolidate-to= : Target monetizing host for unreliable sources (e.g. doodstream)}
        {--lang= : Only this language}
        {--max-attempts=3 : Retry a failed link this many times before giving up}
        {--update-db : Update episode_links with the new URLs}
        {--status : Show progress and totals for today, then exit}
        {--reset-failed : Re-arm permanently failed items (attempts back to 0)}';

    protected $description = 'Do N transfers per day (global), resume daily, auto-retry errors.';

    public function handle(PlayerTransferService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $maxAttempts = max(1, (int) $this->option('max-attempts'));
        $consolidateTo = strtolower((string) $this->option('consolidate-to'));
        $lang = (string) $this->option('lang');
        $updateDb = (bool) $this->option('update-db');
        $today = Carbon::today();

        if ($consolidateTo !== '' && ! $service->driverByName($consolidateTo)) {
            $this->error("Unknown consolidate target: {$consolidateTo}");

            return self::FAILURE;
        }

        // Re-arm permanently failed items
        if ($this->option('reset-failed')) {
            $n = TransferProgress::where('status', 'failed')
                ->update(['status' => 'pending', 'attempts' => 0, 'error' => null]);
            $this->info("Re-armed {$n} failed item(s) → pending.");

            return self::SUCCESS;
        }

        // Make sure every link is tracked
        $this->syncAllProgress($lang);

        // How many succeeded TODAY already? (this is what makes it resumable daily)
        $doneToday = TransferProgress::where('status', 'done')
            ->whereDate('transferred_at', $today->toDateString())
            ->count();

        $remaining = max(0, $limit - $doneToday);

        // Global stats
        $totalAll = TransferProgress::count();
        $totalDone = TransferProgress::where('status', 'done')->count();
        $totalPending = TransferProgress::where('status', 'pending')->count();
        $totalFailed = TransferProgress::where('status', 'failed')->count();
        $pct = $totalAll > 0 ? round(($totalDone / $totalAll) * 100, 1) : 0;

        $this->info('═══ Daily Quota Transfer ═══');
        $this->line("  Cap: {$limit}/day | Done today: {$doneToday} | Remaining today: {$remaining}");
        $this->line("  Overall: {$totalDone}/{$totalAll} ({$pct}%) | Pending: {$totalPending} | Failed: {$totalFailed}");
        $this->newLine();

        if ($this->option('status')) {
            return self::SUCCESS;
        }

        if ($remaining <= 0) {
            $this->line("<fg=yellow>Daily cap reached ({$doneToday}/{$limit}). Come back tomorrow.</>");

            return self::SUCCESS;
        }

        // Build the work queue: pending first, then retryable failed
        // (failed with attempts < max), ordered by anime then link id.
        $queue = TransferProgress::with('episodeLink.episode.season.anime')
            ->where(function ($q) use ($maxAttempts) {
                $q->where('status', 'pending')
                  ->orWhere(function ($q2) use ($maxAttempts) {
                      $q2->where('status', 'failed')->where('attempts', '<', $maxAttempts);
                  });
            })
            ->when($lang !== '', fn ($q) => $q->whereHas('episodeLink', fn ($l) => $l->where('language', $lang)))
            ->orderByRaw("FIELD(status,'pending','failed')")
            ->orderBy('anime_id')
            ->orderBy('id')
            ->limit($remaining * 3) // headroom in case some fail
            ->get();

        if ($queue->isEmpty()) {
            $this->info('Nothing left to transfer. All done! 🎉');

            return self::SUCCESS;
        }

        $ok = 0;
        $failed = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($remaining);
        $bar->setFormat("  %current%/%max% [%bar%] %percent:3s%% %message%");
        $bar->setMessage('');
        $bar->start();

        foreach ($queue as $progress) {
            if ($ok >= $remaining) {
                break; // daily cap for successes reached
            }

            $link = $progress->episodeLink;
            if (! $link) {
                $progress->update(['status' => 'failed', 'error' => 'Link deleted', 'attempts' => $maxAttempts]);
                continue;
            }

            $host = $progress->host ?? $link->host ?? '';
            $isUnreliable = $this->isUnreliable($host);
            $targetPlatform = $isUnreliable ? ($consolidateTo ?: null) : null;

            // Unreliable host but no consolidation target → skip
            if ($isUnreliable && $targetPlatform === null) {
                $skipped++;
                continue;
            }

            $sourceDriver = $service->driverForHost($host);
            if (! $sourceDriver) {
                $skipped++;
                continue;
            }

            $credPlatform = $targetPlatform ?? $sourceDriver->name();
            $credentials = $this->getTargetCredentials($credPlatform);
            if (empty($credentials)) {
                $skipped++;
                continue;
            }

            $episode = $link->episode;
            $season = $episode?->season;
            $anime = $season?->anime;

            $label = sprintf('%s S%02dE%02d [%s]',
                $anime?->name ?? '?',
                ($season?->position ?? 0) + 1,
                $episode?->index ?? 0,
                $link->language ?? '?'
            );
            $bar->setMessage($label);

            $title = sprintf('%s - S%02dE%02d %s [%s]',
                $anime?->name ?? 'Episode',
                ($season?->position ?? 0) + 1,
                $episode?->index ?? 0,
                $episode?->name ?? '',
                $link->language ?? ''
            );

            try {
                $tr = $service->transfer(trim($link->url), trim($title), $credentials, null, $targetPlatform);
                $newHost = $tr['host'] ?? ($targetPlatform ?: $host);

                $progress->update([
                    'status' => 'done',
                    'attempts' => $progress->attempts + 1,
                    'new_url' => $tr['embed_url'],
                    'new_file_code' => $tr['file_code'] ?? null,
                    'transferred_at' => $today,
                    'last_attempt_at' => now(),
                    'error' => null,
                ]);

                if ($updateDb) {
                    $link->update(['url' => $tr['embed_url'], 'host' => $newHost]);
                }

                $ok++;
                $bar->advance();
            } catch (Throwable $e) {
                $attempts = $progress->attempts + 1;
                $progress->update([
                    'status' => 'failed',
                    'attempts' => $attempts,
                    'error' => $e->getMessage(),
                    'last_attempt_at' => now(),
                ]);
                $failed++;
                $this->newLine();
                $perm = $attempts >= $maxAttempts ? ' <fg=red>(permanent)</>' : " (retry {$attempts}/{$maxAttempts})";
                $this->line("  <fg=red>✗</> {$label}: ".substr($e->getMessage(), 0, 60).$perm);
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Result', 'Count'], [
            ['✓ Transferred today', $ok],
            ['✗ Failed this run', $failed],
            ['⊘ Skipped (no driver/creds)', $skipped],
            ['Daily cap', $limit],
        ]);

        $stillPending = TransferProgress::where('status', 'pending')->count();
        $permFailed = TransferProgress::where('status', 'failed')
            ->where('attempts', '>=', $maxAttempts)->count();

        if ($ok >= $remaining) {
            $this->line("<fg=yellow>Daily cap reached. Re-run tomorrow to continue.</>");
        }
        if ($stillPending > 0) {
            $this->line("<fg=cyan>{$stillPending} link(s) still pending.</>");
        }
        if ($permFailed > 0) {
            $this->warn("{$permFailed} link(s) permanently failed after {$maxAttempts} attempts. Use --reset-failed to retry them.");
        }
        if ($stillPending === 0 && $permFailed === 0 && TransferProgress::where('status', 'failed')->count() === 0) {
            $this->info('Everything transferred! 🎉');
        }

        return self::SUCCESS;
    }

    private function isUnreliable(string $host): bool
    {
        foreach (self::UNRELIABLE as $u) {
            if (str_contains($host, $u)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure every episode_link has a progress row (across all animes).
     */
    private function syncAllProgress(string $lang): void
    {
        $existing = TransferProgress::pluck('episode_link_id')->flip();

        $query = EpisodeLink::query();
        if ($lang !== '') {
            $query->where('language', $lang);
        }

        $toInsert = [];
        $now = now();

        $query->select('episode_links.id', 'episode_links.host', 'seasons.anime_id')
            ->join('episodes', 'episodes.id', '=', 'episode_links.episode_id')
            ->join('seasons', 'seasons.id', '=', 'episodes.season_id')
            ->orderBy('seasons.anime_id')
            ->chunk(1000, function ($links) use (&$toInsert, $existing, $now) {
                foreach ($links as $link) {
                    if ($existing->has($link->id)) {
                        continue;
                    }
                    $toInsert[] = [
                        'episode_link_id' => $link->id,
                        'anime_id' => $link->anime_id,
                        'host' => $link->host ?? 'unknown',
                        'status' => 'pending',
                        'attempts' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    if (count($toInsert) >= 500) {
                        TransferProgress::insert($toInsert);
                        $toInsert = [];
                    }
                }
            });

        if (! empty($toInsert)) {
            TransferProgress::insert($toInsert);
        }
    }

    private function getTargetCredentials(string $platform): array
    {
        return match ($platform) {
            'streamwish' => array_filter([
                'api_key' => env('TRANSFER_STREAMWISH_API_KEY', ''),
                'api_base' => env('TRANSFER_STREAMWISH_API_BASE', 'https://api.streamwish.com/api'),
                'embed_base' => env('TRANSFER_STREAMWISH_EMBED_BASE', 'https://streamwish.to'),
                'timeout' => (int) env('TRANSFER_STREAMWISH_TIMEOUT', 900),
            ]),
            'vidhide' => array_filter([
                'api_key' => env('TRANSFER_VIDHIDE_API_KEY', ''),
                'api_base' => env('TRANSFER_VIDHIDE_API_BASE', 'https://api.filelions.com/api'),
                'embed_base' => env('TRANSFER_VIDHIDE_EMBED_BASE', 'https://vidhide.com'),
                'timeout' => (int) env('TRANSFER_VIDHIDE_TIMEOUT', 900),
            ]),
            'sibnet' => array_filter([
                'login' => env('TRANSFER_SIBNET_LOGIN', ''),
                'password' => env('TRANSFER_SIBNET_PASSWORD', ''),
            ]),
            'sendvid' => array_filter([
                'api_key' => env('TRANSFER_SENDVID_API_KEY', ''),
                'api_base' => env('TRANSFER_SENDVID_API_BASE', 'https://sendvid.com/api'),
            ]),
            'doodstream' => array_filter([
                'api_key' => env('TRANSFER_DOODSTREAM_API_KEY', ''),
                'api_base' => env('TRANSFER_DOODSTREAM_API_BASE', 'https://doodapi.co/api'),
            ]),
            'streamtape' => array_filter([
                'login' => env('TRANSFER_STREAMTAPE_LOGIN', ''),
                'api_key' => env('TRANSFER_STREAMTAPE_API_KEY', ''),
                'api_base' => env('TRANSFER_STREAMTAPE_API_BASE', 'https://api.streamtape.com'),
            ]),
            'myvi' => array_filter([
                'login' => env('TRANSFER_MYVI_LOGIN', ''),
                'password' => env('TRANSFER_MYVI_PASSWORD', ''),
                'api_base' => env('TRANSFER_MYVI_API_BASE', 'https://myvi.tv'),
            ]),
            'dailymotion' => array_filter([
                'api_key' => env('TRANSFER_DAILYMOTION_API_KEY', ''),
                'api_secret' => env('TRANSFER_DAILYMOTION_API_SECRET', ''),
                'username' => env('TRANSFER_DAILYMOTION_USERNAME', ''),
                'password' => env('TRANSFER_DAILYMOTION_PASSWORD', ''),
            ]),
            default => [],
        };
    }
}
