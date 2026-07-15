<?php

namespace App\Console\Commands;

use App\Models\Anime;
use App\Models\EpisodeLink;
use App\Models\TransferProgress;
use App\Services\PlayerTransferService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Daily quota-based transfer: max 50 requests per host per anime per day.
 * Automatically advances to the next anime once the current one is fully done.
 * Resumes from where it left off on each re-launch.
 *
 * Progress is tracked in `transfer_progress` table + a cache key for the
 * current anime ID so we know where we left off.
 *
 * Usage:
 *   # Start from ID 1, processes sequentially, auto-advances
 *   php artisan episodes:daily-transfer --from-id=1 --update-db
 *
 *   # Resume from last position (uses cached current ID)
 *   php artisan episodes:daily-transfer --auto --update-db
 *
 *   # See full progress report
 *   php artisan episodes:daily-transfer --from-id=1 --status
 *
 * Scheduling (runs daily, resumes automatically):
 *   $schedule->command('episodes:daily-transfer --auto --update-db')->dailyAt('02:00');
 */
class EpisodesDailyTransfer extends Command
{
    protected $signature = 'episodes:daily-transfer
        {anime? : Anime name (partial match) or ID}
        {--from-id= : Start from this anime ID and process sequentially}
        {--auto : Resume from last saved position (cached)}
        {--season= : Only this season (1-based)}
        {--lang= : Only this language (VF, VOSTFR, etc.)}
        {--quota=50 : Max transfers per host per anime per day}
        {--max-failures=3 : Skip a link after this many failures}
        {--update-db : Update episode_links with new URLs}
        {--status : Show progress status only (no transfer)}
        {--retry-failed : Reset failed items to pending}
        {--reset : Reset ALL progress (start over)}';

    protected $description = 'Transfer episodes with daily quota. Auto-advances through animes. Resumes from last position.';

    private const CACHE_KEY = 'episodes_daily_transfer_current_id';

    public function handle(PlayerTransferService $service): int
    {
        $animeArg = $this->argument('anime');
        $fromId = $this->option('from-id');
        $auto = (bool) $this->option('auto');
        $quota = max(1, (int) $this->option('quota'));
        $maxFailures = max(1, (int) $this->option('max-failures'));
        $lang = (string) $this->option('lang');
        $seasonPos = $this->option('season') !== null ? (int) $this->option('season') : null;
        $updateDb = (bool) $this->option('update-db');
        $statusOnly = (bool) $this->option('status');
        $retryFailed = (bool) $this->option('retry-failed');
        $reset = (bool) $this->option('reset');
        $today = Carbon::today();

        // Resolve starting point
        $startId = $this->resolveStartId($animeArg, $fromId, $auto);
        if ($startId === null) {
            return self::FAILURE;
        }

        // Get all animes from startId onwards
        $animes = Anime::query()->where('id', '>=', $startId)->orderBy('id')->get();
        if ($animes->isEmpty()) {
            $this->error("No animes found starting from ID {$startId}.");

            return self::FAILURE;
        }

        $this->info("═══ Daily Transfer ═══");
        $this->line("  Starting from: ID {$startId} | Animes: {$animes->count()} | Quota: {$quota}/host/anime/day");
        $this->newLine();

        $globalTransferred = 0;
        $globalFailed = 0;
        $globalSkipped = 0;
        $emptyAnimes = [];
        $failedAnimes = [];
        $completedAnimes = [];

        foreach ($animes as $anime) {
            $result = $this->processAnime(
                $anime, $service, $quota, $maxFailures, $lang, $seasonPos,
                $updateDb, $statusOnly, $retryFailed, $reset, $today
            );

            $globalTransferred += $result['transferred'];
            $globalFailed += $result['failed'];
            $globalSkipped += $result['skipped'];

            if ($result['empty']) {
                $emptyAnimes[] = $anime;
            }
            if ($result['has_failures']) {
                $failedAnimes[] = $anime;
            }

            // Check if this anime is fully complete → auto-advance
            if ($result['complete'] && !$statusOnly && !$reset && !$retryFailed) {
                $completedAnimes[] = $anime;
                // Save next ID for --auto resume
                $nextId = $anime->id + 1;
                Cache::forever(self::CACHE_KEY, $nextId);
            } elseif (!$result['complete'] && !$statusOnly) {
                // Not complete yet — save this ID as current position
                Cache::forever(self::CACHE_KEY, $anime->id);
            }
        }

        // ══════ Summary ══════
        $this->newLine();
        $this->info("═══ DAILY SUMMARY ═══");
        $this->table(
            ['', 'Count'],
            [
                ['Animes processed', $animes->count()],
                ['Animes completed', count($completedAnimes)],
                ['✓ Transferred', $globalTransferred],
                ['✗ Failed', $globalFailed],
                ['⊘ Skipped (quota/no creds)', $globalSkipped],
            ]
        );

        // Report animes with no episodes
        if (!empty($emptyAnimes)) {
            $this->newLine();
            $this->warn("⚠ Animes without episodes (".count($emptyAnimes)."):");
            foreach ($emptyAnimes as $a) {
                $this->line("  <fg=yellow>[{$a->id}]</> {$a->name}");
            }
        }

        // Report animes with failures
        if (!empty($failedAnimes)) {
            $this->newLine();
            $this->warn("⚠ Animes with transfer failures (".count($failedAnimes)."):");
            foreach ($failedAnimes as $a) {
                $failCount = TransferProgress::where('anime_id', $a->id)->where('status', 'failed')->count();
                $this->line("  <fg=red>[{$a->id}]</> {$a->name} — {$failCount} failed link(s)");
            }
            $this->line("  Use --retry-failed to retry them.");
        }

        // Show current position
        $currentId = Cache::get(self::CACHE_KEY, $startId);
        $this->newLine();
        $this->line("📍 Current position: anime ID {$currentId}");
        $this->line("   Next run will resume from here (use --auto).");

        if ($updateDb && $globalTransferred > 0) {
            $this->newLine();
            $this->info("✓ Database updated with {$globalTransferred} new URL(s).");
        }

        return self::SUCCESS;
    }

    /**
     * Determine the starting anime ID.
     */
    private function resolveStartId(?string $animeArg, ?string $fromId, bool $auto): ?int
    {
        // --auto: resume from cached position
        if ($auto) {
            $cached = Cache::get(self::CACHE_KEY);
            if ($cached) {
                $this->line("Resuming from cached position: ID {$cached}");

                return (int) $cached;
            }
            // No cached position, start from 1
            $this->line("No previous position found. Starting from ID 1.");

            return 1;
        }

        // --from-id=N
        if ($fromId !== null && $fromId !== '') {
            $id = (int) $fromId;
            Cache::forever(self::CACHE_KEY, $id);

            return $id;
        }

        // Specific anime by argument
        if ($animeArg !== null && $animeArg !== '') {
            if (is_numeric($animeArg)) {
                $anime = Anime::find((int) $animeArg);
                if (!$anime) {
                    $this->error("Anime ID {$animeArg} not found.");

                    return null;
                }

                return $anime->id;
            }

            $anime = Anime::query()
                ->where('name', 'like', "%{$animeArg}%")
                ->orWhere('slug', 'like', "%{$animeArg}%")
                ->first();

            if (!$anime) {
                $this->error("Anime not found: \"{$animeArg}\"");

                return null;
            }

            return $anime->id;
        }

        $this->error('Provide an anime name/ID, --from-id=N, or --auto to resume.');

        return null;
    }

    /**
     * Process a single anime. Returns stats.
     */
    private function processAnime(
        Anime $anime,
        PlayerTransferService $service,
        int $quota,
        int $maxFailures,
        string $lang,
        ?int $seasonPos,
        bool $updateDb,
        bool $statusOnly,
        bool $retryFailed,
        bool $reset,
        Carbon $today,
    ): array {
        $result = [
            'transferred' => 0,
            'failed' => 0,
            'skipped' => 0,
            'empty' => false,
            'complete' => false,
            'has_failures' => false,
        ];

        $this->line("──────────────────────────────────────");
        $this->info("◉ [{$anime->id}] {$anime->name}");

        // Handle --reset
        if ($reset) {
            $deleted = TransferProgress::where('anime_id', $anime->id)->delete();
            if ($deleted) {
                $this->warn("  Reset: {$deleted} record(s) deleted.");
            }

            return $result;
        }

        // Handle --retry-failed
        if ($retryFailed) {
            $updated = TransferProgress::where('anime_id', $anime->id)
                ->where('status', 'failed')
                ->update(['status' => 'pending', 'error' => null]);
            if ($updated) {
                $this->line("  Reset {$updated} failed item(s) to pending.");
            }

            return $result;
        }

        // Check if anime has any episodes at all
        $hasEpisodes = EpisodeLink::query()
            ->whereHas('episode.season', fn ($q) => $q->where('anime_id', $anime->id))
            ->exists();

        if (!$hasEpisodes) {
            $this->line("  <fg=yellow>⚠ No episodes/links found. Skipping.</>");
            $result['empty'] = true;
            $result['complete'] = true; // Nothing to do → consider it done

            return $result;
        }

        // Sync progress table
        $this->syncProgressTable($anime, $lang, $seasonPos);

        // Get progress
        $progressQuery = TransferProgress::where('anime_id', $anime->id);
        if ($lang !== '') {
            $progressQuery->whereHas('episodeLink', fn ($q) => $q->where('language', $lang));
        }
        $allProgress = $progressQuery->get();

        if ($allProgress->isEmpty()) {
            $this->line("  <fg=yellow>⚠ No links match filters. Skipping.</>");
            $result['empty'] = true;
            $result['complete'] = true;

            return $result;
        }

        $byHost = $allProgress->groupBy('host');
        $totalDone = $allProgress->where('status', 'done')->count();
        $totalFailed = $allProgress->where('status', 'failed')->count();
        $totalPending = $allProgress->where('status', 'pending')->count();
        $totalAll = $allProgress->count();
        $pct = $totalAll > 0 ? round(($totalDone / $totalAll) * 100, 1) : 0;

        $this->line("  Progress: {$totalDone}/{$totalAll} ({$pct}%) | Pending: {$totalPending} | Failed: {$totalFailed}");

        // Status only mode
        if ($statusOnly) {
            foreach ($byHost as $host => $items) {
                $done = $items->where('status', 'done')->count();
                $pending = $items->where('status', 'pending')->count();
                $failed = $items->where('status', 'failed')->count();
                $doneToday = $items->where('status', 'done')->where('transferred_at', $today->toDateString())->count();
                $this->line("    {$host}: ✓{$done} ⏳{$pending} ✗{$failed} (today: {$doneToday}/{$quota})");
            }

            $result['complete'] = ($totalPending === 0);

            return $result;
        }

        // Already complete?
        if ($totalPending === 0) {
            $this->line("  <fg=green>✓ Complete!</>");
            $result['complete'] = true;
            if ($totalFailed > 0) {
                $result['has_failures'] = true;
            }

            return $result;
        }

        // Process each host
        foreach ($byHost as $host => $items) {
            $driver = $service->driverForHost($host);
            if (!$driver) {
                $this->line("  <fg=gray>[{$host}]</> No driver available. Skipping.");

                continue;
            }

            $doneToday = $items->where('status', 'done')
                ->where('transferred_at', $today->toDateString())->count();

            $remaining = max(0, $quota - $doneToday);
            if ($remaining <= 0) {
                $pendingCount = $items->where('status', 'pending')->count();
                if ($pendingCount > 0) {
                    $this->line("  <fg=yellow>[{$host}]</> Quota {$quota}/{$quota} atteint. Reprendra demain.");
                    $result['skipped'] += $pendingCount;
                }

                continue;
            }

            $pending = $items->where('status', 'pending')->sortBy('id')->take($remaining);
            if ($pending->isEmpty()) {
                continue;
            }

            $credentials = $this->getTargetCredentials($driver->name());
            if (empty($credentials)) {
                $this->warn("  [{$host}] Pas de credentials. Configurer TRANSFER_".strtoupper($driver->name())."_* dans .env");
                $result['skipped'] += $pending->count();

                continue;
            }

            $this->line("  <fg=cyan>[{$host}]</> {$pending->count()} transfert(s) (aujourd'hui: {$doneToday}/{$quota})");

            $consecutiveFailures = 0;

            foreach ($pending as $progress) {
                // If too many consecutive failures on this host, stop to avoid rate-limiting
                if ($consecutiveFailures >= $maxFailures) {
                    $this->line("    <fg=red>⊘</> {$maxFailures} échecs consécutifs sur {$host}. Arrêt pour ce host.");
                    break;
                }

                $link = $progress->episodeLink;
                if (!$link) {
                    $progress->update(['status' => 'failed', 'error' => 'Link supprimé de la DB']);
                    $result['failed']++;

                    continue;
                }

                $episode = $link->episode;
                $season = $episode?->season;

                $epLabel = "S".str_pad((string)(($season?->position ?? 0)+1), 2, '0', STR_PAD_LEFT)
                    ."E".str_pad((string)($episode?->index ?? 0), 2, '0', STR_PAD_LEFT)
                    ." [{$link->language}]";

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
                    $consecutiveFailures = 0; // Reset on success
                    $this->line("    <fg=green>✓</> {$epLabel}");
                } catch (Throwable $e) {
                    $progress->update([
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ]);
                    $result['failed']++;
                    $consecutiveFailures++;
                    $result['has_failures'] = true;
                    $this->line("    <fg=red>✗</> {$epLabel}: ".substr($e->getMessage(), 0, 80));
                }
            }
        }

        // Check if fully complete now
        $remainingPending = TransferProgress::where('anime_id', $anime->id)
            ->where('status', 'pending')->count();
        $result['complete'] = ($remainingPending === 0);

        if ($result['complete']) {
            $this->line("  <fg=green>✓ Anime terminé !</>");
        }

        return $result;
    }

    /**
     * Ensure every episode_link for this anime has a progress row.
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

        if (!empty($toInsert)) {
            foreach (array_chunk($toInsert, 500) as $chunk) {
                TransferProgress::insert($chunk);
            }
            $this->line("  Synced: ".count($toInsert)." nouveau(x) lien(s) ajouté(s).");
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
                'api_base' => env('TRANSFER_DOODSTREAM_API_BASE', 'https://doodapi.co/api'),
            ]),
            'streamtape' => array_filter([
                'login' => env('TRANSFER_STREAMTAPE_LOGIN', ''),
                'api_key' => env('TRANSFER_STREAMTAPE_API_KEY', ''),
                'api_base' => env('TRANSFER_STREAMTAPE_API_BASE', 'https://api.streamtape.com'),
            ]),
            'filemoon' => array_filter([
                'api_key' => env('TRANSFER_FILEMOON_API_KEY', ''),
                'api_base' => env('TRANSFER_FILEMOON_API_BASE', 'https://filemoonapi.com/api'),
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
