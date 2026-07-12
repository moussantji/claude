<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\EpisodeLink;
use App\Models\TorrentDownload;
use App\Models\TorrentFile;
use RuntimeException;
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
            $credentials = $this->getCredentials($job->target_platform);
            if (empty($credentials)) {
                $job->markFailed(
                    "No credentials for {$job->target_platform}. "
                    . 'Set TRANSFER_' . strtoupper($job->target_platform) . '_* in .env'
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

            // Stage B: make each file live, then poll the async (RD) ones.
            $this->submitPending($job, $credentials);
            $this->pollQueued($job, $credentials);

            $job->refreshCounters();

            // Done when nothing is left in a non-terminal state.
            $remaining = $job->files()->whereIn('status', ['pending', 'queued'])->count();
            if ($remaining === 0) {
                $job->markDone();
                $this->cleanupDownloadArtifacts($job);
            }
        } catch (Throwable $e) {
            $job->markFailed(mb_substr($e->getMessage(), 0, 500));
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

            return false; // poll on the next pass
        }

        if (! $job->aria2_gid) {
            $job->markFailed('Téléchargement sans identifiant aria2.');

            return false;
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

                    return false;
                }
                $job->markProcessing();

                return true;

            case 'error':
                $job->markFailed($poll['error'] ?? 'aria2c: échec du téléchargement.');

                return false;

            default:
                $job->markFailed("aria2c: statut '{$poll['status']}'.");

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
            $url = trim($job->torrent_url);
            $id = str_starts_with(strtolower($url), 'magnet:')
                ? $this->debrid->addMagnet($url)
                : $this->debrid->addTorrentUrl($url);
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

    /**
     * Send pending links to the target host (remote upload).
     */
    private function submitPending(TorrentDownload $job, array $credentials): void
    {
        $pending = $job->files()
            ->where('status', 'pending')
            ->orderBy('detected_episode')
            ->limit($this->filesPerRun)
            ->get();

        foreach ($pending as $file) {
            $this->submitFile($job, $file, $credentials);
        }
    }

    /**
     * Poll queued links; finalize the ones the host has finished fetching.
     */
    private function pollQueued(TorrentDownload $job, array $credentials): void
    {
        $queued = $job->files()
            ->where('status', 'queued')
            ->limit($this->filesPerRun)
            ->get();

        foreach ($queued as $file) {
            $this->pollFile($job, $file, $credentials);
        }
    }

    /**
     * Make one file live on the host.
     *   • local_path present (aria2) → upload the local file, then delete it.
     *   • otherwise (RD/direct link) → remote upload (host fetches the link).
     */
    private function submitFile(TorrentDownload $job, TorrentFile $file, array $credentials): void
    {
        try {
            if ($file->episode_id === null) {
                $file->markSkipped('Aucun épisode correspondant — mappez-le manuellement puis réessayez.');

                return;
            }

            $title = $this->buildTitle($job, $file);

            // aria2 provider: upload the locally-downloaded file to the host.
            if ($file->local_path) {
                $this->uploadLocalFile($job, $file, $title, $credentials);

                return;
            }

            // remote upload: the host fetches the direct link itself.
            $result = $this->transferService->transferRemote(
                trim((string) $file->source_url),
                $title,
                $credentials,
                $job->target_platform,
            );

            $state = $result['state'] ?? 'queued';

            if ($state === 'done' && ! empty($result['embed_url'])) {
                $file->markDone($result['embed_url'], $result['file_code'] ?? null);
                $this->attachToEpisode($file, $result['embed_url'], $job->target_platform);

                return;
            }

            // Async: the host is now fetching the link; poll it later.
            $file->markQueued($result['job_id'] ?? null);
        } catch (Throwable $e) {
            $file->markFailed(mb_substr($e->getMessage(), 0, 500));
        }
    }

    /**
     * Upload a locally-downloaded file to the host, then free the disk space.
     */
    private function uploadLocalFile(TorrentDownload $job, TorrentFile $file, string $title, array $credentials): void
    {
        $path = $file->local_path;
        if (! $path || ! is_file($path)) {
            $file->markFailed("Fichier introuvable sur le disque : {$path}");

            return;
        }
        if (filesize($path) < self::MIN_VIDEO_SIZE) {
            $file->markFailed('Fichier trop petit (probablement corrompu).');

            return;
        }

        $driver = $this->transferService->driverByName($job->target_platform);
        if (! $driver) {
            $file->markFailed("Aucun driver pour la plateforme : {$job->target_platform}");

            return;
        }

        $result = $driver->upload($path, $title, $credentials);
        $embed = $result['embed_url'] ?? null;
        if (! $embed) {
            $file->markFailed('Upload effectué mais aucune URL embed retournée.');

            return;
        }

        $file->markDone($embed, $result['file_code'] ?? null);
        $this->attachToEpisode($file, $embed, $job->target_platform);

        // Free disk space immediately after a successful upload.
        $this->deleteLocalFile($path);
    }

    /**
     * Check whether the host has finished fetching a queued link.
     */
    private function pollFile(TorrentDownload $job, TorrentFile $file, array $credentials): void
    {
        try {
            if (! $file->remote_job_id) {
                // Nothing to poll against — re-submit on the next pass.
                $file->update(['status' => 'pending']);

                return;
            }

            $status = $this->transferService->remoteStatus(
                $job->target_platform,
                $file->remote_job_id,
                $credentials,
            );

            switch ($status['state'] ?? 'pending') {
                case 'done':
                    $embed = $status['embed_url'] ?? null;
                    if ($embed) {
                        $file->markDone($embed, $status['file_code'] ?? null);
                        $this->attachToEpisode($file, $embed, $job->target_platform);
                    } else {
                        $file->markFailed('Host reported done but returned no embed URL.');
                    }
                    break;

                case 'error':
                    $file->markFailed($status['error'] ?? 'Host reported an error.');
                    break;

                // 'pending' → still fetching, leave queued.
            }
        } catch (Throwable $e) {
            $file->markFailed(mb_substr($e->getMessage(), 0, 500));
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
     * Remove any leftover downloaded files for a finished batch, plus the
     * (now empty) torrent sub-folder when applicable.
     */
    private function cleanupDownloadArtifacts(TorrentDownload $job): void
    {
        if (! $job->usesAria2()) {
            return;
        }

        $dir = $this->aria2->downloadDir();
        foreach ($job->files as $file) {
            if (! $file->local_path) {
                continue;
            }
            $this->deleteLocalFile($file->local_path);

            $parent = dirname($file->local_path);
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
