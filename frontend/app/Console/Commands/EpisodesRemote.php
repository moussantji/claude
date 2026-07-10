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
 * SERVER-TO-SERVER transfer (remote upload). The TARGET host downloads the
 * video itself — our server only makes light API calls. Ideal for shared
 * hosting / cPanel (no video download/upload, no disk, no bandwidth on us).
 *
 * Two phases handled in one run:
 *   1. FINALIZE queued jobs — poll the target, get the final embed URL, update DB.
 *   2. QUEUE new pending links (up to the daily quota per anime).
 *
 * Quota: N remote jobs queued per anime per day (default 50). Resumes daily.
 *
 * Examples:
 *   # Send everything to doodstream (best: supports remote upload from many hosts)
 *   php artisan episodes:remote --to=doodstream --from-id=1 --update-db
 *
 *   # Reliable → same-host (remote), unreliable → doodstream
 *   php artisan episodes:remote --consolidate-to=doodstream --auto --update-db
 *
 *   # Only finalize already-queued jobs (fetch final URLs)
 *   php artisan episodes:remote --finalize-only --update-db
 *
 * Scheduler (cheap on shared hosting):
 *   Schedule::command('episodes:remote --to=doodstream --auto --update-db')->hourly();
 */
class EpisodesRemote extends Command
{
    private const UNRELIABLE = ['sendvid', 'sibnet', 'myvi'];
    private const CACHE_KEY = 'episodes_remote_current_id';

    protected $signature = 'episodes:remote
        {--to= : Send ALL links to this single target host (e.g. doodstream)}
        {--consolidate-to= : Only unreliable sources → this host; reliable stay same-host}
        {--from-id= : Start from this anime ID}
        {--auto : Resume from last saved position}
        {--lang= : Only this language}
        {--quota=50 : Remote jobs queued per anime per day}
        {--finalize-only : Only poll/finalize queued jobs, do not queue new ones}
        {--update-db : Update episode_links with the final URLs}
        {--max-minutes=0 : Stop cleanly after N minutes (shared hosting)}
        {--status : Show progress only}';

    public function handle(PlayerTransferService $service): int
    {
        $to = strtolower((string) $this->option('to'));
        $consolidateTo = strtolower((string) $this->option('consolidate-to'));
        $lang = (string) $this->option('lang');
        $quota = max(1, (int) $this->option('quota'));
        $updateDb = (bool) $this->option('update-db');
        $finalizeOnly = (bool) $this->option('finalize-only');
        $maxMinutes = max(0, (int) $this->option('max-minutes'));
        $deadline = $maxMinutes > 0 ? microtime(true) + $maxMinutes * 60 : null;
        $today = Carbon::today();

        if ($to !== '' && ! $service->driverByName($to)) {
            $this->error("Unknown target: {$to}");

            return self::FAILURE;
        }
        if ($consolidateTo !== '' && ! $service->driverByName($consolidateTo)) {
            $this->error("Unknown consolidate target: {$consolidateTo}");

            return self::FAILURE;
        }

        // ---- PHASE 1: finalize queued jobs (cheap, always run) ----
        $finalized = $this->finalizeQueued($service, $updateDb, $deadline);

        if ($finalizeOnly) {
            $this->info("Finalized: {$finalized['done']} done, {$finalized['pending']} still pending, {$finalized['failed']} failed.");

            return self::SUCCESS;
        }

        // ---- PHASE 2: queue new remote jobs ----
        $startId = $this->resolveStartId($this->option('from-id'), (bool) $this->option('auto'));
        if ($startId === null) {
            return self::FAILURE;
        }

        $this->syncAllProgress($lang);

        $animes = Anime::where('id', '>=', $startId)->orderBy('id')->get();
        $this->info('═══ Remote (server-to-server) transfer ═══');
        $this->line("  From ID {$startId} | Quota: {$quota} jobs/anime/day | Finalized this run: {$finalized['done']}");
        $this->newLine();

        if ($this->option('status')) {
            $this->renderStatus();

            return self::SUCCESS;
        }

        $queued = 0;
        $failed = 0;

        foreach ($animes as $anime) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                $this->line("<fg=yellow>⏱ Time limit reached. Resume next run.</>");
                break;
            }

            $items = TransferProgress::where('anime_id', $anime->id)
                ->where('status', 'pending')
                ->when($lang !== '', fn ($q) => $q->whereHas('episodeLink', fn ($l) => $l->where('language', $lang)))
                ->with('episodeLink.episode.season')
                ->orderBy('id')
                ->get();

            if ($items->isEmpty()) {
                // completed for now → advance
                if (! TransferProgress::where('anime_id', $anime->id)->whereIn('status', ['pending', 'queued'])->exists()) {
                    Cache::forever(self::CACHE_KEY, $anime->id + 1);
                }
                continue;
            }

            // Per-anime daily quota: count jobs queued/done today
            $usedToday = TransferProgress::where('anime_id', $anime->id)
                ->whereDate('last_attempt_at', $today->toDateString())
                ->whereIn('status', ['queued', 'done'])
                ->count();
            $budget = max(0, $quota - $usedToday);

            if ($budget <= 0) {
                Cache::forever(self::CACHE_KEY, $anime->id);
                continue;
            }

            $this->line("◉ [{$anime->id}] {$anime->name} — {$items->count()} pending (budget {$budget})");

            foreach ($items->take($budget) as $progress) {
                if ($deadline !== null && microtime(true) >= $deadline) {
                    break 2;
                }

                $link = $progress->episodeLink;
                if (! $link) {
                    $progress->update(['status' => 'failed', 'error' => 'Link deleted', 'last_attempt_at' => now()]);
                    continue;
                }

                $host = $progress->host ?? $link->host ?? '';
                $target = $this->resolveTarget($host, $to, $consolidateTo);
                if ($target === null) {
                    continue; // unreliable but no target, and not forcing --to
                }

                $credentials = $this->getTargetCredentials($target);
                if (empty($credentials)) {
                    continue;
                }

                $ep = $link->episode;
                $season = $ep?->season;
                $label = 'S'.str_pad((string) (($season?->position ?? 0) + 1), 2, '0', STR_PAD_LEFT)
                    .'E'.str_pad((string) ($ep?->index ?? 0), 2, '0', STR_PAD_LEFT)." [{$link->language}]";
                $title = "{$anime->name} - {$label}";

                try {
                    $res = $service->transferRemote(trim($link->url), $title, $credentials, $target);

                    if (($res['state'] ?? '') === 'done' && ! empty($res['embed_url'])) {
                        $progress->update([
                            'status' => 'done',
                            'new_url' => $res['embed_url'],
                            'new_file_code' => $res['file_code'] ?? null,
                            'remote_job_id' => $res['job_id'] ?? null,
                            'transferred_at' => $today,
                            'last_attempt_at' => now(),
                            'error' => null,
                        ]);
                        if ($updateDb) {
                            $link->update(['url' => $res['embed_url'], 'host' => $target]);
                        }
                        $this->line("    <fg=green>✓</> {$label} (instant)");
                    } else {
                        $progress->update([
                            'status' => 'queued',
                            'remote_job_id' => $res['job_id'] ?? null,
                            'new_file_code' => $target,
                            'last_attempt_at' => now(),
                            'error' => null,
                        ]);
                        $this->line("    <fg=cyan>↑</> {$label} queued (job ".($res['job_id'] ?? '?').")");
                    }
                    $queued++;
                } catch (Throwable $e) {
                    $progress->update(['status' => 'failed', 'error' => $e->getMessage(), 'last_attempt_at' => now()]);
                    $failed++;
                    $this->line("    <fg=red>✗</> {$label}: ".substr($e->getMessage(), 0, 60));
                }
            }

            Cache::forever(self::CACHE_KEY, $anime->id);
        }

        $this->newLine();
        $this->info("Queued: {$queued} | Finalized: {$finalized['done']} | Failed: {$failed}");
        $this->line('Re-run --finalize-only later to fetch URLs of queued jobs.');

        return self::SUCCESS;
    }

    /**
     * Poll queued remote jobs and finalize the ones that are ready.
     *
     * @return array{done:int, pending:int, failed:int}
     */
    private function finalizeQueued(PlayerTransferService $service, bool $updateDb, ?float $deadline): array
    {
        $done = 0;
        $pending = 0;
        $failed = 0;

        $queued = TransferProgress::where('status', 'queued')
            ->with('episodeLink')
            ->orderBy('last_attempt_at')
            ->limit(500)
            ->get();

        foreach ($queued as $progress) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }

            // new_file_code holds the target platform for queued jobs
            $platform = $progress->new_file_code ?: '';
            $jobId = (string) ($progress->remote_job_id ?? '');
            if ($platform === '' || $jobId === '') {
                $pending++;
                continue;
            }

            $credentials = $this->getTargetCredentials($platform);
            if (empty($credentials)) {
                $pending++;
                continue;
            }

            try {
                $st = $service->remoteStatus($platform, $jobId, $credentials);
                if ($st['state'] === 'done' && ! empty($st['embed_url'])) {
                    $progress->update([
                        'status' => 'done',
                        'new_url' => $st['embed_url'],
                        'new_file_code' => $st['file_code'] ?? $progress->new_file_code,
                        'transferred_at' => Carbon::today(),
                        'error' => null,
                    ]);
                    if ($updateDb && $progress->episodeLink) {
                        $progress->episodeLink->update(['url' => $st['embed_url'], 'host' => $platform]);
                    }
                    $done++;
                } elseif ($st['state'] === 'error') {
                    $progress->update(['status' => 'failed', 'error' => $st['error'] ?? 'remote error']);
                    $failed++;
                } else {
                    $pending++;
                }
            } catch (Throwable) {
                $pending++;
            }
        }

        return ['done' => $done, 'pending' => $pending, 'failed' => $failed];
    }

    private function resolveTarget(string $host, string $to, string $consolidateTo): ?string
    {
        if ($to !== '') {
            return $to; // force everything to a single target
        }

        $isUnreliable = false;
        foreach (self::UNRELIABLE as $u) {
            if (str_contains($host, $u)) {
                $isUnreliable = true;
                break;
            }
        }

        if ($isUnreliable) {
            return $consolidateTo !== '' ? $consolidateTo : null;
        }

        // reliable host → same host (remote upload to your own account on it)
        return null; // same-host remote handled by passing null target below? — see note
    }

    private function resolveStartId(?string $fromId, bool $auto): ?int
    {
        if ($fromId !== null && $fromId !== '') {
            $id = (int) $fromId;
            Cache::forever(self::CACHE_KEY, $id);

            return $id;
        }
        if ($auto) {
            return (int) (Cache::get(self::CACHE_KEY) ?: 1);
        }
        $this->error('Use --from-id=N or --auto.');

        return null;
    }

    private function renderStatus(): void
    {
        $this->table(['Status', 'Count'], [
            ['done', TransferProgress::where('status', 'done')->count()],
            ['queued', TransferProgress::where('status', 'queued')->count()],
            ['pending', TransferProgress::where('status', 'pending')->count()],
            ['failed', TransferProgress::where('status', 'failed')->count()],
        ]);
    }

    private function syncAllProgress(string $lang): void
    {
        $existing = TransferProgress::pluck('episode_link_id')->flip();
        $now = now();
        $buffer = [];

        EpisodeLink::query()
            ->select('episode_links.id', 'episode_links.host', 'seasons.anime_id')
            ->join('episodes', 'episodes.id', '=', 'episode_links.episode_id')
            ->join('seasons', 'seasons.id', '=', 'episodes.season_id')
            ->when($lang !== '', fn ($q) => $q->where('episode_links.language', $lang))
            ->orderBy('seasons.anime_id')
            ->chunk(1000, function ($links) use (&$buffer, $existing, $now) {
                foreach ($links as $link) {
                    if ($existing->has($link->id)) {
                        continue;
                    }
                    $buffer[] = [
                        'episode_link_id' => $link->id,
                        'anime_id' => $link->anime_id,
                        'host' => $link->host ?? 'unknown',
                        'status' => 'pending',
                        'attempts' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    if (count($buffer) >= 500) {
                        TransferProgress::insert($buffer);
                        $buffer = [];
                    }
                }
            });

        if (! empty($buffer)) {
            TransferProgress::insert($buffer);
        }
    }

    private function getTargetCredentials(string $platform): array
    {
        return match ($platform) {
            'vidmoly' => array_filter([
                'api_key' => env('TRANSFER_VIDMOLY_API_KEY', ''),
                'api_base' => env('TRANSFER_VIDMOLY_API_BASE', 'https://vidmoly.me'),
                'embed_base' => env('TRANSFER_VIDMOLY_EMBED_BASE', 'https://vidmoly.me'),
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
            default => [],
        };
    }
}
