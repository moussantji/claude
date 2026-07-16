<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\EpisodeLink;
use App\Models\TorrentDownload;
use App\Models\TorrentFile;
use App\Models\TorrentFileTarget;
use App\Services\CodecDetector;
use App\Services\PlayerDrivers\FileStatusInterface;
use App\Services\PlayerDrivers\FolderOrganizerInterface;
use App\Services\PlayerDrivers\RemoteUploadInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
    private const REMOTE_HOSTS = ['doodstream', 'streamwish', 'streamtape'];

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
    private CodecDetector $codecDetector;

    public function __construct(
        PlayerTransferService $transferService,
        RealDebridClient $debrid,
        Aria2Client $aria2,
        CodecDetector $codecDetector,
    ) {
        $this->transferService = $transferService;
        $this->debrid = $debrid;
        $this->aria2 = $aria2;
        $this->codecDetector = $codecDetector;
        $this->filesPerRun = max(1, (int) env('TORRENT_FILES_PER_RUN', 10));
        $this->defaultDailyLimit = max(1, (int) env('TORRENT_DEFAULT_DAILY_LIMIT', 200));
        // 0 = download the whole torrent at once (default). >0 = "slice" mode:
        // download N files at a time (for small-disk VPS). Only applies to aria2.
        $this->aria2Slice = max(0, (int) env('TORRENT_ARIA2_SLICE', 0));
    }

    /**
     * Hosts that convert HEVC/x265 poorly and should be skipped for such files.
     * Configurable via TORRENT_HEVC_SKIP_HOSTS (comma-separated). Default:
     * streamtape (known to get stuck "converting" on HEVC). Empty = never skip.
     *
     * @return list<string>
     */
    private function hevcSkipHosts(): array
    {
        $raw = strtolower((string) env('TORRENT_HEVC_SKIP_HOSTS', 'streamtape'));

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
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

    /** Default provider from config (aria2 = VPS télécharge directement). */
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
            $job->refresh(); // reload in-memory state after counter updates

            // Done when no episode file is still awaiting its players.
            $remaining = $job->files()->where('status', 'pending')->count();
            $queued = TorrentFileTarget::query()
                ->whereHas('file', fn ($q) => $q->where('torrent_download_id', $job->id))
                ->whereIn('status', ['pending', 'queued', 'uploading'])
                ->count();

            if ($remaining === 0 && $queued === 0) {
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
        } catch (RealDebridPermanentException $e) {
            // Permanent RD error — attempt automatic fallback to aria2 if the
            // error is content-related (infringing code 35) and aria2 is
            // available. This lets the admin's paid RD work for what it can,
            // and aria2 picks up the rest transparently.
            if ($e->isInfringing() && $this->canFallbackToAria2($job)) {
                $this->fallbackToAria2($job, $e);
            } else {
                $job->update(['attempts' => 99]);
                $job->markFailed($this->permanentErrorMessage($e));
            }
            $this->cleanupDownloadArtifacts($job);
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
            // Determine what to pass to aria2: uploaded file path, magnet, or URL.
            $source = trim($job->torrent_url ?? '');
            if ($job->torrent_file_path && is_file($job->torrent_file_path)) {
                // aria2 can accept a local .torrent file path directly.
                $source = $job->torrent_file_path;
            }
            if ($source === '') {
                $job->markFailed('Aucune source torrent pour aria2 (ni fichier, ni magnet, ni URL).');

                return false;
            }
            $gid = $this->aria2->add($source);
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
            // Check active torrent limit before submitting a new one.
            try {
                $active = $this->debrid->activeTorrentCount();
                if ($active['limit'] > 0 && $active['nb'] >= $active['limit']) {
                    // Don't fail — just skip this pass. The slot will free up.
                    Log::info("Real-Debrid: {$active['nb']}/{$active['limit']} torrents actifs — batch #{$job->id} attend un slot.");

                    return false;
                }
            } catch (Throwable) {
                // activeCount endpoint may fail — proceed anyway.
            }

            try {
                $url = trim($job->torrent_url ?? '');
                $filePath = $job->torrent_file_path;

                if ($filePath && is_file($filePath)) {
                    // Uploaded .torrent file — send binary directly to RD.
                    $binary = file_get_contents($filePath);
                    if ($binary === false) {
                        $job->markFailed("Impossible de lire le fichier .torrent : {$filePath}");

                        return false;
                    }
                    $id = $this->debrid->addTorrentFile($binary);
                } elseif (str_starts_with(strtolower($url), 'magnet:')) {
                    $id = $this->debrid->addMagnet($url);
                } elseif ($url !== '') {
                    $id = $this->debrid->addTorrentUrl($url);
                } else {
                    $job->markFailed('Aucune source torrent (ni fichier, ni magnet, ni URL).');

                    return false;
                }
            } catch (RealDebridPermanentException $e) {
                // Infringing content: try automatic fallback to aria2.
                if ($e->isInfringing() && $this->canFallbackToAria2($job)) {
                    $this->fallbackToAria2($job, $e);

                    return false;
                }
                // Other permanent errors: no fallback possible.
                $job->update(['attempts' => 99]); // prevent automatic retry
                $job->markFailed($this->permanentErrorMessage($e));

                return false;
            } catch (\RuntimeException $e) {
                // Transient errors that exhausted retries — let the batch
                // retry from the top on the next pass.
                throw $e;
            }
            $job->markResolving($id);

            return false; // poll on the next pass
        }

        if (! $job->debrid_id) {
            $job->markFailed('Résolution sans identifiant Real-Debrid.');

            return false;
        }

        // Poll torrent status.
        try {
            $info = $this->debrid->info($job->debrid_id);
        } catch (RealDebridPermanentException $e) {
            $job->update(['attempts' => 99]);
            $job->markFailed($this->permanentErrorMessage($e));

            return false;
        }

        $status = $info['status'] ?? 'error';
        $progress = (int) ($info['progress'] ?? 0);
        if ($progress !== $job->debrid_progress) {
            $job->update(['debrid_progress' => $progress]);
        }

        switch ($status) {
            case 'waiting_files_selection':
                // Keep only the video files (skip samples/nfo/images).
                $ids = $this->videoFileIds($info['files'] ?? []);
                try {
                    $this->debrid->selectFiles($job->debrid_id, $ids !== '' ? $ids : 'all');
                } catch (RealDebridPermanentException $e) {
                    $job->update(['attempts' => 99]);
                    $job->markFailed($this->permanentErrorMessage($e));
                    $this->debrid->deleteTorrent($job->debrid_id);

                    return false;
                }

                return false;

            case 'magnet_conversion':
                // Detect stuck magnet conversion (no seeders). If stuck for
                // too long, fail gracefully instead of polling forever.
                $stuckMinutes = (int) env('REALDEBRID_MAGNET_TIMEOUT_MINUTES', 30);
                if ($stuckMinutes > 0 && $job->updated_at) {
                    $elapsed = Carbon::now()->diffInMinutes($job->updated_at);
                    if ($elapsed >= $stuckMinutes && $progress === 0) {
                        $job->markFailed(
                            "Real-Debrid: conversion du magnet bloquée depuis {$elapsed} min (pas de seeders?). "
                            . 'Essayez un autre torrent avec plus de sources.'
                        );
                        $this->debrid->deleteTorrent($job->debrid_id);

                        return false;
                    }
                }

                return false; // still converting — wait.

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

            case 'dead':
                $job->markFailed(
                    'Real-Debrid: torrent mort (aucun seeder disponible). '
                    . 'Essayez un magnet mieux seedé ou attendez que les sources reviennent.'
                );
                $this->debrid->deleteTorrent($job->debrid_id);

                return false;

            case 'virus':
                $job->update(['attempts' => 99]); // no point retrying
                $job->markFailed('Real-Debrid: fichier signalé comme virus/malware par le scan.');
                $this->debrid->deleteTorrent($job->debrid_id);

                return false;

            case 'magnet_error':
                $job->markFailed(
                    'Real-Debrid: erreur de magnet (lien invalide ou expiré). '
                    . 'Vérifiez le hash/magnet et réessayez.'
                );
                $this->debrid->deleteTorrent($job->debrid_id);

                return false;

            default: // error, or unknown
                $errorDetail = $info['error'] ?? $status;
                $job->markFailed("Real-Debrid: statut '{$status}'" . ($errorDetail !== $status ? " — {$errorDetail}" : '.'));
                $this->debrid->deleteTorrent($job->debrid_id);

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
     * Handles per-file errors gracefully: infringing files are marked as
     * permanently failed, transient errors are retryable.
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
            $status = 'pending';
            if ($rdLink) {
                try {
                    $directUrl = $this->debrid->unrestrict($rdLink);
                } catch (RealDebridPermanentException $e) {
                    // Infringing or permanently blocked at the file level.
                    $error = $e->isInfringing()
                        ? 'Fichier bloqué par Real-Debrid (signalement ayant-droit). Ce fichier ne peut pas être débridé.'
                        : 'Real-Debrid unrestrict: ' . mb_substr($e->getMessage(), 0, 200);
                    $status = 'failed';
                    Log::warning("RD unrestrict permanent error for file '{$fileName}' in batch #{$job->id}", [
                        'error_code' => $e->rdErrorCode,
                    ]);
                } catch (Throwable $e) {
                    // Transient/other errors — file stays pending for retry.
                    $error = 'Real-Debrid unrestrict: ' . mb_substr($e->getMessage(), 0, 200);
                    $status = 'failed';
                }
            } else {
                $error = 'Real-Debrid: aucun lien pour ce fichier.';
                $status = 'failed';
            }

            TorrentFile::create([
                'torrent_download_id' => $job->id,
                'source_url' => $directUrl ?? '',
                'file_name' => $fileName,
                'detected_episode' => $epNum,
                'episode_id' => $episodeId,
                'language' => $job->language,
                'status' => $directUrl ? 'pending' : $status,
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
        $hevcSkip = $this->hevcSkipHosts();

        foreach ($job->files()->where('status', 'pending')->get() as $file) {
            if ($file->episode_id === null) {
                $file->markSkipped('Aucun épisode correspondant — mappez-le manuellement puis réessayez.');

                continue;
            }

            // Detect the codec once (filename heuristic + optional ffprobe).
            // No local file needed — the release name usually says x265/x264.
            if ($file->video_codec === null) {
                $codec = $this->codecDetector->detect($file->file_name, $file->local_path, $file->source_url);
                if ($codec !== null) {
                    $file->update(['video_codec' => $codec]);
                }
            }

            $isHevc = $this->codecDetector->isHevc($file->video_codec);
            $existing = $file->targets()->pluck('platform')->all();
            $eligible = 0;

            foreach ($platforms as $platform) {
                // Skip hosts that convert HEVC poorly (e.g. Streamtape) — this
                // avoids the "stuck converting" problem for x265 anime files.
                if ($isHevc && in_array($platform, $hevcSkip, true)) {
                    continue;
                }
                $eligible++;
                if (! in_array($platform, $existing, true)) {
                    TorrentFileTarget::create([
                        'torrent_file_id' => $file->id,
                        'platform' => $platform,
                        'status' => 'pending',
                    ]);
                }
            }

            // Every chosen player was skipped for codec reasons → flag it.
            if ($eligible === 0 && empty($existing)) {
                $file->markSkipped(
                    'Codec ' . strtoupper((string) $file->video_codec) . ' non supporté par le(s) lecteur(s) choisi(s) '
                    . '(ex: Streamtape gère mal le HEVC/x265). Choisissez un autre lecteur (doodstream, vidmoly).'
                );
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
        // Recover targets stuck in "uploading" (e.g. a PHP timeout killed the
        // process mid-upload) so they can be retried instead of blocking forever.
        TorrentFileTarget::query()
            ->whereHas('file', fn ($q) => $q->where('torrent_download_id', $job->id))
            ->where('status', 'uploading')
            ->where('updated_at', '<', now()->subMinutes(15))
            ->update(['status' => 'pending']);

        // Timeout queued targets that have been waiting too long (the player
        // never confirmed done/error). Mark them failed so the admin can retry
        // with a different link or the same one.
        $queuedTimeout = max(10, (int) env('TORRENT_QUEUED_TIMEOUT_MINUTES', 30));
        TorrentFileTarget::query()
            ->whereHas('file', fn ($q) => $q->where('torrent_download_id', $job->id))
            ->where('status', 'queued')
            ->where('updated_at', '<', now()->subMinutes($queuedTimeout))
            ->update([
                'status' => 'failed',
                'error' => "Timeout — le lecteur n'a pas confirmé après {$queuedTimeout} min. Réessayez avec un nouveau lien.",
            ]);

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
                // Explain WHY it's still pending (quota reached) so the admin
                // isn't left wondering. Status stays pending → resumes tomorrow.
                $limit = $this->hostDailyLimit($platform);
                $target->update([
                    'error' => "Quota journalier {$platform} atteint ({$limit}/jour). Reprend demain — ou augmentez TRANSFER_" . strtoupper($platform) . '_DAILY_LIMIT.',
                ]);

                continue; // this player is capped today — try again tomorrow
            }
            $creds = $this->getCredentials($platform);
            if (empty($creds)) {
                $target->markFailed("Aucun identifiant pour {$platform} (TRANSFER_" . strtoupper($platform) . '_*).');

                continue;
            }

            // Atomic claim: flip pending → uploading in a single UPDATE so two
            // concurrent processJob() passes can't submit the SAME episode to
            // the SAME player twice (which would create a duplicate upload).
            $claimed = TorrentFileTarget::where('id', $target->id)
                ->where('status', 'pending')
                ->update(['status' => 'uploading']);
            if ($claimed === 0) {
                continue; // another pass already grabbed this target
            }
            $target->status = 'uploading';

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

            $driver = $this->transferService->driverByName($platform);
            if (! $driver) {
                $target->markFailed("Aucun driver pour la plateforme : {$platform}");

                return;
            }

            // A per-target source URL override (set when retrying ONE player
            // with a new link) ALWAYS wins — even on an aria2 batch whose local
            // file was already deleted after the first upload.
            $overrideUrl = trim((string) $target->source_url);

            // --- aria2: upload the locally-downloaded file ---
            // Only when there's no per-target override and the local file still exists.
            if ($overrideUrl === '' && $file->local_path) {
                $path = $file->local_path;
                if (! $path || ! is_file($path)) {
                    $target->markFailed("Fichier introuvable sur le disque : {$path}");

                    return;
                }
                if (filesize($path) < self::MIN_VIDEO_SIZE) {
                    $target->markFailed('Fichier trop petit (probablement corrompu).');

                    return;
                }
                $result = $driver->upload($path, $title, $creds);
                $embed = $result['embed_url'] ?? null;
                if (! $embed) {
                    $target->markFailed('Upload effectué mais aucune URL embed retournée.');

                    return;
                }
                $target->markDone($embed, $result['file_code'] ?? null);
                // NB: the DB (episode_links) is NOT updated here — the admin
                // syncs it manually via the "Mettre à jour la base" button.
                $this->bumpDaily($platform);

                return;
            }

            // --- Direct link / retry override: the host fetches the link itself ---
            // Prefer the target's own source URL (retry with a new link),
            // otherwise fall back to the file's source URL.
            $sourceUrl = $overrideUrl !== '' ? $overrideUrl : trim((string) $file->source_url);
            if ($sourceUrl === '') {
                $target->markFailed('Aucune source URL pour ce fichier (fichier local supprimé et pas de lien de remplacement).');

                return;
            }

            if ($driver instanceof RemoteUploadInterface) {
                // Remote upload: the host fetches the link itself (async).
                // Pass the source URL directly — do NOT try to extract a direct
                // URL first (that would make an extra HTTP request to the source,
                // which some hosts interpret as a download attempt).
                $result = $driver->remoteUpload($sourceUrl, $title, $creds);
                $result['platform'] = $platform;
                $state = $result['state'] ?? 'queued';

                if ($state === 'done' && ! empty($result['embed_url'])) {
                    $target->markDone($result['embed_url'], $result['file_code'] ?? null);
                    $this->bumpDaily($platform);

                    return;
                }

                // Async: host is now fetching the link; poll it later.
                $target->markQueued($result['job_id'] ?? null);
                $this->bumpDaily($platform);
            } else {
                // No remote upload support (e.g. hosts sans remote upload): download locally
                // then upload. This uses disk/bandwidth but is the only option.
                $tempDir = storage_path('app/temp-transfers');
                if (! is_dir($tempDir)) {
                    @mkdir($tempDir, 0755, true);
                }
                $tempFile = $tempDir . '/target_' . uniqid() . '_' . time() . '.mp4';

                try {
                    // Download the source file (try yt-dlp first, fallback to HTTP).
                    $downloaded = $this->transferService->downloadWithYtDlp($sourceUrl, $tempFile);
                    if (! $downloaded) {
                        // Direct HTTP download as fallback.
                        $dlResp = Http::timeout(600)
                            ->withOptions(['sink' => $tempFile, 'stream' => true])
                            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'])
                            ->get($sourceUrl);
                        $downloaded = $dlResp->successful() && is_file($tempFile) && filesize($tempFile) > self::MIN_VIDEO_SIZE;
                    }

                    if (! $downloaded || ! is_file($tempFile) || filesize($tempFile) < self::MIN_VIDEO_SIZE) {
                        $target->markFailed("Téléchargement échoué pour {$platform} (fichier trop petit ou inexistant).");

                        return;
                    }

                    // Upload to the target player.
                    $result = $driver->upload($tempFile, $title, $creds);
                    $embed = $result['embed_url'] ?? null;
                    if (! $embed) {
                        $target->markFailed('Upload effectué mais aucune URL embed retournée.');

                        return;
                    }
                    $target->markDone($embed, $result['file_code'] ?? null);
                    $this->bumpDaily($platform);
                } finally {
                    if (is_file($tempFile)) {
                        @unlink($tempFile);
                    }
                }
            }
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
                            // DB sync is manual (admin button) — not here.
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
            $pending = $file->targets->whereIn('status', ['pending', 'queued', 'uploading'])->count();
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

    /**
     * Attach the uploaded embed URL to the episode as a playable link.
     *
     * Avoids duplicates: if an identical (episode + url) link already exists,
     * it is NOT re-created.
     *
     * @return bool true if a new link was created, false if skipped/duplicate
     */
    private function attachToEpisode(TorrentFile $file, string $embedUrl, string $platform): bool
    {
        if (! $file->episode_id) {
            Log::warning("TorrentFile #{$file->id}: pas d'episode_id — lien non enregistré en base.");

            return false;
        }

        $host = parse_url($embedUrl, PHP_URL_HOST);
        $host = is_string($host) ? preg_replace('/^www\./', '', $host) : $platform;
        $language = $file->language ?? 'VOSTFR';

        // Enforce ONE link per player per language: remove any existing link
        // of the SAME player (resolved from its host domain) for this episode
        // + language, then insert the new one ("replace" instead of stacking).
        foreach (EpisodeLink::where('episode_id', $file->episode_id)->where('language', $language)->get() as $link) {
            $linkPlatform = $this->platformForHost($link->host) ?? $link->host;
            if ($linkPlatform !== $platform) {
                continue;
            }
            if ($link->url === $embedUrl) {
                return false; // identical link already present — nothing to do
            }
            $link->delete(); // stale link for this player → replace it
        }

        $position = (int) EpisodeLink::where('episode_id', $file->episode_id)
            ->where('language', $language)
            ->max('position') + 1;

        EpisodeLink::create([
            'episode_id' => $file->episode_id,
            'language' => $language,
            'position' => $position,
            'url' => $embedUrl,
            'host' => $host,
        ]);

        Log::info("Lien créé: Episode #{$file->episode_id} → {$host} ({$language})");

        return true;
    }

    /** Resolve the player/platform name from a stored link host domain. */
    private function platformForHost(?string $host): ?string
    {
        if (! $host) {
            return null;
        }

        return $this->transferService->driverForHost($host)?->name();
    }

    /**
     * Deduplicate episode links: keep ONE link per (player, language) per
     * episode, deleting the extra/similar ones. The most recent link (highest
     * id) is kept. Optionally scoped to a single anime.
     *
     * When $keepOnlyManaged is true, ALSO delete every link whose player is
     * NOT one of the managed remote hosts (doodstream/vidmoly/streamtape)
     * — i.e. keep only "mes lecteurs" and remove all the rest.
     *
     * @return array{removed:int, episodes:int, pruned:int}
     */
    public function deduplicateLinks(?int $animeId = null, bool $keepOnlyManaged = false): array
    {
        $removed = 0;
        $episodesAffected = 0;
        $pruned = 0;
        $managed = self::REMOTE_HOSTS;

        $query = Episode::query()->whereHas('links');
        if ($animeId !== null) {
            $query->whereHas('season', fn ($q) => $q->where('anime_id', $animeId));
        }

        $query->with('links')->chunkById(200, function ($episodes) use (&$removed, &$episodesAffected, &$pruned, $keepOnlyManaged, $managed) {
            foreach ($episodes as $episode) {
                $seen = [];
                $toDelete = [];
                // Newest first so the most recent link of each player is kept.
                foreach ($episode->links->sortByDesc('id') as $link) {
                    $platform = $this->platformForHost($link->host) ?? strtolower((string) $link->host);

                    // Aggressive prune: drop links from unmanaged players.
                    if ($keepOnlyManaged && ! in_array($platform, $managed, true)) {
                        $toDelete[] = $link->id;
                        $pruned++;

                        continue;
                    }

                    $key = $platform . '|' . strtolower((string) ($link->language ?? ''));
                    if (isset($seen[$key])) {
                        $toDelete[] = $link->id;
                    } else {
                        $seen[$key] = true;
                    }
                }
                if (! empty($toDelete)) {
                    EpisodeLink::whereIn('id', $toDelete)->delete();
                    $removed += count($toDelete);
                    $episodesAffected++;
                }
            }
        });

        return ['removed' => $removed, 'episodes' => $episodesAffected, 'pruned' => $pruned];
    }

    /**
     * Build the upload title sent to the PLAYER (this is the "organization on
     * the player side"): the file appears named as
     *   Anime_Name/Saison X/EYY [LANG]
     * so it's cleanly organized in your player account, not on your disk.
     */
    private function buildTitle(TorrentDownload $job, TorrentFile $file): string
    {
        $episode = $file->episode;
        $season = $episode?->season;
        $anime = $season?->anime ?? $job->anime;

        if ($anime) {
            $seasonNo = ($season->position ?? 0) + 1;
            $epNo = str_pad((string) ($episode->index ?? $file->detected_episode ?? 0), 2, '0', STR_PAD_LEFT);
            $lang = $file->language ?? 'VOSTFR';

            // "Anime/Saison 1/E05 [VOSTFR]" — the "/" gives a folder-like
            // organization on hosts that support it, and a clean title elsewhere.
            return sprintf('%s/Saison %d/E%s [%s]', $anime->name, $seasonNo, $epNo, $lang);
        }

        return $job->title ?: pathinfo($file->file_name ?? 'video', PATHINFO_FILENAME);
    }

    /* =====================================================================
     * MANUAL DATABASE SYNC
     *
     * The DB (episode_links) is NOT updated automatically after upload. The
     * admin verifies on the player that everything is live, then clicks the
     * "Mettre à jour la base" button which calls syncLinksToDatabase().
     * =================================================================== */

    /**
     * Create episode_links in the DB from the batch's completed uploads.
     *
     * Iterates every done per-player target that has an embed URL and attaches
     * it to its episode. Idempotent: existing links are skipped. Called only
     * on demand (admin button), never automatically.
     *
     * @return array{links:int, organized:int} links created + files organized on player
     */
    public function syncLinksToDatabase(TorrentDownload $job): array
    {
        $created = 0;
        $hasTargets = \Illuminate\Support\Facades\Schema::hasTable('torrent_file_targets');

        // Multi-target: one embed URL per (episode file × player).
        if ($hasTargets) {
            $targets = TorrentFileTarget::query()
                ->whereHas('file', fn ($q) => $q->where('torrent_download_id', $job->id))
                ->where('status', 'done')
                ->whereNotNull('embed_url')
                ->with('file')
                ->get();

            foreach ($targets as $target) {
                $file = $target->file;
                if (! $file || ! $file->episode_id || ! $target->embed_url) {
                    continue;
                }

                // Verify the file is really on the host before publishing the
                // link (if the host exposes a file-info endpoint).
                if (! empty($target->file_code)) {
                    $driver = $this->transferService->driverByName($target->platform);
                    if ($driver instanceof FileStatusInterface) {
                        $exists = $driver->fileExists((string) $target->file_code, $this->getCredentials($target->platform));
                        if ($exists === false) {
                            Log::warning("Sync: fichier absent sur {$target->platform} (code {$target->file_code}) — lien ignoré.");

                            continue;
                        }
                    }
                }

                if ($this->attachToEpisode($file, $target->embed_url, $target->platform)) {
                    $created++;
                }
            }
        }

        // Legacy single-target fallback: files that stored embed_url directly.
        $files = $job->files()
            ->where('status', 'done')
            ->whereNotNull('embed_url')
            ->whereNotNull('episode_id')
            ->get();

        foreach ($files as $file) {
            $host = parse_url($file->embed_url, PHP_URL_HOST) ?: '';
            if ($this->attachToEpisode($file, $file->embed_url, $host)) {
                $created++;
            }
        }

        $job->update(['links_synced' => true]);

        // Leave the "processing" state if nothing is still uploading, so the
        // batch shows a clean "done" status after the manual sync.
        $stillWorking = false;
        if ($hasTargets) {
            $stillWorking = TorrentFileTarget::query()
                ->whereHas('file', fn ($q) => $q->where('torrent_download_id', $job->id))
                ->whereIn('status', ['pending', 'queued', 'uploading'])
                ->exists();
        }
        if (! $stillWorking) {
            $stillWorking = $job->files()->where('status', 'pending')->exists();
        }
        if (! $stillWorking && ! $job->isDone() && ! $job->isFailed()) {
            $job->markDone();
        }

        // Also organize the already-uploaded files into folders ON the player
        // (Anime/Saison X/Episode YY). Best-effort — never blocks the sync.
        $organized = $this->organizeOnPlayer($job);

        return ['links' => $created, 'organized' => $organized];
    }

    /* =====================================================================
     * ORGANIZE ON PLAYER (move/rename uploaded files into host folders)
     *
     * Reorganizes files that are ALREADY on the player host into a folder
     * structure Anime/Saison X/ + a clean title (Episode YY). Does NOT change
     * the file_code, so existing embed links keep working. Best-effort: hosts
     * without folder support (or a failing API call) are skipped silently.
     * =================================================================== */

    /**
     * @return int number of files (re)organized on their player host
     */
    public function organizeOnPlayer(TorrentDownload $job): int
    {
        if (! filter_var(env('TORRENT_ORGANIZE_ON_PLAYER', true), FILTER_VALIDATE_BOOLEAN)) {
            return 0;
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('torrent_file_targets')) {
            return 0;
        }

        $organized = 0;

        $targets = TorrentFileTarget::query()
            ->whereHas('file', fn ($q) => $q->where('torrent_download_id', $job->id))
            ->where('status', 'done')
            ->whereNotNull('file_code')
            ->with('file.episode.season.anime')
            ->get();

        foreach ($targets as $target) {
            $driver = $this->transferService->driverByName($target->platform);
            if (! $driver instanceof FolderOrganizerInterface) {
                continue; // this host doesn't support folder organization
            }

            $file = $target->file;
            if (! $file) {
                continue;
            }

            $creds = $this->getCredentials($target->platform);
            if (empty($creds)) {
                continue;
            }

            [$segments, $title] = $this->playerFolderPath($job, $file);
            if (empty($segments)) {
                continue; // can't organize without the anime/season context
            }

            try {
                $folderId = $driver->ensureFolderPath($segments, $creds);
                if ($driver->organizeFile((string) $target->file_code, $folderId, $title, $creds)) {
                    $organized++;
                }
            } catch (Throwable $e) {
                Log::warning("Organisation lecteur echouee ({$target->platform}, file {$target->file_code}): "
                    . mb_substr($e->getMessage(), 0, 150));
            }
        }

        return $organized;
    }

    /**
     * Compute the player folder path + title for a file:
     *   segments = [Anime_Name, "Saison X"]   title = "Episode YY [LANG]"
     *
     * @return array{0: list<string>, 1: string|null}
     */
    private function playerFolderPath(TorrentDownload $job, TorrentFile $file): array
    {
        $episode = $file->episode;
        $season = $episode?->season;
        $anime = $season?->anime ?? $job->anime;

        if (! $anime) {
            return [[], null];
        }

        $seasonNo = ($season->position ?? 0) + 1;
        $epNo = str_pad((string) ($episode->index ?? $file->detected_episode ?? 0), 2, '0', STR_PAD_LEFT);
        $lang = $file->language ?? 'VOSTFR';

        return [
            [$anime->name, "Saison {$seasonNo}"],
            "Episode {$epNo} [{$lang}]",
        ];
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
            'streamwish' => array_filter([
                'api_key' => env('TRANSFER_STREAMWISH_API_KEY', ''),
                'api_base' => env('TRANSFER_STREAMWISH_API_BASE', 'https://api.streamwish.com/api'),
                'embed_base' => env('TRANSFER_STREAMWISH_EMBED_BASE', 'https://streamwish.to'),
                'timeout' => (int) env('TRANSFER_STREAMWISH_TIMEOUT', 900),
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
            default => [],
        };
    }

    /* =====================================================================
     * AUTOMATIC ARIA2 FALLBACK (when RD blocks a torrent, code 35)
     *
     * If aria2 is available on the server and the env allows fallback
     * (TORRENT_ARIA2_FALLBACK=true, default), the batch is transparently
     * re-routed to aria2 instead of failing. The admin sees the provider
     * switch in the panel and the download proceeds without manual action.
     * =================================================================== */

    /**
     * Whether automatic fallback from RD to aria2 is possible for this job.
     */
    private function canFallbackToAria2(TorrentDownload $job): bool
    {
        // Must be enabled (default: true if aria2 is available).
        if (! filter_var(env('TORRENT_ARIA2_FALLBACK', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        // Must have a torrent source (magnet or URL) to re-download.
        if (! $job->usesTorrent()) {
            return false;
        }

        // Must already be using RD (no point falling back to itself).
        if ($job->provider !== 'realdebrid') {
            return false;
        }

        // aria2 must be operational.
        return $this->aria2->isAvailable();
    }

    /**
     * Switch a Real-Debrid batch to aria2 after a permanent RD rejection.
     *
     * Resets the batch state so it restarts from scratch using the aria2
     * pipeline. The original torrent_url is preserved.
     */
    private function fallbackToAria2(TorrentDownload $job, RealDebridPermanentException $e): void
    {
        Log::info("Batch #{$job->id}: Real-Debrid a refusé (code {$e->rdErrorCode}) — bascule automatique sur aria2.", [
            'title' => $job->title,
            'torrent_url' => mb_substr($job->torrent_url ?? '', 0, 80),
        ]);

        // Clean up any partial RD state.
        if ($job->debrid_id) {
            $this->debrid->deleteTorrent($job->debrid_id);
        }

        // Switch provider and reset to pending so the next processJob() pass
        // picks it up via the aria2 pipeline.
        $job->update([
            'provider' => 'aria2',
            'status' => 'pending',
            'debrid_id' => null,
            'debrid_progress' => 0,
            'aria2_gid' => null,
            'error' => null,
            // Keep attempts reasonable so the batch is retried, but record
            // that a fallback happened (visible in error history).
            'attempts' => 0,
        ]);
    }

    /* =====================================================================
     * ERROR MESSAGES (human-friendly, actionable)
     * =================================================================== */

    /**
     * Build a clear, actionable error message from a RealDebridPermanentException.
     */
    private function permanentErrorMessage(RealDebridPermanentException $e): string
    {
        if ($e->isInfringing()) {
            return 'Contenu bloqué par Real-Debrid (signalement ayant-droit / DMCA, code 35). '
                . 'Ce hash est définitivement refusé. Essayez un autre magnet/source pour cet anime.';
        }

        if ($e->isIpBlocked()) {
            return 'Adresse IP rejetée par Real-Debrid (code 22). '
                . 'RD bloque les VPS/cloud. Utilisez un token obtenu depuis votre IP résidentielle, '
                . 'ou activez le "Remote Traffic" dans les paramètres RD.';
        }

        if ($e->isAuthError()) {
            return 'Authentification Real-Debrid échouée (token invalide ou compte verrouillé, code '
                . $e->rdErrorCode . '). Vérifiez REALDEBRID_API_TOKEN dans .env.';
        }

        return match ($e->rdErrorCode) {
            29 => 'Torrent trop volumineux pour Real-Debrid (code 29). Essayez un torrent plus petit.',
            30 => 'Fichier .torrent invalide (code 30). Le fichier est peut-être corrompu ou mal encodé.',
            28 => 'Type de fichier non autorisé par Real-Debrid (code 28).',
            36 => 'Limite d\'utilisation quotidienne Real-Debrid atteinte (Fair Usage). Réessayez demain.',
            default => mb_substr($e->getMessage(), 0, 300),
        };
    }
}
