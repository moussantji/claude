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
 * Daily quota-based transfer: max 50 requests per host per anime per day.
 * Resumes from where it left off on each re-launch.
 *
 * The command tracks progress in the `transfer_progress` table so it knows
 * exactly which links have been done, which failed, and which are pending.
 *
 * Usage:
 *   # Transfer with daily quota (resumes automatically)
 *   php artisan episodes:daily-transfer "Naruto"
 *
 *   # Specify quota per host
 *   php artisan episodes:daily-transfer "Naruto" --quota=100
 *
 *   # Only VF
 *   php artisan episodes:daily-transfer "Naruto" --lang=VF
 *
 *   # Only a specific season
 *   php artisan episodes:daily-transfer "Naruto" --season=2
 *
 *   # See progress without transferring
 *   php artisan episodes:daily-transfer "Naruto" --status
 *
 *   # Reset failed items to retry them
 *   php artisan episodes:daily-transfer "Naruto" --retry-failed
 *
 *   # Update DB links after transfer
 *   php artisan episodes:daily-transfer "Naruto" --update-db
 *
 * Schedule it in app/Console/Kernel.php:
 *   $schedule->command('episodes:daily-transfer "Naruto" --update-db')->daily();
 */
class EpisodesDailyTransfer extends Command
{
    protected $signature = 'episodes:daily-transfer
        {anime : Anime name (partial match)}
        {--season= : Only this season (1-based)}
        {--lang= : Only this language (VF, VOSTFR, etc.)}
        {--quota=50 : Max transfers per host per day}
        {--update-db : Update episode_links with new URLs}
        {--status : Show progress status only (no transfer)}
        {--retry-failed : Reset failed items to pending so they get retried}
        {--reset : Reset ALL progress for this anime (start over)}';

    protected $description = 'Transfer episodes with a daily quota (50/host/anime/day). Resumes from last position.';

    public function handle(PlayerTransferService $service): int
    {
        $animeName = (string) $this->argument('anime');
        $quota = max(1, (int) $this->option('quota'));
        $lang = (string) $this->option('lang');
        $seasonPos = $this->option('season') !== null ? (int) $this->option('season') : null;
        $updateDb = (bool) $this->option('update-db');
        $statusOnly = (bool) $this->option('status');
        $retryFailed = (bool) $this->option('retry-failed');
        $reset = (bool) $this->option('reset');
        $today = Carbon::today();

        // 1. Find anime
        $anime = Anime::query()
            ->where('name', 'like', "%{$animeName}%")
            ->orWhere('slug', 'like', "%{$animeName}%")
            ->first();

        if (! $anime) {
            $this->error("Anime not found: \"{$animeName}\"");
            $this->newLine();
            Anime::query()->limit(10)->pluck('name')->each(fn ($n) => $this->line("  • {$n}"));

            return self::FAILURE;
        }

        $this->info("◉ {$anime->name}");
        $this->line("  Quota: {$quota} transfers/host/day");
        $this->newLine();

        // 2. Handle --reset
        if ($reset) {
            $deleted = TransferProgress::where('anime_id', $anime->id)->delete();
            $this->warn("Reset: {$deleted} progress record(s) deleted.");
            $this->line('All transfers will start from the beginning on next run.');

            return self::SUCCESS;
        }

        // 3. Handle --retry-failed
        if ($retryFailed) {
            $updated = TransferProgress::where('anime_id', $anime->id)
                ->where('status', 'failed')
                ->update(['status' => 'pending', 'error' => null]);
            $this->info("Reset {$updated} failed item(s) to pending.");

            return self::SUCCESS;
        }

        // 4. Sync progress table — ensure all episode_links have a progress row
        $this->syncProgressTable($anime, $lang, $seasonPos);

        // 5. Get all episode links grouped by host
        $progressQuery = TransferProgress::where('anime_id', $anime->id);
        if ($lang !== '') {
            $progressQuery->whereHas('episodeLink', fn ($q) => $q->where('language', $lang));
        }

        $allProgress = $progressQuery->get();
        $byHost = $allProgress->groupBy('host');

        // 6. Show status
        $this->line('Progress:');
        $this->newLine();

        $headers = ['Host', 'Total', 'Done', 'Failed', 'Pending', 'Today', 'Remaining Today'];
        $rows = [];

        foreach ($byHost as $host => $items) {
            $total = $items->count();
            $done = $items->where('status', 'done')->count();
            $failed = $items->where('status', 'failed')->count();
            $pending = $items->where('status', 'pending')->count();
            $doneToday = $items->where('status', 'done')
                ->where('transferred_at', $today->toDateString())->count();
            $remaining = max(0, $quota - $doneToday);

            $rows[] = [
                $host,
                $total,
                "<fg=green>{$done}</>",
                $failed > 0 ? "<fg=red>{$failed}</>" : '0',
                "<fg=yellow>{$pending}</>",
                $doneToday,
                $pending > 0 ? "<fg=cyan>{$remaining}</>" : '—',
            ];
        }

        $this->table($headers, $rows);

        $totalDone = $allProgress->where('status', 'done')->count();
        $totalAll = $allProgress->count();
        $pct = $totalAll > 0 ? round(($totalDone / $totalAll) * 100, 1) : 0;
        $this->line("Overall: {$totalDone}/{$totalAll} ({$pct}%)");
        $this->newLine();

        if ($statusOnly) {
            return self::SUCCESS;
        }

        // 7. Process transfers (respecting daily quota)
        $transferred = 0;
        $failed = 0;
        $quotaReached = 0;

        foreach ($byHost as $host => $items) {
            $driver = $service->driverForHost($host);
            if (! $driver) {
                continue;
            }

            // How many done today for this host?
            $doneToday = $items->where('status', 'done')
                ->where('transferred_at', $today->toDateString())->count();

            $remaining = max(0, $quota - $doneToday);
            if ($remaining <= 0) {
                $this->line("  <fg=yellow>[{$host}]</> Quota reached for today ({$quota}/{$quota}). Skipping.");
                $quotaReached++;

                continue;
            }

            // Get pending items for this host (in order)
            $pending = $items->where('status', 'pending')->sortBy('id')->take($remaining);
            if ($pending->isEmpty()) {
                $this->line("  <fg=green>[{$host}]</> Nothing pending.");

                continue;
            }

            $credentials = $this->getTargetCredentials($driver->name());
            if (empty($credentials)) {
                $this->warn("  [{$host}] No credentials. Set TRANSFER_".strtoupper($driver->name())."_* in .env");

                continue;
            }

            $this->line("  <fg=cyan>[{$host}]</> Transferring {$pending->count()}/{$remaining} remaining today...");

            $bar = $this->output->createProgressBar($pending->count());
            $bar->setFormat("  %current%/%max% [%bar%] %percent:3s%% %message%");
            $bar->setMessage('');
            $bar->start();

            foreach ($pending as $progress) {
                $link = $progress->episodeLink;
                if (! $link) {
                    $progress->update(['status' => 'failed', 'error' => 'Link deleted']);
                    $bar->advance();

                    continue;
                }

                $episode = $link->episode;
                $season = $episode?->season;

                $label = sprintf('S%02dE%02d [%s]',
                    ($season?->position ?? 0) + 1,
                    $episode?->index ?? 0,
                    $link->language ?? '?'
                );
                $bar->setMessage($label);

                $title = sprintf('%s - S%02dE%02d %s [%s]',
                    $anime->name,
                    ($season?->position ?? 0) + 1,
                    $episode?->index ?? 0,
                    $episode?->name ?? '',
                    $link->language ?? ''
                );

                try {
                    $result = $service->transfer(
                        $link->url,
                        trim($title),
                        $credentials,
                    );

                    $progress->update([
                        'status' => 'done',
                        'new_url' => $result['embed_url'],
                        'new_file_code' => $result['file_code'] ?? null,
                        'transferred_at' => $today,
                        'error' => null,
                    ]);

                    if ($updateDb) {
                        $link->update([
                            'url' => $result['embed_url'],
                            'host' => parse_url($result['embed_url'], PHP_URL_HOST) ?? $link->host,
                        ]);
                    }

                    $transferred++;
                } catch (Throwable $e) {
                    $progress->update([
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ]);
                    $failed++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        // 8. Summary
        $this->newLine();
        if ($transferred > 0 || $failed > 0) {
            $this->info("Session: ✓ {$transferred} transferred, ✗ {$failed} failed");
        }
        if ($quotaReached > 0) {
            $this->line("<fg=yellow>{$quotaReached} host(s) reached their daily quota. Re-run tomorrow.</>");
        }

        $pendingTotal = $allProgress->where('status', 'pending')->count() - $transferred;
        if ($pendingTotal > 0) {
            $this->line("<fg=cyan>{$pendingTotal} link(s) still pending. Re-run later or tomorrow.</>");
        } else {
            $this->info('All links have been processed!');
        }

        if ($updateDb && $transferred > 0) {
            $this->info("Database updated with {$transferred} new URL(s).");
        }

        return self::SUCCESS;
    }

    /**
     * Ensure every episode_link for this anime has a corresponding
     * transfer_progress row. New links get status=pending.
     */
    private function syncProgressTable(Anime $anime, string $lang, ?int $seasonPos): void
    {
        $query = EpisodeLink::query()
            ->whereHas('episode.season', fn ($q) => $q->where('anime_id', $anime->id));

        if ($lang !== '') {
            $query->where('language', $lang);
        }
        if ($seasonPos !== null) {
            $query->whereHas('episode.season', fn ($q) => $q->where('position', $seasonPos - 1));
        }

        $links = $query->get(['id', 'host']);

        $existingIds = TransferProgress::where('anime_id', $anime->id)
            ->pluck('episode_link_id')
            ->flip();

        $toInsert = [];
        $now = now();

        foreach ($links as $link) {
            if ($existingIds->has($link->id)) {
                continue;
            }
            $toInsert[] = [
                'episode_link_id' => $link->id,
                'anime_id' => $anime->id,
                'host' => $link->host ?? 'unknown',
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($toInsert)) {
            // Batch insert
            foreach (array_chunk($toInsert, 500) as $chunk) {
                TransferProgress::insert($chunk);
            }
            $this->line("  Synced: ".count($toInsert)." new link(s) added to progress tracker.");
        }
    }

    private function getTargetCredentials(string $platform): array
    {
        return match ($platform) {
            'vidmoly' => array_filter([
                'api_key' => env('TRANSFER_VIDMOLY_API_KEY', ''),
                'api_base' => env('TRANSFER_VIDMOLY_API_BASE', 'https://api.vidmoly.to'),
                'embed_base' => env('TRANSFER_VIDMOLY_EMBED_BASE', 'https://vidmoly.to'),
                'timeout' => (int) env('TRANSFER_VIDMOLY_TIMEOUT', 900),
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
                'api_base' => env('TRANSFER_DOODSTREAM_API_BASE', 'https://doodapi.com/api'),
            ]),
            'streamtape' => array_filter([
                'login' => env('TRANSFER_STREAMTAPE_LOGIN', ''),
                'api_key' => env('TRANSFER_STREAMTAPE_API_KEY', ''),
                'api_base' => env('TRANSFER_STREAMTAPE_API_BASE', 'https://api.streamtape.com'),
            ]),
            'mp4upload' => array_filter([
                'api_key' => env('TRANSFER_MP4UPLOAD_API_KEY', ''),
                'api_base' => env('TRANSFER_MP4UPLOAD_API_BASE', 'https://www.mp4upload.com/api'),
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
