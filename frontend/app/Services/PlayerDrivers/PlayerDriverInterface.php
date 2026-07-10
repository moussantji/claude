<?php

namespace App\Services\PlayerDrivers;

/**
 * Contract for a video hosting platform driver.
 * Each driver knows how to:
 *   1. Extract the direct video URL from an embed page
 *   2. Download that video to a local temp file
 *   3. Upload a local video file to a target account
 */
interface PlayerDriverInterface
{
    /**
     * Platform identifier (e.g. 'vidmoly', 'sibnet', 'sendvid').
     */
    public function name(): string;

    /**
     * Extract the direct download URL of the video from an embed/page URL.
     *
     * @param  string  $embedUrl  The embed or page URL from the DB.
     * @return string|null  Direct video file URL (.mp4, .m3u8, etc.) or null on failure.
     */
    public function extractDirectUrl(string $embedUrl): ?string;

    /**
     * Download the video file to a local temporary path.
     *
     * @param  string  $directUrl  The direct video URL obtained from extractDirectUrl().
     * @param  string  $destPath   Local file path to save to.
     * @return bool  True on success.
     */
    public function download(string $directUrl, string $destPath): bool;

    /**
     * Upload a local video file to the TARGET account.
     *
     * @param  string  $localPath  Path to the local video file.
     * @param  string  $title      Title for the uploaded video.
     * @param  array   $credentials  Target account credentials (api_key, etc.)
     * @return array{embed_url: string, file_code: string|null}  Result with the new embed URL.
     */
    public function upload(string $localPath, string $title, array $credentials): array;

    /**
     * Check if this driver handles the given host.
     */
    public function matchesHost(string $host): bool;
}
