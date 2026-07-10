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
 * ALL-IN-ONE automation: combines daily-transfer + consolidate.
 *
 * For each anime (starting from an ID, auto-advancing, resuming daily):
 *   • Reliable hosts (vidmoly, doodstream, streamtape, mp4upload)
 *       → same-host transfer to your target account (backup / migrate)
 *   • Unreliable hosts (sendvid, sibnet, myvi)
 *       → consolidated (cross-host) onto a monetizing host (--consolidate-to)
 *
 * Respects a daily quota per source host per anime, tracks progress in the
 * `transfer_progress` table, and resumes exactly where it left off.
 *
 * Examples:
 *   # Fully automatic: reliable→same, unreliable→doodstream, from ID 1
 *   php artisan episodes:auto --from-id=1 --consolidate-to=doodstream --update-db
 *
 *   # Resume from last position (for the scheduler)
 *   php artisan episodes:auto --auto --consolidate-to=doodstream --update-db
 *
 *   # Status report
 *   php artisan episodes:auto --auto --status
 *
 * Schedule (runs daily, resumes automatically):
 *   $schedule->command('episodes:auto --auto --consolidate-to=doodstream --update-db')->dailyAt('02:00');
 */
class EpisodesAuto extends Command
{
    private const UNRELIABLE = ['sendvid', 'sibnet', 'myvi'];
    private const MONETIZING = ['doodstream', 'vidmoly', 'streamtape', 'mp4upload'];
    private const CACHE_KEY = 'episodes_auto_current_id';

    protected $signature = 'episodes:auto
        {--from-id= : Start from this anime ID and process sequentially}
        {--auto : Resume from last saved position (cached)}
        {--consolidate-to= : Target monetizing host for unreliable sources (doodstream, vidmoly, ...)}
        {--unreliable= : Override unreliable source hosts (comma-separated)}
        {--lang= : Only this language}
        {--quota=50 : Max transfers per source host per anime per day}
        {--max-failures=3 : Stop a host after this many consecutive failures}
        {--add-link : For consolidation, add a new link instead of replacing}
        {--update-db : Update episode_links with new URLs}
        {--status : Show progress report only (no transfer)}
        {--retry-failed : Reset failed items to pending}
        {--reset : Reset ALL progress (start over)}';

    protected $description = 'All-in-one: same-host transfer for reliable hosts + consolidation for unreliable ones. Daily quota, auto-resume.';

    public function handle(PlayerTransferService $service): int
    {
        $fromId = $this->option('from-id');
        $auto = (bool) $this->option('auto');
        $consolidateTo = strtolower((string) $this->option('consolidate-to'));
        $quota = max(1, (int) $this->option('quota'));
        $maxFailures = max(1, (int) $this->option('max-failures'));
        $lang = (string) $this->option('lang');
        $addLink = (bool) $this->option('add-link');
        $updateDb = (bool) $this->option('update-db');
        $statusOnly = (bool) $this->option('status');
        $retryFailed = (bool) $this->option('retry-failed');
        $reset = (bool) $this->option('reset');
        $today = Carbon::today();

        $unreliable = $this->option('unreliable')
            ? array_filter(array_map('trim', explode(',', strtolower((string) $this->option('unreliable')))))
            : self::UNRELIABLE;

        // Validate consolidation target
        if ($consolidateTo !== '') {
            if (! $service->driverByName($consolidateTo)) {
                $this->error("Unknown consolidate target: {$consolidateTo}");

                return self::FAILURE;
            }
            if (! in_array($consolidateTo, self::MONETIZING, true)) {
                $this->warn("⚠ '{$consolidateTo}' is not a known monetizing host.");
            }
        }

        // Resolve starting ID
        $startId = $this->resolveStartId($fromId, $auto);
        if ($startId === null) {
            return self::FAILURE;
        }

        $animes = Anime::query()->where('id', '>=', $startId)->orderBy('id')->get();
        if ($animes->isEmpty()) {
            $this->error("No animes found from ID {$startId}.");

            return self::FAILURE;
        }

        $this->info('═══ Episodes Auto ═══');
        $this->line("  From ID: {$startId} | Animes: {$animes->count()} | Quota: {$quota}/host/anime/day");
        $this->line('  Reliable → same-host | Unreliable ('.implode(',', $unreliable).') → '.($consolidateTo ?: '(skipped: no --consolidate-to)'));
        $this->newLine();

        $totals = ['transferred' => 0, 'consolidated' => 0, 'failed' => 0, 'skipped' => 0];
        $emptyAnimes = [];
        $failedAnimes = [];
        $completed = 0;

        foreach ($animes as $anime) {
            $r = $this->processAnime(
                $anime, $service, $consolidateTo, $unreliable, $quota, $maxFailures,
                $lang, $addLink, $updateDb, $statusOnly, $retryFailed, $reset, $today
            );

            $totals['transferred'] += $r['transferred'];
            $totals['consolidated'] += $r['consolidated'];
            $totals['failed'] += $r['failed'];
            $totals['skipped'] += $r['skipped'];

            if ($r['empty']) {
                $emptyAnimes[] = $anime;
            }
            if ($r['has_failures']) {
                $failedAnimes[] = $anime;
            }

            if ($r['complete'] && ! $statusOnly && ! $reset && ! $retryFailed) {
                $completed++;
                Cache::forever(self::CACHE_KEY, $anime->id + 1);
            } elseif (! $r['complete'] && ! $statusOnly) {
                Cache::forever(self::CACHE_KEY, $anime->id);
            }
        }

        $this->renderSummary($totals, $completed, $animes->count(), $emptyAnimes, $failedAnimes, $startId);

        return self::SUCCESS;
    }

    private function resolveStartId(?string $fromId, bool $auto): ?int
    {
        if ($fromId !== null && $fromId !== '') {
            $id = (int) $fromId;
            Cache::forever(self::CACHE_KEY, $id);

            return $id;
        }

        if ($auto) {
            $cached = Cache::get(self::CACHE_KEY);
            if ($cached) {
                $this->line("Resuming from cached position: ID {$cached}");

                return (int) $cached;
            }
            $this->line('No previous position. Starting from ID 1.');

            return 1;
        }

        $this->error('Use --from-id=N or --auto to resume.');

        return null;
    }

    /**
     * Process one anime: reliable hosts → same-host, unreliable → consolidate.
     */
    private function processAnime(
        Anime $anime,
        PlayerTransferService $service,
        string $consolidateTo,
        array $unreliable,
        int $quota,
        int $maxFailures,
        string $lang,
        bool $addLink,
        bool $updateDb,
        bool $statusOnly,
        bool $retryFailed,
        bool $reset,
        Carbon $today,
    ): array {
        $result = [
            'transferred' => 0, 'consolidated' => 0, 'failed' => 0, 'skipped' => 0,
            'empty' => false, 'complete' => false, 'has_failures' => false,
        ];

        $this->line('──────────────────────────────────────');
        $this->info("◉ [{$anime->id}] {$anime->name}");

        if ($reset) {
            $deleted = TransferProgress::where('anime_id', $anime->id)->delete();
            if ($deleted) {
                $this->warn("  Reset: {$deleted} record(s) deleted.");
            }

            return $result;
        }

        if ($retryFailed) {
            $updated = TransferProgress::where('anime_id', $anime->id)
                ->where('status', 'failed')
                ->update(['status' => 'pending', 'error' => null]);
            if ($updated) {
                $this->line("  Reset {$updated} failed item(s).");
            }

            return $result;
        }

        // Anime with no episodes/links at all
        $hasEpisodes = EpisodeLink::query()
            ->whereHas('episode.season', fn ($q) => $q->where('anime_id', $anime->id))
            ->exists();

        if (! $hasEpisodes) {
            $this->line('  <fg=yellow>⚠ No episodes/links. Skipping.</>');
            $result['empty'] = true;
            $result['complete'] = true;

            return $result;
        }

        $this->syncProgressTable($anime, $lang);

        $progressQuery = TransferProgress::where('anime_id', $anime->id);
        if ($lang !== '') {
            $progressQuery->whereHas('episodeLink', fn ($q) => $q->where('language', $lang));
        }
        $allProgress = $progressQuery->get();

        if ($allProgress->isEmpty()) {
            $this->line('  <fg=yellow>⚠ No links match filters.</>');
            $result['empty'] = true;
            $result['complete'] = true;

            return $result;
        }

        $byHost = $allProgress->groupBy('host');
        $totalDone = $allProgress->where('status', 'done')->count();
        $totalAll = $allProgress->count();
        $totalPending = $allProgress->where('status', 'pending')->count();
        $totalFailed = $allProgress->where('status', 'failed')->count();
        $pct = $totalAll > 0 ? round(($totalDone / $totalAll) * 100, 1) : 0;

        $this->line("  Progress: {$totalDone}/{$totalAll} ({$pct}%) | Pending: {$totalPending} | Failed: {$totalFailed}");

        if ($statusOnly) {
            foreach ($byHost as $host => $items) {
                $action = $this->isUnreliable($host, $unreliable) ? "→{$consolidateTo}" : '→same';
                $done = $items->where('status', 'done')->count();
                $pending = $items->where('status', 'pending')->count();
                $failed = $items->where('status', 'failed')->count();
                $this->line("    {$host} {$action}: ✓{$done} ⏳{$pending} ✗{$failed}");
            }
            $result['complete'] = ($totalPending === 0);

            return $result;
        }

        if ($totalPending === 0) {
            $this->line('  <fg=green>✓ Complete!</>');
            $result['complete'] = true;
            $result['has_failures'] = ($totalFailed > 0);

            return $result;
        }

        // Process each host
        foreach ($byHost as $host => $items) {
            $isUnreliable = $this->isUnreliable($host, $unreliable);

            // Decide target platform
            $targetPlatform = null; // same-host by default
            if ($isUnreliable) {
                if ($consolidateTo === '') {
                    $this->line("  <fg=gray>[{$host}]</> Unreliable, no --consolidate-to. Skipping.");
                    $result['skipped'] += $items->where('status', 'pending')->count();

                    continue;
                }
                $targetPlatform = $consolidateTo;
            }

            // Which driver name governs the credentials + quota?
            $sourceDriver = $service->driverForHost($host);
            if (! $sourceDriver) {
                $this->line("  <fg=gray>[{$host}]</> No driver. Skipping.");

                continue;
            }

            $credPlatform = $targetPlatform ?? $sourceDriver->name();
            $credentials = $this->getTargetCredentials($credPlatform);
            if (empty($credentials)) {
                $this->warn("  [{$host}] No credentials for target '{$credPlatform}'. Set TRANSFER_".strtoupper($credPlatform)."_* in .env");
                $result['skipped'] += $items->where('status', 'pending')->count();

                continue;
            }

            // Daily quota is per SOURCE host
            $doneToday = $items->where('status', 'done')
                ->where('transferred_at', $today->toDateString())->count();
            $remaining = max(0, $quota - $doneToday);

            if ($remaining <= 0) {
                $pendingCount = $items->where('status', 'pending')->count();
                if ($pendingCount > 0) {
                    $this->line("  <fg=yellow>[{$host}]</> Quota {$quota}/{$quota} atteint. Demain.");
                    $result['skipped'] += $pendingCount;
                }

                continue;
            }

            $pending = $items->where('status', 'pending')->sortBy('id')->take($remaining);
            if ($pending->isEmpty()) {
                continue;
            }

            $action = $targetPlatform ? "→ {$targetPlatform} (consolidate)" : '→ same-host';
            $this->line("  <fg=cyan>[{$host}]</> {$pending->count()} {$action} (aujourd'hui: {$doneToday}/{$quota})");

            $consecutiveFailures = 0;

            foreach ($pending as $progress) {
                if ($consecutiveFailures >= $maxFailures) {
                    $this->line("    <fg=red>⊘</> {$maxFailures} échecs consécutifs sur {$host}. Arrêt.");
                    break;
                }

                $link = $progress->episodeLink;
                if (! $link) {
                    $progress->update(['status' => 'failed', 'error' => 'Link supprimé']);
                    $result['failed']++;

                    continue;
                }

                $episode = $link->episode;
                $season = $episode?->season;
                $epLabel = 'S'.str_pad((string) (($season?->position ?? 0) + 1), 2, '0', STR_PAD_LEFT)
                    .'E'.str_pad((string) ($episode?->index ?? 0), 2, '0', STR_PAD_LEFT)." [{$link->language}]";

                $title = sprintf('%s - S%02dE%02d %s [%s]',
                    $anime->name,
                    ($season?->position ?? 0) + 1,
                    $episode?->index ?? 0,
                    $episode?->name ?? '',
                    $link->language ?? ''
                );

                try {
                    $tr = $service->transfer(trim($link->url), trim($title), $credentials, null, $targetPlatform);
                    $newHost = $tr['host'] ?? ($targetPlatform ?: $link->host);

                    $progress->update([
                        'status' => 'done',
                        'new_url' => $tr['embed_url'],
                        'new_file_code' => $tr['file_code'] ?? null,
                        'transferred_at' => $today,
                        'error' => null,
                    ]);

                    if ($targetPlatform && $addLink) {
                        // Consolidation with --add-link: keep original, add new monetizing link
                        EpisodeLink::create([
                            'episode_id' => $link->episode_id,
                            'language' => $link->language,
                            'position' => 0,
                            'url' => $tr['embed_url'],
                            'host' => $newHost,
                        ]);
                    } elseif ($updateDb) {
                        $link->update(['url' => $tr['embed_url'], 'host' => $newHost]);
                    }

                    if ($targetPlatform) {
                        $result['consolidated']++;
                    } else {
                        $result['transferred']++;
                    }
                    $consecutiveFailures = 0;
                    $this->line("    <fg=green>✓</> {$epLabel}");
                } catch (Throwable $e) {
                    $progress->update(['status' => 'failed', 'error' => $e->getMessage()]);
                    $result['failed']++;
                    $result['has_failures'] = true;
                    $consecutiveFailures++;
                    $this->line("    <fg=red>✗</> {$epLabel}: ".substr($e->getMessage(), 0, 70));
                }
            }
        }

        $remainingPending = TransferProgress::where('anime_id', $anime->id)
            ->where('status', 'pending')->count();
        $result['complete'] = ($remainingPending === 0);
        if ($result['complete']) {
            $this->line('  <fg=green>✓ Anime terminé !</>');
        }

        return $result;
    }

    private function isUnreliable(string $host, array $unreliable): bool
    {
        foreach ($unreliable as $u) {
            if ($u !== '' && str_contains($host, $u)) {
                return true;
            }
        }

        return false;
    }

    private function renderSummary(array $totals, int $completed, int $count, array $empty, array $failed, int $startId): void
    {
        $this->newLine();
        $this->info('═══ AUTO SUMMARY ═══');
        $this->table(['', 'Count'], [
            ['Animes processed', $count],
            ['Animes completed', $completed],
            ['✓ Same-host transferred', $totals['transferred']],
            ['✓ Consolidated (cross-host)', $totals['consolidated']],
            ['✗ Failed', $totals['failed']],
            ['⊘ Skipped (quota/creds)', $totals['skipped']],
        ]);

        if (! empty($empty)) {
            $this->newLine();
            $this->warn('⚠ Animes sans épisodes ('.count($empty).'):');
            foreach ($empty as $a) {
                $this->line("  [{$a->id}] {$a->name}");
            }
        }

        if (! empty($failed)) {
            $this->newLine();
            $this->warn('⚠ Animes avec échecs ('.count($failed).'):');
            foreach ($failed as $a) {
                $n = TransferProgress::where('anime_id', $a->id)->where('status', 'failed')->count();
                $this->line("  [{$a->id}] {$a->name} — {$n} échec(s)");
            }
            $this->line('  Utilise --retry-failed pour réessayer.');
        }

        $currentId = Cache::get(self::CACHE_KEY, $startId);
        $this->newLine();
        $this->line("📍 Position actuelle: anime ID {$currentId} (reprise avec --auto)");
    }

    /**
     * Ensure every episode_link for this anime has a progress row.
     */
    private function syncProgressTable(Anime $anime, string $lang): void
    {
        $query = EpisodeLink::query()
            ->whereHas('episode.season', fn ($q) => $q->where('anime_id', $anime->id));

        if ($lang !== '') {
            $query->where('language', $lang);
        }

        $links = $query->get(['id', 'host']);

        $existingIds = TransferProgress::where('anime_id', $anime->id)
            ->pluck('episode_link_id')->flip();

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
            foreach (array_chunk($toInsert, 500) as $chunk) {
                TransferProgress::insert($chunk);
            }
            $this->line('  Synced: '.count($toInsert).' nouveau(x) lien(s).');
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
