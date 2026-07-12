<?php

namespace App\Services;

use App\Models\EpisodeLink;
use App\Models\TorrentDownload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Manages torrent downloads via aria2c (JSON-RPC) and uploads results
 * to target player platforms using the existing PlayerTransferService drivers.
 *
 * aria2c must be running as a daemon with --enable-rpc:
 *   aria2c --enable-rpc --rpc-listen-port=6800 --dir=/tmp/torrents --seed-time=0
 *
 * Alternatively, if aria2c RPC is unavailable, falls back to CLI mode
 * (blocking download via `aria2c` binary directly).
 */
class TorrentService
{
    private string $rpcUrl;
    private ?string $rpcSecret;
    private string $downloadDir;
    private int $timeout;
    private PlayerTransferService $transferService;

    public function __construct(PlayerTransferService $transferService)
    {
        $this->transferService = $transferService;
        $this->rpcUrl = env('ARIA2_RPC_URL', 'http://localhost:6800/jsonrpc');
        $this->rpcSecret = env('ARIA2_RPC_SECRET') ?: null;
        $this->downloadDir = env('TORRENT_DOWNLOAD_DIR', storage_path('app/torrents'));
        $this->timeout = (int) env('TORRENT_DOWNLOAD_TIMEOUT', 3600); // 1h default

        if (! is_dir($this->downloadDir)) {
            @mkdir($this->downloadDir, 0755, true);
        }
    }

    /* =====================================================================
     * PUBLIC API
     * =================================================================== */

    /**
     * Start downloading a torrent (async via aria2c RPC, or blocking CLI).
     * Returns the aria2c GID on success.
     */
    public function startDownload(TorrentDownload $job): string
    {
        $url = trim($job->torrent_url);

        if ($this->isRpcAvailable()) {
            return $this->startViaRpc($url);
        }

        // Fallback: blocking CLI download
        return $this->startViaCli($url);
    }

    /**
     * Poll download status via aria2c RPC.
     *
     * @return array{status: string, progress: int, local_path: string|null, file_size: int|null, error: string|null}
     *         status: 'active' | 'complete' | 'error' | 'waiting' | 'paused' | 'removed'
     */
    public function pollDownload(string $gid): array
    {
        if (! $this->isRpcAvailable()) {
            // CLI mode: the download already finished in startViaCli, so if
            // we reach here the file should exist.
            return [
                'status' => 'complete',
                'progress' => 100,
                'local_path' => null,
                'file_size' => null,
                'error' => null,
            ];
        }

        $resp = $this->rpcCall('aria2.tellStatus', [$gid, [
            'status', 'totalLength', 'completedLength', 'files', 'errorCode', 'errorMessage',
        ]]);

        $status = $resp['status'] ?? 'error';
        $total = (int) ($resp['totalLength'] ?? 0);
        $completed = (int) ($resp['completedLength'] ?? 0);
        $progress = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        $localPath = null;
        if (! empty($resp['files']) && is_array($resp['files'])) {
            // Pick the largest file (the video)
            $largest = collect($resp['files'])->sortByDesc('length')->first();
            $localPath = $largest['path'] ?? null;
        }

        $error = null;
        if ($status === 'error') {
            $error = ($resp['errorMessage'] ?? 'Unknown aria2c error')
                . ' (code: ' . ($resp['errorCode'] ?? '?') . ')';
        }

        return [
            'status' => $status,
            'progress' => $progress,
            'local_path' => $localPath,
            'file_size' => $total > 0 ? $total : null,
            'error' => $error,
        ];
    }

    /**
     * Upload a downloaded file to the target player platform.
     *
     * @return array{embed_url: string, file_code: string|null, host: string}
     * @throws RuntimeException
     */
    public function uploadToTarget(string $localPath, string $title, string $targetPlatform, array $credentials): array
    {
        $driver = $this->transferService->driverByName($targetPlatform);

        if (! $driver) {
            throw new RuntimeException("No driver available for platform: {$targetPlatform}");
        }

        if (! is_file($localPath)) {
            throw new RuntimeException("Downloaded file not found: {$localPath}");
        }

        if (filesize($localPath) < 100_000) {
            throw new RuntimeException("Downloaded file is too small (< 100KB), likely corrupt: {$localPath}");
        }

        $result = $driver->upload($localPath, $title, $credentials);
        $result['host'] = parse_url($result['embed_url'] ?? '', PHP_URL_HOST) ?? $targetPlatform;

        return $result;
    }

    /**
     * Full pipeline: process a single torrent job through all stages.
     * Handles state transitions and cleanup.
     */
    public function processJob(TorrentDownload $job): void
    {
        try {
            // Stage 1: Start or resume download
            if ($job->isPending()) {
                $gid = $this->startDownload($job);
                $job->markDownloading($gid);
            }

            // Stage 2: Poll download until complete (for RPC mode)
            if ($job->isDownloading()) {
                $poll = $this->pollDownload($job->aria2_gid ?? '');

                $job->update(['progress' => $poll['progress']]);

                if ($poll['status'] === 'active' || $poll['status'] === 'waiting') {
                    return; // Not done yet — come back later
                }

                if ($poll['status'] === 'error') {
                    $job->markFailed($poll['error'] ?? 'aria2c download error');
                    return;
                }

                if ($poll['status'] === 'complete') {
                    $localPath = $poll['local_path'] ?? $this->findLargestFile($job->aria2_gid);
                    if (! $localPath || ! is_file($localPath)) {
                        $job->markFailed('Download complete but video file not found on disk.');
                        return;
                    }
                    $job->markUploading($localPath, $poll['file_size'] ?? (int) filesize($localPath));
                }
            }

            // Stage 3: Upload to target platform
            if ($job->isUploading()) {
                $localPath = $job->local_path;
                if (! $localPath || ! is_file($localPath)) {
                    $job->markFailed("File missing for upload: {$localPath}");
                    return;
                }

                $credentials = $this->getCredentials($job->target_platform);
                if (empty($credentials)) {
                    $job->markFailed("No credentials configured for {$job->target_platform}. Set TRANSFER_" . strtoupper($job->target_platform) . "_* in .env");
                    return;
                }

                $title = $job->title ?: ('Torrent #' . $job->id);

                $result = $this->uploadToTarget($localPath, $title, $job->target_platform, $credentials);

                $job->markDone($result['embed_url'], $result['file_code'] ?? null);

                // Auto-attach to episode if episode_id was provided
                if ($job->episode_id) {
                    $this->attachToEpisode($job, $result);
                }

                // Cleanup: remove the downloaded file
                $this->cleanup($localPath);
            }
        } catch (Throwable $e) {
            $job->markFailed(mb_substr($e->getMessage(), 0, 500));

            // Cleanup on failure too
            if ($job->local_path && is_file($job->local_path)) {
                $this->cleanup($job->local_path);
            }
        }
    }

    /**
     * Find the largest video file in the download directory for a given GID.
     */
    public function findLargestFile(?string $gid): ?string
    {
        if (! $gid) {
            return null;
        }

        // aria2c names files in the download dir; scan for the largest video
        $videoExts = ['mp4', 'mkv', 'avi', 'webm', 'ts', 'flv', 'mov'];
        $largest = null;
        $largestSize = 0;

        $files = glob($this->downloadDir . '/*') ?: [];
        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $videoExts, true) && filesize($file) > $largestSize) {
                $largest = $file;
                $largestSize = filesize($file);
            }
        }

        return $largest;
    }

    /* =====================================================================
     * aria2c RPC
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
            throw new RuntimeException("aria2c RPC error: " . ($json['error']['message'] ?? 'unknown'));
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

    /**
     * Start a download via aria2c RPC (async — returns immediately with GID).
     */
    private function startViaRpc(string $url): string
    {
        $options = [
            'dir' => $this->downloadDir,
            'seed-time' => '0',           // Don't seed after download
            'max-download-limit' => '0',  // No speed limit
            'file-allocation' => 'none',
        ];

        if (str_starts_with($url, 'magnet:')) {
            $result = $this->rpcCall('aria2.addUri', [[$url], $options]);
        } else {
            // HTTP/HTTPS URL to a .torrent file
            $result = $this->rpcCall('aria2.addUri', [[$url], $options]);
        }

        if (is_string($result)) {
            return $result; // GID
        }

        throw new RuntimeException('aria2c RPC did not return a GID: ' . json_encode($result));
    }

    /* =====================================================================
     * aria2c CLI fallback (blocking)
     * =================================================================== */

    /**
     * Download via aria2c CLI (blocking). Returns a fake GID (the process
     * completes synchronously so the file is already on disk).
     */
    private function startViaCli(string $url): string
    {
        $binary = env('ARIA2_BINARY', 'aria2c');

        $args = [
            $binary,
            '--dir=' . $this->downloadDir,
            '--seed-time=0',
            '--file-allocation=none',
            '--max-connection-per-server=8',
            '--split=8',
            '--check-certificate=false',
            '--allow-overwrite=true',
        ];

        if (str_starts_with($url, 'magnet:')) {
            $args[] = $url;
        } else {
            $args[] = $url;
        }

        try {
            $result = Process::timeout($this->timeout)->run($args);
        } catch (Throwable $e) {
            throw new RuntimeException("aria2c CLI error: {$e->getMessage()}");
        }

        if (! $result->successful()) {
            $err = trim($result->errorOutput() ?: $result->output());
            throw new RuntimeException("aria2c CLI failed: " . mb_substr($err, 0, 300));
        }

        // Generate a fake GID for tracking (CLI mode = already complete)
        return 'cli_' . md5($url . time());
    }

    /* =====================================================================
     * HELPERS
     * =================================================================== */

    /**
     * Create an episode_link pointing to the newly uploaded video.
     */
    private function attachToEpisode(TorrentDownload $job, array $uploadResult): void
    {
        if (! $job->episode_id || ! $job->embed_url) {
            return;
        }

        $host = parse_url($job->embed_url, PHP_URL_HOST);
        $host = is_string($host) ? preg_replace('/^www\./', '', $host) : $job->target_platform;

        EpisodeLink::create([
            'episode_id' => $job->episode_id,
            'language' => $job->language ?? 'VOSTFR',
            'position' => 0,
            'url' => $job->embed_url,
            'host' => $host,
        ]);
    }

    /**
     * Remove downloaded file and any related fragments.
     */
    private function cleanup(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }

        // Also remove .aria2 control file if present
        $control = $path . '.aria2';
        if (is_file($control)) {
            @unlink($control);
        }
    }

    /**
     * Get target platform credentials from environment variables.
     * Reuses the same env vars as PlayerTransferService / EpisodesAuto.
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
