<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\EpisodeLink;
use App\Models\TorrentDownload;
use App\Models\TorrentFile;
use App\Models\TorrentFileTarget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Orchestrates "torrent" batches, with two interchangeable providers.
 *
 *   • aria2 (100% free): our server downloads the magnet/.torrent with aria2c,
 *     then uploads each video file to the TARGET player host (file upload).
 *   • realdebrid (paid): Real-Debrid downloads it and exposes a direct HTTP
 *     link per file; the host fetches each link itself (remote upload).
 *
 * Either way, once a file is live its embed URL is attached to the episode.
 *
 * Batch flow:
 *   pending → downloading (aria2) | resolving (Real-Debrid) → processing → done
 * Per-file:
 *   aria2 → upload local file → done → episode_link
 *   RD    → send link → queued (host fetching) → poll → done → episode_link
 */
class TorrentService
{
    /** Hosts that support server-to-server remote upload. */
    private const REMOTE_HOSTS = ['doodstream', 'vidmoly', 'streamtape', 'mp4upload'];

    /** Video extensions kept when selecting files inside a torrent. */
    private const VIDEO_EXTS = ['mp4', 'mkv', 'avi', 'webm', 'ts', 'flv', 'mov', 'm4v', 'wmv'];

    /** Ignore files below this size (samples/nfo/images). */
    private const MIN_VIDEO_SIZE = 5_000_000; // 5 MB

    /** Max files to submit/poll per processJob() call (keeps each run bounded). */
    private int $filesPerRun;

    /** Default per-host daily upload cap (overridable via TRANSFER_<HOST>_DAILY_LIMIT). */
    private int $defaultDailyLimit;

    /** aria2 slice size: download this many video files at a time (0 = all at once). */
    private int $aria2Slice;

    private PlayerTransferService $transferService;
    private RealDebridClient $debrid;
    private Aria2Client $aria2;

    public function __construct(
        PlayerTransferService $transferService,
        RealDebridClient $debrid,
        Aria2Client $aria2,
    ) {
        $this->transferService = $transferService;
        $this->debrid = $debrid;
        $this->aria2 = $aria2;
        $this->filesPerRun = max(1, (int) env('TORRENT_FILES_PER_RUN', 10));
        $this->defaultDailyLimit = max(1, (int) env('TORRENT_DEFAULT_DAILY_LIMIT', 200));
        // 0 = download the whole torrent at once (default). >0 = "slice" mode:
        // download N files at a time (for small-disk VPS). Only applies to aria2.
        $this->aria2Slice = max(0, (int) env('TORRENT_ARIA2_SLICE', 0));
    }

    /* =====================================================================
     * PER-HOST DAILY UPLOAD QUOTA
     *
     * Some player hosts cap how many uploads you may push per day. We honor
     * that cap using a per-host, per-DATE counter in the cache (auto-resets at
     * midnight), SHARED with episodes:remote so both features draw from the
     * same host budget. When a host's cap is reached, remaining files stay
     * pending and resume automatically the next day.
     * =================================================================== */

    /** Configured daily cap for a host (TRANSFER_<HOST>_DAILY_LIMIT or default). */
    public function hostDailyLimit(string $platform): int
    {
        $env = env('TRANSFER_' . strtoupper($platform) . '_DAILY_LIMIT');

        return ($env !== null && $env !== '') ? max(1, (int) $env) : $this->defaultDailyLimit;
    }

    /** Uploads already sent to a host today (shared counter with episodes:remote). */
    public function dailyUsed(string $platform, ?Carbon $day = null): int
    {
        return (int) Cache::get($this->dailyKey($platform, $day ?? Carbon::today()), 0);
    }

    /** Remaining uploads allowed for a host today. */
    public function dailyRemaining(string $platform): int
    {
        return max(0, $this->hostDailyLimit($platform) - $this->dailyUsed($platform));
    }

    private function dailyKey(string $platform, Carbon $day): string
    {
        return "remote_daily:{$platform}:{$day->toDateString()}";
    }

    private function bumpDaily(string $platform): void
    {
        $key = $this->dailyKey($platform, Carbon::today());
        Cache::put($key, $this->dailyUsed($platform) + 1, Carbon::now()->endOfDay());
    }

    /**
     * Per-host daily usage for the dashboard.
     *
     * @return list<array{host:string, used:int, limit:int, remaining:int, capped:bool}>
     */
    public function dailyUsage(): array
    {
        $out = [];
        foreach (self::REMOTE_HOSTS as $host) {
            $used = $this->dailyUsed($host);
            $limit = $this->hostDailyLimit($host);
            $out[] = [
                'host' => $host,
                'used' => $used,
                'limit' => $limit,
                'remaining' => max(0, $limit - $used),
                'capped' => $used >= $limit,
            ];
        }

        return $out;
    }

    /** Default provider from config (aria2 = free). */
    public static function defaultProvider(): string
    {
        $p = strtolower((string) env('TORRENT_PROVIDER', 'aria2'));

        return in_array($p, ['aria2', 'realdebrid'], true) ? $p : 'aria2';
    }

    /**
     * Platforms that can receive a link (remote upload).
     *
     * @return list<string>
     */
    public static function remoteHosts(): array
    {
        return self::REMOTE_HOSTS;
    }

    /* =====================================================================
     * PIPELINE
     * =================================================================== */

    /**
     * Advance a batch:
     *   Stage A — fetch the torrent source into files (provider-specific):
     *             aria2 downloads locally, or Real-Debrid resolves to links.
     *   Stage B — make each file live on the host (local upload or remote
     *             upload), then attach it to its episode.
     */
    public function processJob(TorrentDownload $job): void
    {
        try {
            // At least one target player must have credentials (multi-target).
            $withCreds = array_filter($job->platforms(), fn ($p) => ! empty($this->getCredentials($p)));
            if (empty($withCreds)) {
                $job->markFailed(
                    'Aucun identifiant configuré pour les lecteurs cibles ('
                    . implode(', ', $job->platforms()) . '). Renseignez TRANSFER_<HOST>_* dans .env'
                );

                return;
            }

            // Stage A: fetch the magnet/.torrent source. Returns false while
            // still downloading/resolving (or on failure) — retry next pass.
            if ($job->usesTorrent()) {
                if ($job->usesAria2() && ($job->isPending() || $job->isDownloading())) {
                    if (! $this->downloadAria2($job)) {
                        return;
                    }
                } elseif ($job->usesDebrid() && ($job->isPending() || $job->isResolving())) {
                    if (! $this->resolveDebrid($job)) {
                        return;
                    }
                }
            }

            // Direct-link batches: files already exist; move to processing.
            if ($job->isPending()) {
                $job->markProcessing();
            }

            if (! $job->isProcessing()) {
                return;
            }

            // Stage B (multi-target): make a per-player target for each episode,
            // push pending targets (quota-aware), poll queued ones, then finalize
            // files whose every target is resolved.
            $this->ensureTargets($job);
            $this->submitPendingTargets($job);
            $this->pollQueuedTargets($job);
            $this->finalizeFiles($job);

            $job->refreshCounters();

            // Done when no episode file is still awaiting its players.
            $remaining = $job->files()->where('status', 'pending')->count();
            if ($remaining === 0) {
                // Slice mode: if more slices remain to download, go back to the
                // download stage instead of finishing (the uploaded slice's
                // local files were already deleted, freeing the disk).
                if ($job->usesAria2() && $this->aria2Slice > 0 && is_array($job->aria2_files)
                    && $job->files()->count() < count($job->aria2_files)) {
                    $job->markDownloading();

                    return;
                }

                $job->markDone();
                $this->cleanupDownloadArtifacts($job);
            }
        } catch (Throwable $e) {
            $job->markFailed(mb_substr($e->getMessage(), 0, 500));
            // Free the disk if an aria2 download was partially written.
            $this->cleanupDownloadArtifacts($job);
        }
    }

    /* =====================================================================
     * ARIA2 DOWNLOAD (free, self-hosted: magnet/.torrent → local files)
     * =================================================================== */

    /**
     * Advance the aria2 download for a batch.
     *
     * @return bool true once the batch has moved to 'processing' (files
     *              downloaded and torrent_files created).
     */
    private function downloadAria2(TorrentDownload $job): bool
    {
        if (! $this->aria2->isAvailable()) {
            $job->markFailed('aria2c indisponible (ni RPC ni binaire). Installez aria2 et configurez ARIA2_* dans .env.');

            return false;
        }

        // Kick off the download.
        if ($job->isPending()) {
            $gid = $this->aria2->add(trim($job->torrent_url));
            $job->markDownloading($gid);

            // Slice mode: select only the first slice ASAP (bounded metadata
            // wait) so aria2 doesn't grab the whole torrent onto a small disk.
            if ($this->aria2Slice > 0) {
                $this->initAria2Slice($job);
            }

            return false; // poll on the next pass
        }

        if (! $job->aria2_gid) {
            $job->markFailed('Téléchargement sans identifiant aria2.');

            return false;
        }

        // Slice mode: download a few files at a time (limited-disk VPS).
        if ($this->aria2Slice > 0) {
            return $this->handleAria2Slice($job);
        }

        $poll = $this->aria2->poll($job->aria2_gid);
        // Reuse debrid_progress as the generic source-download progress.
        if ((int) $poll['progress'] !== (int) $job->debrid_progress) {
            $job->update(['debrid_progress' => (int) $poll['progress']]);
        }

        switch ($poll['status']) {
            case 'active':
            case 'waiting':
            case 'paused':
                return false; // still downloading

            case 'complete':
                $created = $this->createFilesFromAria2($job, $poll['files']);
                if ($created === 0) {
                    $job->markFailed('Téléchargement terminé mais aucun fichier vidéo trouvé.');
                    $this->cleanupDownloadArtifacts($job);

                    return false;
                }
                $job->markProcessing();

                return true;

            case 'error':
                $job->markFailed($poll['error'] ?? 'aria2c: échec du téléchargement.');
                $this->cleanupDownloadArtifacts($job); // free partial files

                return false;

            default:
                $job->markFailed("aria2c: statut '{$poll['status']}'.");
                $this->cleanupDownloadArtifacts($job);

                return false;
        }
    }

    /**
     * Create a torrent_files row (local_path) per downloaded video file.
     *
     * @param  list<array{path:string, length:int}>  $files
     * @return int number of video files kept
     */
    private function createFilesFromAria2(TorrentDownload $job, array $files): int
    {
        $videos = [];
        foreach ($files as $f) {
            $path = $f['path'] ?? '';
            if ($path === '' || ! is_file($path)) {
                continue;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (! in_array($ext, self::VIDEO_EXTS, true)) {
                continue;
            }
            $size = (int) ($f['length'] ?? @filesize($path));
            if ($size < self::MIN_VIDEO_SIZE) {
                continue;
            }
            $videos[] = ['path' => $path, 'name' => basename($path)];
        }

        // Stable order for deterministic positional fallback.
        usort($videos, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        $episodesByIndex = $job->season_id
            ? Episode::where('season_id', $job->season_id)->get()->keyBy('index')
            : collect();

        $fallbackIndex = 1;
        $created = 0;

        foreach ($videos as $v) {
            $epNum = $this->parseEpisodeNumber($v['name']);
            if ($epNum === null && ! ($job->episode_id && ! $job->season_id)) {
                $epNum = $fallbackIndex;
            }
            $episodeId = $this->matchEpisodeId($job, $epNum, $episodesByIndex);
            $fallbackIndex = ($epNum ?? $fallbackIndex) + 1;

            TorrentFile::create([
                'torrent_download_id' => $job->id,
                'source_url' => null,
                'local_path' => $v['path'],
                'file_name' => $v['name'],
                'detected_episode' => $epNum,
                'episode_id' => $episodeId,
                'language' => $job->language,
                'status' => 'pending',
            ]);
            $created++;
        }

        $job->refreshCounters();

        return $created;
    }

    /* =====================================================================
     * ARIA2 SLICE MODE (download N files at a time — small-disk VPS)
     * =================================================================== */

    /**
     * Keep only real video files, sorted by their torrent index.
     *
     * @return list<array{index:int, path:string, length:int}>
     */
    private function extractVideos(array $files): array
    {
        $videos = [];
        foreach ($files as $f) {
            $path = $f['path'] ?? '';
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($path !== '' && in_array($ext, self::VIDEO_EXTS, true) && (int) ($f['length'] ?? 0) >= self::MIN_VIDEO_SIZE) {
                $videos[] = ['index' => (int) $f['index'], 'path' => $path, 'length' => (int) $f['length']];
            }
        }
        usort($videos, fn ($a, $b) => $a['index'] <=> $b['index']);

        return $videos;
    }

    /**
     * Right after adding the torrent: wait briefly for metadata, store the
     * video file list, and select only the first slice so aria2 doesn't
     * download the whole torrent onto a limited disk.
     */
    private function initAria2Slice(TorrentDownload $job): void
    {
        for ($i = 0; $i < 8; $i++) {
            $videos = $this->extractVideos($this->aria2->getFiles($job->aria2_gid));
            if (! empty($videos)) {
                $job->update(['aria2_files' => $videos]);
                $slice = array_slice($videos, 0, $this->aria2Slice);
                $this->aria2->select($job->aria2_gid, implode(',', array_column($slice, 'index')));

                return;
            }
            sleep(1); // metadata not ready yet
        }
        // Not ready after ~8s — handleAria2Slice() retries on the next pass.
    }

    /**
     * Advance a sliced aria2 download: download the current slice, then hand it
     * to the upload stage. Returns true once the current slice is ready
     * (job moved to 'processing'); false while still downloading.
     */
    private function handleAria2Slice(TorrentDownload $job): bool
    {
        // Surface a hard aria2 error.
        $poll = $this->aria2->poll($job->aria2_gid);
        if (($poll['status'] ?? '') === 'error') {
            $job->markFailed($poll['error'] ?? 'aria2c: échec du téléchargement.');
            $this->cleanupDownloadArtifacts($job);

            return false;
        }

        // Ensure we know the ordered video-file list (from metadata).
        $videos = is_array($job->aria2_files) ? $job->aria2_files : [];
        if (empty($videos)) {
            $videos = $this->extractVideos($this->aria2->getFiles($job->aria2_gid));
            if (empty($videos)) {
                return false; // metadata still not ready — retry
            }
            $job->update(['aria2_files' => $videos]);
            $slice = array_slice($videos, 0, $this->aria2Slice);
            $this->aria2->select($job->aria2_gid, implode(',', array_column($slice, 'index')));

            return false;
        }

        $total = count($videos);
        $done = $job->files()->count(); // video files already created (past slices)

        if ($done >= $total) {
            // All slices created — let the upload stage finish the rest.
            $job->markProcessing();

            return true;
        }

        $slice = array_slice($videos, $done, $this->aria2Slice);
        $sliceIndices = array_column($slice, 'index');

        // Live per-file status.
        $live = [];
        foreach ($this->aria2->getFiles($job->aria2_gid) as $f) {
            $live[$f['index']] = $f;
        }

        // Make sure only the current slice is selected for download.
        $needSelect = false;
        foreach ($sliceIndices as $idx) {
            if (empty($live[$idx]['selected'])) {
                $needSelect = true;
                break;
            }
        }
        if ($needSelect) {
            $this->aria2->select($job->aria2_gid, implode(',', $sliceIndices));

            return false; // start downloading this slice; check next pass
        }

        // Is the whole slice downloaded?
        $sliceTotal = 0;
        $sliceDone = 0;
        $allComplete = true;
        foreach ($slice as $v) {
            $cl = (int) ($live[$v['index']]['completedLength'] ?? 0);
            $sliceDone += $cl;
            $sliceTotal += $v['length'];
            if ($cl < $v['length']) {
                $allComplete = false;
            }
        }

        // Rough overall progress (finished slices + current slice fraction).
        $frac = $sliceTotal > 0 ? $sliceDone / $sliceTotal : 0;
        $job->update(['debrid_progress' => (int) round((($done + $frac) / max(1, $total)) * 100)]);

        if (! $allComplete) {
            return false; // slice still downloading
        }

        // Slice complete → create its files and hand them to the upload stage.
        $this->createFilesForSlice($job, $slice);
        $job->markProcessing();

        return true;
    }

    /**
     * Create a torrent_files row (local_path) for each file of a slice.
     */
    private function createFilesForSlice(TorrentDownload $job, array $slice): void
    {
        $episodesByIndex = $job->season_id
            ? Episode::where('season_id', $job->season_id)->get()->keyBy('index')
            : collect();

        $offset = $job->files()->count(); // absolute position of the slice's 1st file

        foreach (array_values($slice) as $i => $v) {
            $fileName = basename($v['path']);
            $epNum = $this->parseEpisodeNumber($fileName);
            if ($epNum === null && ! ($job->episode_id && ! $job->season_id)) {
                $epNum = $offset + $i + 1; // positional fallback (absolute)
            }
            $episodeId = $this->matchEpisodeId($job, $epNum, $episodesByIndex);

            TorrentFile::create([
                'torrent_download_id' => $job->id,
                'source_url' => null,
                'local_path' => $v['path'],
                'file_name' => $fileName,
                'detected_episode' => $epNum,
                'episode_id' => $episodeId,
                'language' => $job->language,
                'status' => 'pending',
            ]);
        }

        $job->refreshCounters();
    }

    /* =====================================================================
     * REAL-DEBRID RESOLUTION (magnet/.torrent → direct HTTP links)
     * =================================================================== */

    /**
     * Advance the Real-Debrid resolution for a batch.
     *
     * @return bool true once the batch has moved to 'processing' (links
     *              resolved and torrent_files created).
     */
    private function resolveDebrid(TorrentDownload $job): bool
    {
        if (! $this->debrid->isConfigured()) {
            $job->markFailed('Real-Debrid non configuré (REALDEBRID_API_TOKEN manquant dans .env).');

            return false;
        }

        // Kick off: register the magnet/.torrent with Real-Debrid.
        if ($job->isPending()) {
            try {
                $url = trim($job->torrent_url);
                $id = str_starts_with(strtolower($url), 'magnet:')
                    ? $this->debrid->addMagnet($url)
                    : $this->debrid->addTorrentUrl($url);
            } catch (\RuntimeException $e) {
                // HTTP 451 = content blocked by Real-Debrid (DMCA / infringing).
                // No point retrying: RD will always refuse this hash.
                if (str_contains($e->getMessage(), '451') || str_contains($e->getMessage(), 'infringing')) {
                    $job->update(['attempts' => 99]); // prevent automatic retry
                    $job->markFailed('Contenu bloqué par Real-Debrid (signalement ayant-droit). Essayez un autre magnet/source pour cet anime.');

                    return false;
                }
                throw $e; // other errors: bubble up normally
            }
            $job->markResolving($id);

            return false; // poll on the next pass
        }

        if (! $job->debrid_id) {
            $job->markFailed('Résolution sans identifiant Real-Debrid.');

            return false;
        }

        $info = $this->debrid->info($job->debrid_id);
        $status = $info['status'] ?? 'error';
        $progress = (int) ($info['progress'] ?? 0);
        if ($progress !== $job->debrid_progress) {
            $job->update(['debrid_progress' => $progress]);
        }

        switch ($status) {
            case 'waiting_files_selection':
                // Keep only the video files (skip samples/nfo/images).
                $ids = $this->videoFileIds($info['files'] ?? []);
                $this->debrid->selectFiles($job->debrid_id, $ids !== '' ? $ids : 'all');

                return false;

            case 'magnet_conversion':
            case 'queued':
            case 'downloading':
            case 'compressing':
            case 'uploading':
                return false; // Real-Debrid still working — wait.

            case 'downloaded':
                $this->createFilesFromDebrid($job, $info);
                $job->markProcessing();
                // Housekeeping: remove the torrent from the RD account.
                $this->debrid->deleteTorrent($job->debrid_id);

                return true;

            default: // magnet_error, error, virus, dead
                $job->markFailed("Real-Debrid: statut '{$status}'.");

                return false;
        }
    }

    /**
     * Comma-separated ids of the video files in a Real-Debrid files[] list.
     */
    private function videoFileIds(array $files): string
    {
        $ids = [];
        foreach ($files as $f) {
            $path = $f['path'] ?? '';
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, self::VIDEO_EXTS, true) && isset($f['id'])) {
                $ids[] = $f['id'];
            }
        }

        return implode(',', $ids);
    }

    /**
     * Once Real-Debrid finished, unrestrict each selected file into a direct
     * link and create a torrent_files row (mapped to an episode).
     *
     * RD returns links[] in the same order as the SELECTED files[].
     */
    private function createFilesFromDebrid(TorrentDownload $job, array $info): void
    {
        $selected = array_values(array_filter(
            $info['files'] ?? [],
            fn ($f) => (int) ($f['selected'] ?? 0) === 1
        ));
        $links = array_values($info['links'] ?? []);

        $episodesByIndex = $job->season_id
            ? Episode::where('season_id', $job->season_id)->get()->keyBy('index')
            : collect();

        $fallbackIndex = 1;

        foreach ($selected as $i => $f) {
            $path = $f['path'] ?? '';
            $fileName = $path !== '' ? basename($path) : "file{$i}";
            $rdLink = $links[$i] ?? null;

            $epNum = $this->parseEpisodeNumber($fileName);
            if ($epNum === null && ! ($job->episode_id && ! $job->season_id)) {
                $epNum = $fallbackIndex;
            }
            $episodeId = $this->matchEpisodeId($job, $epNum, $episodesByIndex);
            $fallbackIndex = ($epNum ?? $fallbackIndex) + 1;

            // Unrestrict the RD hoster link into a direct URL we can send.
            $directUrl = null;
            $error = null;
            if ($rdLink) {
                try {
                    $directUrl = $this->debrid->unrestrict($rdLink);
                } catch (Throwable $e) {
                    $error = 'Real-Debrid unrestrict: ' . mb_substr($e->getMessage(), 0, 200);
                }
            } else {
                $error = 'Real-Debrid: aucun lien pour ce fichier.';
            }

            TorrentFile::create([
                'torrent_download_id' => $job->id,
                'source_url' => $directUrl ?? '',
                'file_name' => $fileName,
                'detected_episode' => $epNum,
                'episode_id' => $episodeId,
                'language' => $job->language,
                'status' => $directUrl ? 'pending' : 'failed',
                'error' => $error,
            ]);
        }

        $job->refreshCounters();
    }

    /**
     * Resolve the episode id for a file (explicit → single-episode → by number).
     */
    private function matchEpisodeId(
        TorrentDownload $job,
        ?int $epNum,
        \Illuminate\Support\Collection $episodesByIndex,
        ?int $explicit = null,
    ): ?int {
        if ($explicit !== null) {
            return $explicit;
        }
        if ($job->episode_id && ! $job->season_id) {
            return $job->episode_id;
        }
        if ($epNum === null) {
            return null;
        }

        return $episodesByIndex->get($epNum)?->id;
    }

    /* =====================================================================
     * MULTI-TARGET UPLOAD (each episode → every selected player)
     * =================================================================== */

    /**
     * Ensure every ready episode file has one target per selected player.
     * Files with no matching episode are skipped (need manual mapping first).
     */
    private function ensureTargets(TorrentDownload $job): void
    {
        $platforms = $job->platforms();

        foreach ($job->files()->where('status', 'pending')->get() as $file) {
            if ($file->episode_id === null) {
                $file->markSkipped('Aucun épisode correspondant — mappez-le manuellement puis réessayez.');

                continue;
            }
            $existing = $file->targets()->pluck('platform')->all();
            foreach ($platforms as $platform) {
                if (! in_array($platform, $existing, true)) {
                    TorrentFileTarget::create([
                        'torrent_file_id' => $file->id,
                        'platform' => $platform,
                        'status' => 'pending',
                    ]);
                }
            }
        }
    }

    /**
     * Push pending (file × player) targets, honoring each player's daily cap.
     * When a player's cap is reached, its targets stay pending and resume the
     * next day (the per-host counter resets at midnight).
     */
    private function submitPendingTargets(TorrentDownload $job): void
    {
        $targets = TorrentFileTarget::query()
            ->whereHas('file', fn ($q) => $q->where('torrent_download_id', $job->id))
            ->where('status', 'pending')
            ->with('file')
            ->orderBy('id')
            ->limit($this->filesPerRun)
            ->get();

        foreach ($targets as $target) {
            $platform = $target->platform;

            // Per-player daily quota (shared with episodes:remote).
            if ($this->dailyRemaining($platform) <= 0) {
                continue; // this player is capped today — try again tomorrow
            }
            $creds = $this->getCredentials($platform);
            if (empty($creds)) {
                $target->markFailed("Aucun identifiant pour {$platform} (TRANSFER_" . strtoupper($platform) . '_*).');

                continue;
            }

            $this->submitTarget($job, $target, $creds);
        }
    }

    /**
     * Upload one episode to ONE player.
     *   • aria2 (file->local_path) → upload the local file (kept until every
     *     player is done, then deleted by finalizeFiles()).
     *   • RD/direct link (file->source_url) → remote upload (host fetches it).
     */
    private function submitTarget(TorrentDownload $job, TorrentFileTarget $target, array $creds): void
    {
        $file = $target->file;
        if (! $file) {
            $target->markFailed('Fichier introuvable.');

            return;
        }

        try {
            $title = $this->buildTitle($job, $file);
            $platform = $target->platform;

            // --- aria2: upload the locally-downloaded file ---
            if ($file->local_path) {
                $path = $file->local_path;
                if (! $path || ! is_file($path)) {
                    $target->markFailed("Fichier introuvable sur le disque : {$path}");

                    return;
                }
                if (filesize($path) < self::MIN_VIDEO_SIZE) {
                    $target->markFailed('Fichier trop petit (probablement corrompu).');

                    return;
                }
                $driver = $this->transferService->driverByName($platform);
                if (! $driver) {
                    $target->markFailed("Aucun driver pour la plateforme : {$platform}");

                    return;
                }
                $result = $driver->upload($path, $title, $creds);
                $embed = $result['embed_url'] ?? null;
                if (! $embed) {
                    $target->markFailed('Upload effectué mais aucune URL embed retournée.');

                    return;
                }
                $target->markDone($embed, $result['file_code'] ?? null);
                $this->attachToEpisode($file, $embed, $platform);
                $this->bumpDaily($platform);

                return;
            }

            // --- RD/direct link: the host fetches the link itself ---
            $result = $this->transferService->transferRemote(
                trim((string) $file->source_url),
                $title,
                $creds,
                $platform,
            );
            $state = $result['state'] ?? 'queued';

            if ($state === 'done' && ! empty($result['embed_url'])) {
                $target->markDone($result['embed_url'], $result['file_code'] ?? null);
                $this->attachToEpisode($file, $result['embed_url'], $platform);
                $this->bumpDaily($platform);

                return;
            }

            // Async: host is now fetching the link; poll it later.
            $target->markQueued($result['job_id'] ?? null);
            $this->bumpDaily($platform);
        } catch (Throwable $e) {
            $target->markFailed(mb_substr($e->getMessage(), 0, 500));
        }
    }

    /**
     * Poll queued targets (remote upload) and finalize the ones the player has
     * finished fetching.
     */
    private function pollQueuedTargets(TorrentDownload $job): void
    {
        $targets = TorrentFileTarget::query()
            ->whereHas('file', fn ($q) => $q->where('torrent_download_id', $job->id))
            ->where('status', 'queued')
            ->with('file')
            ->limit($this->filesPerRun)
            ->get();

        foreach ($targets as $target) {
            try {
                if (! $target->remote_job_id) {
                    $target->update(['status' => 'pending']); // nothing to poll — re-submit

                    continue;
                }
                $status = $this->transferService->remoteStatus(
                    $target->platform,
                    $target->remote_job_id,
                    $this->getCredentials($target->platform),
                );
                switch ($status['state'] ?? 'pending') {
                    case 'done':
                        $embed = $status['embed_url'] ?? null;
                        if ($embed) {
                            $target->markDone($embed, $status['file_code'] ?? null);
                            if ($target->file) {
                                $this->attachToEpisode($target->file, $embed, $target->platform);
                            }
                        } else {
                            $target->markFailed('Le lecteur signale « terminé » sans URL embed.');
                        }
                        break;

                    case 'error':
                        $target->markFailed($status['error'] ?? 'Le lecteur a signalé une erreur.');
                        break;

                    // 'pending' → still fetching, leave queued.
                }
            } catch (Throwable $e) {
                $target->markFailed(mb_substr($e->getMessage(), 0, 500));
            }
        }
    }

    /**
     * Mark files whose every player target is resolved as done, and (aria2)
     * free the local file only once ALL players have uploaded it.
     */
    private function finalizeFiles(TorrentDownload $job): void
    {
        $files = $job->files()->where('status', 'pending')->with('targets')->get();

        foreach ($files as $file) {
            if ($file->targets->isEmpty()) {
                continue; // targets not created yet
            }
            $pending = $file->targets->whereIn('status', ['pending', 'queued'])->count();
            if ($pending > 0) {
                continue; // some players still working
            }

            $file->update(['status' => 'done']);

            if ($file->local_path) {
                $this->deleteLocalFile($file->local_path);
            }
        }
    }

    /* =====================================================================
     * EPISODE MATCHING
     * =================================================================== */

    /**
     * Build torrent_files rows from a list of download links, matching each to
     * an episode. Called at submission time (from the controller).
     *
     * @param  list<array{url:string, episode_id?:int|null}>  $links
     * @return int  number of files created
     */
    public function createFilesFromLinks(TorrentDownload $job, array $links): int
    {
        // Preload the season's episodes for auto-matching.
        $episodesByIndex = $job->season_id
            ? Episode::where('season_id', $job->season_id)->get()->keyBy('index')
            : collect();

        // Stable order so positional fallback is deterministic.
        $links = array_values($links);
        $fallbackIndex = 1;
        $created = 0;

        foreach ($links as $link) {
            $url = trim($link['url'] ?? '');
            if ($url === '') {
                continue;
            }

            $fileName = $this->fileNameFromUrl($url);
            $explicitEpisode = $link['episode_id'] ?? null;

            // Determine episode number / id.
            $epNum = $this->parseEpisodeNumber($fileName);
            $episodeId = $explicitEpisode;

            if ($episodeId === null) {
                if ($job->episode_id && ! $job->season_id) {
                    // Single-episode batch: everything maps to that episode.
                    $episodeId = $job->episode_id;
                } else {
                    if ($epNum === null) {
                        $epNum = $fallbackIndex;
                    }
                    $episodeId = $episodesByIndex->get($epNum)?->id;
                }
            }

            if ($epNum !== null) {
                $fallbackIndex = $epNum + 1;
            } else {
                $fallbackIndex++;
            }

            TorrentFile::create([
                'torrent_download_id' => $job->id,
                'source_url' => $url,
                'file_name' => $fileName,
                'detected_episode' => $epNum,
                'episode_id' => $episodeId,
                'language' => $job->language,
                'status' => 'pending',
            ]);
            $created++;
        }

        $job->refreshCounters();

        return $created;
    }

    /**
     * Parse an episode number from a filename/URL segment.
     *
     * Handles: S01E05 / 1x05 / E05 / Ep 5 / Episode 05 / [05] / positional.
     */
    public function parseEpisodeNumber(string $filename): ?int
    {
        $name = preg_replace('/\.[a-z0-9]{2,4}$/i', '', $filename);

        $patterns = [
            '/S\d{1,2}[\s._-]?E(\d{1,3})/i',
            '/\b\d{1,2}x(\d{1,3})\b/i',
            '/\bE(?:p|pisode)?[\s._-]*(\d{1,3})\b/i',
            '/[\s._-](\d{1,3})[\s._-]?(?:v\d)?\s*(?:\[|\()/',
            '/[\s._-](\d{1,3})[\s._-]/',
            '/\[(\d{1,3})\]/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name, $m)) {
                $num = (int) $m[1];
                if ($num > 0 && $num < 2000) {
                    return $num;
                }
            }
        }

        return null;
    }

    private function fileNameFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $base = basename($path);

        return $base !== '' ? urldecode($base) : $url;
    }

    /* =====================================================================
     * DISK CLEANUP (aria2 provider)
     * =================================================================== */

    private function deleteLocalFile(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
        $control = $path . '.aria2';
        if (is_file($control)) {
            @unlink($control);
        }
    }

    /**
     * Free the disk for an aria2 batch — on success AND on failure.
     *
     * Crucial on small disks: a failed/oversized download leaves partial files
     * that aria2 still holds open, so they keep occupying space until the
     * download is removed from the daemon. We therefore: capture the paths from
     * aria2, tell the daemon to drop the download (releasing the handles), then
     * delete the files and any now-empty torrent sub-folder.
     */
    private function cleanupDownloadArtifacts(TorrentDownload $job): void
    {
        if (! $job->usesAria2()) {
            return;
        }

        $paths = [];

        // Paths aria2 was writing (works even before torrent_files exist,
        // e.g. a download that failed / filled the disk mid-way).
        if ($job->aria2_gid) {
            try {
                foreach ($this->aria2->poll($job->aria2_gid)['files'] ?? [] as $f) {
                    if (! empty($f['path'])) {
                        $paths[] = $f['path'];
                    }
                }
            } catch (Throwable) {
                // ignore — best effort
            }
        }

        // Paths of already-created files (post-complete uploads).
        foreach ($job->files as $file) {
            if ($file->local_path) {
                $paths[] = $file->local_path;
            }
        }

        // Stop the download in the daemon so it releases the open files.
        if ($job->aria2_gid) {
            $this->aria2->remove($job->aria2_gid);
        }

        $dir = $this->aria2->downloadDir();
        foreach (array_unique($paths) as $path) {
            $this->deleteLocalFile($path);

            $parent = dirname($path);
            if ($parent !== $dir && is_dir($parent) && $this->isDirEmpty($parent)) {
                @rmdir($parent);
            }
        }
    }

    private function isDirEmpty(string $dir): bool
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                return false;
            }
        }

        return true;
    }

    /* =====================================================================
     * EPISODE LINK CREATION
     * =================================================================== */

    private function attachToEpisode(TorrentFile $file, string $embedUrl, string $platform): void
    {
        if (! $file->episode_id) {
            return;
        }

        $host = parse_url($embedUrl, PHP_URL_HOST);
        $host = is_string($host) ? preg_replace('/^www\./', '', $host) : $platform;

        EpisodeLink::create([
            'episode_id' => $file->episode_id,
            'language' => $file->language ?? 'VOSTFR',
            'position' => 0,
            'url' => $embedUrl,
            'host' => $host,
        ]);
    }

    private function buildTitle(TorrentDownload $job, TorrentFile $file): string
    {
        $episode = $file->episode;
        $season = $episode?->season;
        $anime = $season?->anime;

        if ($anime) {
            return sprintf(
                '%s - S%02dE%02d [%s]',
                $anime->name,
                ($season->position ?? 0) + 1,
                $episode->index ?? ($file->detected_episode ?? 0),
                $file->language ?? 'VOSTFR'
            );
        }

        return $job->title ?: pathinfo($file->file_name ?? 'video', PATHINFO_FILENAME);
    }

    /* =====================================================================
     * CREDENTIALS
     * =================================================================== */

    /**
     * Target platform credentials from env (same vars as PlayerTransferService).
     */
    private function getCredentials(string $platform): array
    {
        return match ($platform) {
            'vidmoly' => array_filter([
                'api_key' => env('TRANSFER_VIDMOLY_API_KEY', ''),
                'api_base' => env('TRANSFER_VIDMOLY_API_BASE', 'https://api.vidmoly.to'),
                'embed_base' => env('TRANSFER_VIDMOLY_EMBED_BASE', 'https://vidmoly.to'),
                'timeout' => (int) env('TRANSFER_VIDMOLY_TIMEOUT', 900),
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
            'mp4upload' => array_filter([
                'api_key' => env('TRANSFER_MP4UPLOAD_API_KEY', ''),
                'api_base' => env('TRANSFER_MP4UPLOAD_API_BASE', 'https://www.mp4upload.com/api'),
            ]),
            default => [],
        };
    }
}
