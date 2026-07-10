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
 *   # Transfer starting from anime ID 1 (processes all animes sequentially)
 *   php artisan episodes:daily-transfer --from-id=1
 *
 *   # Transfer a specific anime by name
 *   php artisan episodes:daily-transfer "Naruto"
 *
 *   # Transfer a specific anime by ID
 *   php artisan episodes:daily-transfer 42
 *
 *   # Specify quota per host per anime
 *   php artisan episodes:daily-transfer --from-id=1 --quota=100
 *
 *   # Only VF
 *   php artisan episodes:daily-transfer --from-id=1 --lang=VF
 *
 *   # See progress without transferring
 *   php artisan episodes:daily-transfer --from-id=1 --status
 *
 *   # Reset failed items to retry them
 *   php artisan episodes:daily-transfer "Naruto" --retry-failed
 *
 *   # Update DB links after transfer
 *   php artisan episodes:daily-transfer --from-id=1 --update-db
 *
 * Schedule it in app/Console/Kernel.php:
 *   $schedule->command('episodes:daily-transfer --from-id=1 --update-db')->daily();
 */
class EpisodesDailyTransfer extends Command
{
    protected $signature = 'episodes:daily-transfer
        {anime? : Anime name (partial match) or ID}
        {--from-id= : Start from this anime ID and process all animes sequentially}
        {--season= : Only this season (1-based)}
        {--lang= : Only this language (VF, VOSTFR, etc.)}
        {--quota=50 : Max transfers per host per anime per day}
        {--update-db : Update episode_links with new URLs}
        {--status : Show progress status only (no transfer)}
        {--retry-failed : Reset failed items to pending so they get retried}
        {--reset : Reset ALL progress for this anime (start over)}';

    protected $description = 'Transfer episodes with a daily quota (50/host/anime/day). Resumes from last position. Use --from-id=1 to process all animes.';

    public function handle(PlayerTransferService $service): int
    {
        $animeArg = $this->argument('anime');
        $fromId = $this->option('from-id');
        $quota = max(1, (int) $this->option('quota'));
        $lang = (string) $this->option('lang');
        $seasonPos = $this->option('season') !== null ? (int) $this->option('season') : null;
        $updateDb = (bool) $this->option('update-db');
        $statusOnly = (bool) $this->option('status');
        $retryFailed = (bool) $this->option('retry-failed');
        $reset = (bool) $this->option('reset');
        $today = Carbon::today();

        // Determine which animes to process
        $animes = $this->resolveAnimes($animeArg, $fromId);
        if ($animes->isEmpty()) {
            $this->error('No anime found.');

            return self::FAILURE;
        }

        $this->info("═══ Daily Transfer ═══");
        $this->line("  Animes: {$animes->count()} | Quota: {$quota}/host/anime/day");
        $this->newLine();

        $globalTransferred = 0;
        $globalFailed = 0;
        $globalSkipped = 0;

        foreach ($animes as $anime) {
            $result = $this->processAnime($anime, $service, $quota, $lang, $seasonPos, $updateDb, $statusOnly, $retryFailed, $reset, $today);

            $globalTransferred += $result['transferred'];
            $globalFailed += $result['failed'];
            $globalSkipped += $result['skipped'];

            // If all hosts hit quota for this anime, it's done for today — move to next
        }

        // Global summary
        $this->newLine();
        $this->info("═══ DAILY SUMMARY ═══");
        $this->table(
            ['', 'Count'],
            [
                ['Animes processed', $animes->count()],
                ['✓ Transferred', $globalTransferred],
                ['✗ Failed', $globalFailed],
                ['⊘ Quota/skipped', $globalSkipped],
            ]
        );

        if ($updateDb && $globalTransferred > 0) {
            $this->info("Database updated with {$globalTransferred} new URL(s).");
        }

        return self::SUCCESS;
    }

    /**
     * Resolve which animes to process based on arguments.
     */
    private function resolveAnimes(?string $animeArg, ?string $fromId)
    {
        // --from-id=N : all animes starting from ID N
        if ($fromId !== null && $fromId !== '') {
            return Anime::query()
                ->where('id', '>=', (int) $fromId)
                ->orderBy('id')
                ->get();
        }

        // No argument and no --from-id
        if ($animeArg === null || $animeArg === '') {
            $this->error('Provide an anime name/ID or use --from-id=1 to process all.');

            return collect();
        }

        // Numeric argument → find by ID
        if (is_numeric($animeArg)) {
            $anime = Anime::find((int) $animeArg);
            if (! $anime) {
                $this->error("Anime with ID {$animeArg} not found.");

                return collect();
            }

            return collect([$anime]);
        }

        // String argument → search by name
        $anime = Anime::query()
            ->where('name', 'like', "%{$animeArg}%")
            ->orWhere('slug', 'like', "%{$animeArg}%")
            ->first();

        if (! $anime) {
            $this->error("Anime not found: \"{$animeArg}\"");
            $this->newLine();
            Anime::query()->limit(10)->pluck('name')->each(fn ($n) => $this->line("  • {$n}"));

            return collect();
        }

        return collect([$anime]);
    }

    /**
     * Process a single anime: sync progress, respect quota, transfer.
     */
    private function processAnime(
        Anime $anime,
        PlayerTransferService $service,
        int $quota,
        string $lang,
        ?int $seasonPos,
        bool $updateDb,
        bool $statusOnly,
        bool $retryFailed,
        bool $reset,
        Carbon $today,
    ): array {
        $result = ['transferred' => 0, 'failed' => 0, 'skipped' => 0];

        $this->line("──────────────────────────────────────");
        $this->info("◉ [{$anime->id}] {$anime->name}");

        // Handle --reset
        if ($reset) {
            $deleted = TransferProgress::where('anime_id', $anime->id)->delete();
            $this->warn("  Reset: {$deleted} record(s) deleted.");

            return $result;
        }

        // Handle --retry-failed
        if ($retryFailed) {
            $updated = TransferProgress::where('anime_id', $anime->id)
                ->where('status', 'failed')
                ->update(['status' => 'pending', 'error' => null]);
            $this->line("  Reset {$updated} failed item(s) to pending.");

            return $result;
        }

        // Sync progress table
        $this->syncProgressTable($anime, $lang, $seasonPos);

        // Get progress grouped by host
        $progressQuery = TransferProgress::where('anime_id', $anime->id);
        if ($lang !== '') {
            $progressQuery->whereHas('episodeLink', fn ($q) => $q->where('language', $lang));
        }

        $allProgress = $progressQuery->get();
        if ($allProgress->isEmpty()) {
            $this->line("  No links found. Skipping.");

            return $result;
        }

        $byHost = $allProgress->groupBy('host');

        // Quick status line
        $totalDone = $allProgress->where('status', 'done')->count();
        $totalAll = $allProgress->count();
        $pct = $totalAll > 0 ? round(($totalDone / $totalAll) * 100, 1) : 0;
        $this->line("  Progress: {$totalDone}/{$totalAll} ({$pct}%) | Hosts: ".implode(', ', $byHost->keys()->all()));

        if ($statusOnly) {
            // Show detailed table
            foreach ($byHost as $host => $items) {
                $done = $items->where('status', 'done')->count();
                $pending = $items->where('status', 'pending')->count();
                $failed = $items->where('status', 'failed')->count();
                $doneToday = $items->where('status', 'done')->where('transferred_at', $today->toDateString())->count();
                $this->line("    {$host}: ✓{$done} ⏳{$pending} ✗{$failed} (today: {$doneToday}/{$quota})");
            }

            return $result;
        }

        // If everything is done for this anime, skip
        if ($allProgress->where('status', 'pending')->isEmpty()) {
            $this->line("  <fg=green>✓ Complete!</>");

            return $result;
        }

        // Process each host
        foreach ($byHost as $host => $items) {
            $driver = $service->driverForHost($host);
            if (! $driver) {
                continue;
            }

            $doneToday = $items->where('status', 'done')
                ->where('transferred_at', $today->toDateString())->count();

            $remaining = max(0, $quota - $doneToday);
            if ($remaining <= 0) {
                $this->line("  <fg=yellow>[{$host}]</> Quota {$quota}/{$quota} reached. Next run.");
                $result['skipped'] += $items->where('status', 'pending')->count();

                continue;
            }

            $pending = $items->where('status', 'pending')->sortBy('id')->take($remaining);
            if ($pending->isEmpty()) {
                continue;
            }

            $credentials = $this->getTargetCredentials($driver->name());
            if (empty($credentials)) {
                $this->warn("  [{$host}] No credentials configured.");

                continue;
            }

            $this->line("  <fg=cyan>[{$host}]</> {$pending->count()} to transfer (quota: {$doneToday}/{$quota})");

            foreach ($pending as $progress) {
                $link = $progress->episodeLink;
                if (! $link) {
                    $progress->update(['status' => 'failed', 'error' => 'Link deleted']);
                    $result['failed']++;

                    continue;
                }

                $episode = $link->episode;
                $season = $episode?->season;

                $title = sprintf('%s - S%02dE%02d %s [%s]',
                    $anime->name,
                    ($season?->position ?? 0) + 1,
                    $episode?->index ?? 0,
                    $episode?->name ?? '',
                    $link->language ?? ''
                );

                try {
                    $transferResult = $service->transfer(
                        $link->url,
                        trim($title),
                        $credentials,
                    );

                    $progress->update([
                        'status' => 'done',
                        'new_url' => $transferResult['embed_url'],
                        'new_file_code' => $transferResult['file_code'] ?? null,
                        'transferred_at' => $today,
                        'error' => null,
                    ]);

                    if ($updateDb) {
                        $link->update([
                            'url' => $transferResult['embed_url'],
                            'host' => parse_url($transferResult['embed_url'], PHP_URL_HOST) ?? $link->host,
                        ]);
                    }

                    $result['transferred']++;
                    $this->line("    <fg=green>✓</> S".str_pad((string)(($season?->position ?? 0)+1), 2, '0', STR_PAD_LEFT)."E".str_pad((string)($episode?->index ?? 0), 2, '0', STR_PAD_LEFT)." [{$link->language}]");
                } catch (Throwable $e) {
                    $progress->update([
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ]);
                    $result['failed']++;
                    $this->line("    <fg=red>✗</> S".str_pad((string)(($season?->position ?? 0)+1), 2, '0', STR_PAD_LEFT)."E".str_pad((string)($episode?->index ?? 0), 2, '0', STR_PAD_LEFT)." [{$link->language}]: {$e->getMessage()}");
                }
            }
        }

        return $result;
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
