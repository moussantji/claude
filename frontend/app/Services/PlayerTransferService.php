<?php

namespace App\Services;

use App\Services\PlayerDrivers\PlayerDriverInterface;
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

        $sourceDriver = $this->driverForHost($host);
        if (! $sourceDriver) {
            throw new RuntimeException("No driver available for source host: {$host}");
        }

        // Target driver: same as source unless a target platform is specified
        $targetDriver = $targetPlatform !== null
            ? $this->driverByName($targetPlatform)
            : $sourceDriver;

        if (! $targetDriver) {
            throw new RuntimeException("No driver available for target platform: {$targetPlatform}");
        }

        $tempDir = $tempDir ?? storage_path('app/temp-transfers');
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $tempFile = $tempDir.'/transfer_'.uniqid().'_'.time().'.mp4';

        try {
            // Step 1: Extract direct video URL (using the SOURCE driver)
            $directUrl = $sourceDriver->extractDirectUrl($sourceEmbedUrl);
            if (! $directUrl) {
                throw new RuntimeException("Could not extract direct URL from: {$sourceEmbedUrl}");
            }

            // Step 2: Download the video (using the SOURCE driver)
            $success = $sourceDriver->download($directUrl, $tempFile);
            if (! $success) {
                throw new RuntimeException("Download failed for: {$directUrl}");
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
}
