<?php

namespace App\Services;

use App\Services\PlayerDrivers\PlayerDriverInterface;
use App\Services\PlayerDrivers\SendvidDriver;
use App\Services\PlayerDrivers\SibnetDriver;
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
     * Transfer a video from source to target account on the same platform.
     *
     * @param  string  $sourceEmbedUrl  Current embed URL (from the DB).
     * @param  string  $title           Title for the re-uploaded video.
     * @param  array   $targetCredentials  Target account credentials.
     * @param  string|null  $tempDir    Directory for temporary downloads.
     * @return array{embed_url: string, file_code: string|null}
     *
     * @throws RuntimeException
     */
    public function transfer(
        string $sourceEmbedUrl,
        string $title,
        array $targetCredentials,
        ?string $tempDir = null,
    ): array {
        // Determine the hosting platform from the URL
        $host = parse_url($sourceEmbedUrl, PHP_URL_HOST) ?? '';
        $host = preg_replace('/^www\./', '', $host);

        $driver = $this->driverForHost($host);
        if (! $driver) {
            throw new RuntimeException("No driver available for host: {$host}");
        }

        $tempDir = $tempDir ?? storage_path('app/temp-transfers');
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $tempFile = $tempDir.'/transfer_'.uniqid().'_'.time().'.mp4';

        try {
            // Step 1: Extract direct video URL
            $directUrl = $driver->extractDirectUrl($sourceEmbedUrl);
            if (! $directUrl) {
                throw new RuntimeException("Could not extract direct URL from: {$sourceEmbedUrl}");
            }

            // Step 2: Download the video
            $success = $driver->download($directUrl, $tempFile);
            if (! $success) {
                throw new RuntimeException("Download failed for: {$directUrl}");
            }

            // Step 3: Upload to target account
            $result = $driver->upload($tempFile, $title, $targetCredentials);

            return $result;
        } finally {
            // Clean up temp file
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }
}
