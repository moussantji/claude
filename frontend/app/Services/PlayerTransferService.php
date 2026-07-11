<?php

namespace App\Services;

use App\Services\PlayerDrivers\PlayerDriverInterface;
use App\Services\PlayerDrivers\RemoteUploadInterface;
use App\Services\PlayerDrivers\DailymotionDriver;
use App\Services\PlayerDrivers\DoodstreamDriver;
use App\Services\PlayerDrivers\Mp4uploadDriver;
use App\Services\PlayerDrivers\MyviDriver;
use App\Services\PlayerDrivers\SendvidDriver;
use App\Services\PlayerDrivers\SibnetDriver;
use App\Services\PlayerDrivers\StreamtapeDriver;
use App\Services\PlayerDrivers\VidmolyDriver;
use RuntimeException;

/**
 * Orchestrates video transfers between hosting platform accounts.
 *
 * Flow:  Source embed URL → extract direct URL → download → upload to target → new embed URL
 */
class PlayerTransferService
{
    /** @var PlayerDriverInterface[] */
    protected array $drivers;

    /** Last yt-dlp failure reason (surfaced in transfer errors). */
    public ?string $lastYtDlpError = null;

    public function __construct()
    {
        $this->drivers = [
            new VidmolyDriver(),
            new SibnetDriver(),
            new SendvidDriver(),
            new DoodstreamDriver(),
            new StreamtapeDriver(),
            new Mp4uploadDriver(),
            new MyviDriver(),
            new DailymotionDriver(),
        ];
    }

    /**
     * Get all registered driver names.
     *
     * @return list<string>
     */
    public function availablePlatforms(): array
    {
        return array_map(fn ($d) => $d->name(), $this->drivers);
    }

    /**
     * Get the driver for a given host string.
     */
    public function driverForHost(string $host): ?PlayerDriverInterface
    {
        foreach ($this->drivers as $driver) {
            if ($driver->matchesHost($host)) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * Get a driver by its platform name.
     */
    public function driverByName(string $name): ?PlayerDriverInterface
    {
        foreach ($this->drivers as $driver) {
            if ($driver->name() === $name) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * Server-to-server (remote) transfer: the TARGET host fetches the video
     * itself. Our server only makes lightweight API calls — ideal for shared
     * hosting. Returns a queued job (finalize later via remoteStatus).
     *
     * @return array{job_id: string|null, embed_url: string|null, file_code: string|null, state: string, platform: string}
     *
     * @throws RuntimeException
     */
    public function transferRemote(
        string $sourceEmbedUrl,
        string $title,
        array $targetCredentials,
        ?string $targetPlatform = null,
    ): array {
        $host = preg_replace('/^www\./', '', parse_url($sourceEmbedUrl, PHP_URL_HOST) ?? '');
        $sourceDriver = $this->driverForHost($host);
        $targetDriver = $targetPlatform !== null ? $this->driverByName($targetPlatform) : $sourceDriver;

        if (! $targetDriver instanceof RemoteUploadInterface) {
            $name = $targetDriver?->name() ?? ($targetPlatform ?? $host);
            throw new RuntimeException("Target '{$name}' does not support remote upload.");
        }

        // Try to get a direct URL (lightweight page fetch, NO video download).
        // Fall back to the embed URL — hosts like Doodstream can resolve it.
        $directUrl = $sourceDriver ? $sourceDriver->extractDirectUrl($sourceEmbedUrl) : null;
        $urlToSend = $directUrl ?: $sourceEmbedUrl;

        $result = $targetDriver->remoteUpload($urlToSend, $title, $targetCredentials);
        $result['platform'] = $targetDriver->name();

        return $result;
    }

    /**
     * Poll the status of a queued remote upload on a target platform.
     *
     * @return array{state: string, embed_url: string|null, file_code: string|null, error: string|null}
     */
    public function remoteStatus(string $platform, string $jobId, array $credentials): array
    {
        $driver = $this->driverByName($platform);
        if (! $driver instanceof RemoteUploadInterface) {
            return ['state' => 'error', 'embed_url' => null, 'file_code' => null, 'error' => 'no remote support'];
        }

        return $driver->remoteStatus($jobId, $credentials);
    }

    /**
     * Transfer a video from a source URL to a target account.
     *
     * By default the target platform is the SAME as the source (vidmoly→vidmoly).
     * Pass $targetPlatform to send it to a DIFFERENT platform (cross-host),
     * e.g. sendvid→doodstream to consolidate onto a monetizing host.
     *
     * @param  string  $sourceEmbedUrl  Current embed URL (from the DB).
     * @param  string  $title           Title for the re-uploaded video.
     * @param  array   $targetCredentials  Target account credentials.
     * @param  string|null  $tempDir    Directory for temporary downloads.
     * @param  string|null  $targetPlatform  Force a different target platform (cross-host).
     * @return array{embed_url: string, file_code: string|null, host: string}
     *
     * @throws RuntimeException
     */
    public function transfer(
        string $sourceEmbedUrl,
        string $title,
        array $targetCredentials,
        ?string $tempDir = null,
        ?string $targetPlatform = null,
    ): array {
        // Determine the source platform from the URL
        $host = parse_url($sourceEmbedUrl, PHP_URL_HOST) ?? '';
        $host = preg_replace('/^www\./', '', $host);

        // Source driver is OPTIONAL: yt-dlp can download most hosts even without
        // a dedicated driver. Only a valid TARGET driver is required to upload.
        $sourceDriver = $this->driverForHost($host);
        $targetDriver = $targetPlatform !== null
            ? $this->driverByName($targetPlatform)
            : $sourceDriver;

        if (! $targetDriver) {
            throw new RuntimeException('No driver available for target platform: '.($targetPlatform ?? $host));
        }

        $tempDir = $tempDir ?? storage_path('app/temp-transfers');
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $tempFile = $tempDir.'/transfer_'.uniqid().'_'.time().'.mp4';

        try {
            // Step 1+2: Download the video.
            // Prefer yt-dlp (handles vidmoly HLS, sibnet, uqload, referers, ...).
            // Fall back to the source driver's own extract+download if needed.
            $downloaded = $this->downloadWithYtDlp($sourceEmbedUrl, $tempFile);

            if (! $downloaded) {
                $reason = $this->lastYtDlpError ?: 'yt-dlp failed';
                if (! $sourceDriver) {
                    throw new RuntimeException("{$reason} (host {$host}, no PHP driver)");
                }
                $directUrl = $sourceDriver->extractDirectUrl($sourceEmbedUrl);
                if (! $directUrl) {
                    throw new RuntimeException("yt-dlp: {$reason}; and driver could not extract: {$sourceEmbedUrl}");
                }
                if (! $sourceDriver->download($directUrl, $tempFile)) {
                    throw new RuntimeException("yt-dlp: {$reason}; and driver download failed: {$directUrl}");
                }
            }

            // Step 3: Upload to target account (using the TARGET driver)
            $result = $targetDriver->upload($tempFile, $title, $targetCredentials);
            $result['host'] = parse_url($result['embed_url'] ?? '', PHP_URL_HOST) ?? $targetDriver->name();

            return $result;
        } finally {
            // Clean up temp file
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Download an embed/source URL with yt-dlp (handles hundreds of hosts, HLS,
     * referers). Requires yt-dlp (+ ffmpeg for HLS) on the machine.
     * Returns false if yt-dlp is unavailable or the download failed.
     */
    public function downloadWithYtDlp(string $url, string $destPath): bool
    {
        $this->lastYtDlpError = null;

        if (! $this->ytDlpAvailable()) {
            $this->lastYtDlpError = "yt-dlp introuvable (binaire '".$this->ytDlpBinary()."'). Mets le CHEMIN ABSOLU dans .env YTDLP_PATH (ex: /usr/local/bin/yt-dlp).";

            return false;
        }

        try {
            $result = \Illuminate\Support\Facades\Process::timeout((int) env('YTDLP_TIMEOUT', 1800))
                ->run([
                    $this->ytDlpBinary(),
                    '--no-playlist',
                    '--no-warnings',
                    '--no-progress',
                    '--retries', '3',
                    '--fragment-retries', '10',
                    '-f', 'best[ext=mp4]/bestvideo*+bestaudio/best',
                    '--merge-output-format', 'mp4',
                    '--referer', $url,
                    '-o', $destPath,
                    $url,
                ]);
        } catch (\Throwable $e) {
            $this->lastYtDlpError = 'yt-dlp process error: '.$e->getMessage();

            return false;
        }

        if ($result->successful() && is_file($destPath) && filesize($destPath) > 100000) {
            return true;
        }

        // Capture yt-dlp's real reason (unsupported host, 403, geo, etc.)
        $err = trim($result->errorOutput() ?: $result->output());
        if ($err !== '') {
            $err = preg_replace('/\s+/', ' ', $err);
            $this->lastYtDlpError = 'yt-dlp: '.(strlen($err) > 140 ? substr($err, -140) : $err);
        } else {
            $this->lastYtDlpError = 'yt-dlp a échoué (aucune vidéo récupérée)';
        }

        // yt-dlp may have written a slightly different filename (e.g. .mp4.mp4)
        $base = preg_replace('/\.mp4$/', '', $destPath);
        foreach ((array) glob($base.'*') as $f) {
            if (is_file($f) && filesize($f) > 100000) {
                if ($f !== $destPath) {
                    @rename($f, $destPath);
                }

                return is_file($destPath);
            }
        }

        return false;
    }

    private function ytDlpBinary(): string
    {
        return (string) env('YTDLP_PATH', 'yt-dlp');
    }

    private function ytDlpAvailable(): bool
    {
        static $available = null;
        if ($available !== null) {
            return $available;
        }
        try {
            $r = \Illuminate\Support\Facades\Process::timeout(15)->run([$this->ytDlpBinary(), '--version']);
            $available = $r->successful();
        } catch (\Throwable) {
            $available = false;
        }

        return $available;
    }
}
