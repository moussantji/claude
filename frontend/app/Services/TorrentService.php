<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\EpisodeLink;
use App\Models\TorrentDownload;
use App\Models\TorrentFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Manages torrent downloads via aria2c (JSON-RPC) and uploads the resulting
 * video files to target player platforms via the PlayerTransferService drivers.
 *
 * A torrent can be a SEASON PACK containing many episodes. After the download
 * completes, every video file is scanned, matched to an episode (by parsing
 * the episode number from the filename) and uploaded individually.
 *
 * aria2c should run as a daemon with RPC enabled:
 *   aria2c --enable-rpc --rpc-listen-port=6800 --dir=/tmp/torrents --seed-time=0
 * If RPC is unavailable, it falls back to a blocking CLI download.
 */
class TorrentService
{
    /** Video file extensions we consider as episodes. */
    private const VIDEO_EXTS = ['mp4', 'mkv', 'avi', 'webm', 'ts', 'flv', 'mov', 'm4v', 'wmv'];

    /** Ignore tiny files (samples, nfo, images) below this size (bytes). */
    private const MIN_VIDEO_SIZE = 5_000_000; // 5 MB

    private string $rpcUrl;
    private ?string $rpcSecret;
    private string $downloadDir;
    private int $timeout;
    private int $filesPerRun;
    private PlayerTransferService $transferService;

    public function __construct(PlayerTransferService $transferService)
    {
        $this->transferService = $transferService;
        $this->rpcUrl = env('ARIA2_RPC_URL', 'http://localhost:6800/jsonrpc');
        $this->rpcSecret = env('ARIA2_RPC_SECRET') ?: null;
        $this->downloadDir = rtrim(env('TORRENT_DOWNLOAD_DIR', storage_path('app/torrents')), '/');
        $this->timeout = (int) env('TORRENT_DOWNLOAD_TIMEOUT', 3600); // 1h default
        // Max files to upload per processJob() call (keeps each run bounded).
        $this->filesPerRun = max(1, (int) env('TORRENT_FILES_PER_RUN', 3));

        if (! is_dir($this->downloadDir)) {
            @mkdir($this->downloadDir, 0755, true);
        }
    }

    /* =====================================================================
     * PIPELINE
     * =================================================================== */

    /**
     * Process a single torrent job through its current stage.
     *
     *   pending     → start aria2c download
     *   downloading → poll; when complete, scan files & create torrent_files
     *   uploading   → upload a batch of pending files, attach to episodes
     *                 → done once every file is resolved
     */
    public function processJob(TorrentDownload $job): void
    {
        try {
            if ($job->isPending()) {
                $gid = $this->startDownload($job);
                $job->markDownloading($gid);
            }

            if ($job->isDownloading()) {
                if (! $this->handleDownloading($job)) {
                    return; // still downloading — come back later
                }
            }

            if ($job->isUploading()) {
                $this->handleUploading($job);
            }
        } catch (Throwable $e) {
            $job->markFailed(mb_substr($e->getMessage(), 0, 500));
        }
    }

    /**
     * Poll the download. When complete, scan the torrent's files and create a
     * torrent_files row per video, matching each to an episode.
     *
     * @return bool true when the download is complete and files are ready.
     */
    private function handleDownloading(TorrentDownload $job): bool
    {
        $poll = $this->pollDownload($job->aria2_gid ?? '');
        $job->update(['progress' => $poll['progress']]);

        if (in_array($poll['status'], ['active', 'waiting', 'paused'], true)) {
            return false;
        }

        if ($poll['status'] === 'error') {
            $job->markFailed($poll['error'] ?? 'aria2c download error');

            return false;
        }

        if ($poll['status'] !== 'complete') {
            $job->markFailed("Unexpected aria2c status: {$poll['status']}");

            return false;
        }

        // Download complete → gather all video files in the torrent.
        $videoFiles = $this->collectVideoFiles($poll['files'] ?? []);

        if (empty($videoFiles)) {
            $job->markFailed('Download complete but no video file found in the torrent.');

            return false;
        }

        $this->createTorrentFiles($job, $videoFiles);
        $job->update(['total_size' => $poll['file_size'] ?? null]);
        $job->markUploading();
        $job->refreshCounters();

        return true;
    }

    /**
     * Upload a bounded batch of pending files, attaching each to its episode.
     * Marks the job done once no pending files remain.
     */
    private function handleUploading(TorrentDownload $job): void
    {
        $credentials = $this->getCredentials($job->target_platform);
        if (empty($credentials)) {
            $job->markFailed(
                "No credentials configured for {$job->target_platform}. "
                . 'Set TRANSFER_' . strtoupper($job->target_platform) . '_* in .env'
            );

            return;
        }

        $pending = $job->files()
            ->where('status', 'pending')
            ->orderBy('detected_episode')
            ->limit($this->filesPerRun)
            ->get();

        foreach ($pending as $file) {
            $this->uploadFile($job, $file, $credentials);
        }

        $job->refreshCounters();

        // Done when nothing is left in a non-terminal state.
        $remaining = $job->files()->whereIn('status', ['pending', 'uploading'])->count();
        if ($remaining === 0) {
            $job->markDone();
            $this->cleanupTorrent($job);
        }
    }

    /**
     * Upload one video file to the target platform and attach it to its episode.
     */
    private function uploadFile(TorrentDownload $job, TorrentFile $file, array $credentials): void
    {
        try {
            if ($file->episode_id === null) {
                $file->markSkipped('No matching episode — set episode manually then retry.');

                return;
            }

            $localPath = $file->local_path;
            if (! $localPath || ! is_file($localPath)) {
                $file->markFailed("File missing on disk: {$localPath}");

                return;
            }
            if (filesize($localPath) < self::MIN_VIDEO_SIZE) {
                $file->markFailed('File too small, likely corrupt.');

                return;
            }

            $file->markUploading();

            $title = $this->buildTitle($job, $file);
            $result = $this->uploadToTarget($localPath, $title, $job->target_platform, $credentials);

            $file->markDone($result['embed_url'], $result['file_code'] ?? null);

            $this->attachToEpisode($file, $result['embed_url'], $job->target_platform);

            // Free disk space immediately after a successful upload.
            $this->deleteFile($localPath);
        } catch (Throwable $e) {
            $file->markFailed(mb_substr($e->getMessage(), 0, 500));
        }
    }

    /* =====================================================================
     * DOWNLOAD (aria2c)
     * =================================================================== */

    /**
     * Start downloading a torrent (async via RPC, or blocking CLI). Returns GID.
     */
    public function startDownload(TorrentDownload $job): string
    {
        $url = trim($job->torrent_url);

        return $this->isRpcAvailable()
            ? $this->startViaRpc($url)
            : $this->startViaCli($url);
    }

    /**
     * Poll download status via aria2c RPC.
     *
     * @return array{status:string, progress:int, files:array, file_size:int|null, error:string|null}
     */
    public function pollDownload(string $gid): array
    {
        // CLI mode is blocking, so if we get here the files already exist.
        if (! $this->isRpcAvailable() || str_starts_with($gid, 'cli_')) {
            return [
                'status' => 'complete',
                'progress' => 100,
                'files' => $this->scanDownloadDir(),
                'file_size' => null,
                'error' => null,
            ];
        }

        // A magnet's metadata download spawns a child GID that holds the actual
        // files; follow followedBy when present.
        $resp = $this->rpcCall('aria2.tellStatus', [$gid, [
            'status', 'totalLength', 'completedLength', 'files', 'errorCode',
            'errorMessage', 'followedBy',
        ]]);

        if (! empty($resp['followedBy'][0])) {
            $childGid = $resp['followedBy'][0];
            $resp = $this->rpcCall('aria2.tellStatus', [$childGid, [
                'status', 'totalLength', 'completedLength', 'files', 'errorCode', 'errorMessage',
            ]]);
        }

        $status = $resp['status'] ?? 'error';
        $total = (int) ($resp['totalLength'] ?? 0);
        $completed = (int) ($resp['completedLength'] ?? 0);
        $progress = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        $error = null;
        if ($status === 'error') {
            $error = ($resp['errorMessage'] ?? 'Unknown aria2c error')
                . ' (code: ' . ($resp['errorCode'] ?? '?') . ')';
        }

        return [
            'status' => $status,
            'progress' => $progress,
            'files' => $resp['files'] ?? [],
            'file_size' => $total > 0 ? $total : null,
            'error' => $error,
        ];
    }

    /* =====================================================================
     * FILE SCANNING & EPISODE MATCHING
     * =================================================================== */

    /**
     * From an aria2c files[] list (or a scanned dir), keep only real videos.
     *
     * @return list<array{path:string, name:string, size:int}>
     */
    private function collectVideoFiles(array $files): array
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

            $size = isset($f['length']) ? (int) $f['length'] : (int) @filesize($path);
            if ($size < self::MIN_VIDEO_SIZE) {
                continue;
            }

            $videos[] = [
                'path' => $path,
                'name' => basename($path),
                'size' => $size,
            ];
        }

        return $videos;
    }

    /**
     * Create a torrent_files row for each video, matching it to an episode.
     *
     * Single-episode torrents (job->episode_id set): the largest file maps to
     * that episode. Season packs (job->season_id set): each file's episode
     * number is parsed and matched to the season's episodes.
     */
    private function createTorrentFiles(TorrentDownload $job, array $videos): void
    {
        // Sort by name so episode order is stable for fallback numbering.
        usort($videos, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        // Single-episode torrent: attach the largest video to job->episode_id.
        if ($job->episode_id && ! $job->season_id) {
            $largest = collect($videos)->sortByDesc('size')->first();
            TorrentFile::create([
                'torrent_download_id' => $job->id,
                'file_name' => $largest['name'],
                'local_path' => $largest['path'],
                'file_size' => $largest['size'],
                'detected_episode' => $this->parseEpisodeNumber($largest['name']),
                'episode_id' => $job->episode_id,
                'language' => $job->language,
                'status' => 'pending',
            ]);

            return;
        }

        // Season pack: match each file to an episode of the season.
        $episodesByIndex = $job->season_id
            ? Episode::where('season_id', $job->season_id)->get()->keyBy('index')
            : collect();

        $fallbackIndex = 1;
        foreach ($videos as $video) {
            $epNum = $this->parseEpisodeNumber($video['name']);

            // If parsing failed, fall back to positional numbering.
            if ($epNum === null) {
                $epNum = $fallbackIndex;
            }
            $fallbackIndex = $epNum + 1;

            $episodeId = $episodesByIndex->get($epNum)?->id;

            TorrentFile::create([
                'torrent_download_id' => $job->id,
                'file_name' => $video['name'],
                'local_path' => $video['path'],
                'file_size' => $video['size'],
                'detected_episode' => $epNum,
                'episode_id' => $episodeId,
                'language' => $job->language,
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Parse an episode number from a release filename.
     *
     * Handles common anime/scene patterns, in priority order:
     *   S01E05 / S1E5 / 1x05          → 5
     *   " - 05 " / "_05_" / "E05"     → 5
     *   "Episode 05" / "Ep. 5"        → 5
     *   "[05]" / "(05)"               → 5
     */
    public function parseEpisodeNumber(string $filename): ?int
    {
        // Strip the extension to avoid matching resolution/codec digits there.
        $name = preg_replace('/\.[a-z0-9]{2,4}$/i', '', $filename);

        $patterns = [
            '/S\d{1,2}[\s._-]?E(\d{1,3})/i',          // S01E05, S1 E5
            '/\b\d{1,2}x(\d{1,3})\b/i',                // 1x05
            '/\bE(?:p|pisode)?[\s._-]*(\d{1,3})\b/i',  // E05, Ep 5, Episode 05
            '/[\s._-](\d{1,3})[\s._-]?(?:v\d)?\s*(?:\[|\()/', // - 05 [1080p]
            '/[\s._-](\d{1,3})[\s._-]/',               // loose: - 05 -
            '/\[(\d{1,3})\]/',                         // [05]
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name, $m)) {
                $num = (int) $m[1];
                if ($num > 0 && $num < 2000) { // guard against years/resolutions
                    return $num;
                }
            }
        }

        return null;
    }

    /* =====================================================================
     * UPLOAD
     * =================================================================== */

    /**
     * Upload a local file to the target platform via its driver.
     *
     * @return array{embed_url:string, file_code:string|null, host:string}
     */
    public function uploadToTarget(string $localPath, string $title, string $targetPlatform, array $credentials): array
    {
        $driver = $this->transferService->driverByName($targetPlatform);
        if (! $driver) {
            throw new RuntimeException("No driver available for platform: {$targetPlatform}");
        }

        $result = $driver->upload($localPath, $title, $credentials);
        $result['host'] = parse_url($result['embed_url'] ?? '', PHP_URL_HOST) ?? $targetPlatform;

        return $result;
    }

    /**
     * Create an episode_link pointing to the uploaded video.
     */
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

        return $job->title ?: pathinfo($file->file_name, PATHINFO_FILENAME);
    }

    /* =====================================================================
     * aria2c RPC / CLI
     * =================================================================== */

    private function isRpcAvailable(): bool
    {
        static $available = null;
        if ($available !== null) {
            return $available;
        }

        try {
            $resp = Http::timeout(5)->post($this->rpcUrl, [
                'jsonrpc' => '2.0',
                'id' => 'ping',
                'method' => 'aria2.getVersion',
                'params' => $this->rpcParams([]),
            ]);
            $available = $resp->successful() && isset($resp->json()['result']);
        } catch (Throwable) {
            $available = false;
        }

        return $available;
    }

    private function rpcCall(string $method, array $params = []): array
    {
        $resp = Http::timeout(30)->post($this->rpcUrl, [
            'jsonrpc' => '2.0',
            'id' => uniqid('aria2_'),
            'method' => $method,
            'params' => $this->rpcParams($params),
        ]);

        if ($resp->failed()) {
            throw new RuntimeException("aria2c RPC failed (HTTP {$resp->status()}): {$resp->body()}");
        }

        $json = $resp->json();
        if (isset($json['error'])) {
            throw new RuntimeException('aria2c RPC error: ' . ($json['error']['message'] ?? 'unknown'));
        }

        return $json['result'] ?? [];
    }

    private function rpcParams(array $params): array
    {
        if ($this->rpcSecret) {
            return array_merge(["token:{$this->rpcSecret}"], $params);
        }

        return $params;
    }

    private function startViaRpc(string $url): string
    {
        $options = [
            'dir' => $this->downloadDir,
            'seed-time' => '0',
            'bt-stop-timeout' => '0',
            'follow-torrent' => 'true',
            'file-allocation' => 'none',
        ];

        $result = $this->rpcCall('aria2.addUri', [[$url], $options]);

        if (is_string($result)) {
            return $result;
        }

        throw new RuntimeException('aria2c RPC did not return a GID: ' . json_encode($result));
    }

    /**
     * Blocking CLI download (fallback when RPC is unavailable).
     */
    private function startViaCli(string $url): string
    {
        $binary = env('ARIA2_BINARY', 'aria2c');

        $args = [
            $binary,
            '--dir=' . $this->downloadDir,
            '--seed-time=0',
            '--bt-stop-timeout=0',
            '--follow-torrent=true',
            '--file-allocation=none',
            '--max-connection-per-server=8',
            '--split=8',
            '--check-certificate=false',
            '--allow-overwrite=true',
            $url,
        ];

        try {
            $result = Process::timeout($this->timeout)->run($args);
        } catch (Throwable $e) {
            throw new RuntimeException("aria2c CLI error: {$e->getMessage()}");
        }

        if (! $result->successful()) {
            $err = trim($result->errorOutput() ?: $result->output());
            throw new RuntimeException('aria2c CLI failed: ' . mb_substr($err, 0, 300));
        }

        return 'cli_' . md5($url . microtime());
    }

    /* =====================================================================
     * DISK HELPERS
     * =================================================================== */

    /**
     * Scan the download directory for video files (CLI-mode fallback where
     * aria2c does not report the files list back to us).
     *
     * @return list<array{path:string, length:int}>
     */
    private function scanDownloadDir(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->downloadDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile()) {
                continue;
            }
            $ext = strtolower($fileInfo->getExtension());
            if (in_array($ext, self::VIDEO_EXTS, true)) {
                $files[] = ['path' => $fileInfo->getPathname(), 'length' => $fileInfo->getSize()];
            }
        }

        return $files;
    }

    private function deleteFile(string $path): void
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
     * Remove any leftover files/folders for a completed torrent.
     */
    private function cleanupTorrent(TorrentDownload $job): void
    {
        foreach ($job->files as $file) {
            if ($file->local_path) {
                $this->deleteFile($file->local_path);

                // Remove the (now empty) torrent sub-folder if applicable.
                $dir = dirname($file->local_path);
                if ($dir !== $this->downloadDir && is_dir($dir) && $this->isDirEmpty($dir)) {
                    @rmdir($dir);
                }
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
