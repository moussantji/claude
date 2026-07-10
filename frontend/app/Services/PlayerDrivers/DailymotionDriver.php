<?php

namespace App\Services\PlayerDrivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Driver for Dailymotion.
 *
 * Extract: uses the Dailymotion player metadata API to get the progressive MP4 URL.
 * Upload: uses the Dailymotion Partner API (OAuth2).
 */
class DailymotionDriver implements PlayerDriverInterface
{
    public function name(): string
    {
        return 'dailymotion';
    }

    public function matchesHost(string $host): bool
    {
        return str_contains($host, 'dailymotion') || str_contains($host, 'dai.ly');
    }

    public function extractDirectUrl(string $embedUrl): ?string
    {
        try {
            // Extract the video ID from the URL
            $videoId = $this->extractVideoId($embedUrl);
            if (! $videoId) {
                return null;
            }

            // Use the player metadata endpoint
            $metaResp = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get("https://www.dailymotion.com/player/metadata/video/{$videoId}");

            if ($metaResp->failed()) {
                return null;
            }

            $data = $metaResp->json();

            // Get qualities from metadata
            $qualities = $data['qualities'] ?? [];

            // Prefer highest progressive MP4
            foreach (['1080', '720', '480', '380', '240'] as $q) {
                if (isset($qualities[$q])) {
                    foreach ($qualities[$q] as $source) {
                        if (($source['type'] ?? '') === 'video/mp4') {
                            return $source['url'];
                        }
                    }
                }
            }

            // Fallback: auto quality
            if (isset($qualities['auto'])) {
                foreach ($qualities['auto'] as $source) {
                    if (str_contains($source['url'] ?? '', '.m3u8') || str_contains($source['url'] ?? '', '.mp4')) {
                        return $source['url'];
                    }
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    public function download(string $directUrl, string $destPath): bool
    {
        try {
            $response = Http::timeout(600)
                ->withOptions(['sink' => $destPath, 'stream' => true])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Referer' => 'https://www.dailymotion.com/',
                ])
                ->get($directUrl);

            return $response->successful() && file_exists($destPath) && filesize($destPath) > 1024;
        } catch (Throwable) {
            return false;
        }
    }

    public function upload(string $localPath, string $title, array $credentials): array
    {
        $apiKey = $credentials['api_key'] ?? '';
        $apiSecret = $credentials['api_secret'] ?? '';
        $username = $credentials['username'] ?? '';
        $password = $credentials['password'] ?? '';

        if ($apiKey === '' || $apiSecret === '') {
            throw new RuntimeException('Dailymotion target credentials (api_key/api_secret) are missing.');
        }

        // 1. Get OAuth2 token
        $tokenResp = Http::timeout(30)->asForm()
            ->post('https://api.dailymotion.com/oauth/token', [
                'grant_type' => 'password',
                'client_id' => $apiKey,
                'client_secret' => $apiSecret,
                'username' => $username,
                'password' => $password,
                'scope' => 'manage_videos',
            ]);

        if ($tokenResp->failed()) {
            throw new RuntimeException('Dailymotion OAuth failed (HTTP '.$tokenResp->status().').');
        }

        $token = $tokenResp->json('access_token');
        if (! $token) {
            throw new RuntimeException('Dailymotion did not return an access token.');
        }

        // 2. Get upload URL
        $uploadUrlResp = Http::timeout(30)
            ->withHeaders(['Authorization' => "Bearer {$token}"])
            ->get('https://api.dailymotion.com/file/upload');

        if ($uploadUrlResp->failed()) {
            throw new RuntimeException('Could not get Dailymotion upload URL.');
        }

        $uploadUrl = $uploadUrlResp->json('upload_url');
        if (! $uploadUrl) {
            throw new RuntimeException('Dailymotion did not return an upload URL.');
        }

        // 3. Upload file
        $handle = fopen($localPath, 'r');
        if (! $handle) {
            throw new RuntimeException("Cannot open file: {$localPath}");
        }

        try {
            $fileResp = Http::timeout(900)
                ->attach('file', $handle, basename($localPath))
                ->post($uploadUrl);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($fileResp->failed()) {
            throw new RuntimeException('Dailymotion file upload failed.');
        }

        $fileUrl = $fileResp->json('url');
        if (! $fileUrl) {
            throw new RuntimeException('Dailymotion did not confirm the upload.');
        }

        // 4. Create the video object
        $videoResp = Http::timeout(30)
            ->withHeaders(['Authorization' => "Bearer {$token}"])
            ->asForm()
            ->post('https://api.dailymotion.com/me/videos', [
                'url' => $fileUrl,
                'title' => $title,
                'channel' => 'shortfilms',
                'published' => 'true',
            ]);

        if ($videoResp->failed()) {
            throw new RuntimeException('Dailymotion video creation failed (HTTP '.$videoResp->status().').');
        }

        $videoId = $videoResp->json('id');
        if (! $videoId) {
            throw new RuntimeException('Dailymotion did not return a video ID.');
        }

        return [
            'file_code' => $videoId,
            'embed_url' => "https://www.dailymotion.com/embed/video/{$videoId}",
        ];
    }

    private function extractVideoId(string $url): ?string
    {
        // Patterns: /embed/video/XXXXX, /video/XXXXX, dai.ly/XXXXX
        if (preg_match('/(?:embed\/video|video)\/([a-z0-9]+)/i', $url, $m)) {
            return $m[1];
        }
        if (preg_match('/dai\.ly\/([a-z0-9]+)/i', $url, $m)) {
            return $m[1];
        }
        // Last segment
        $parts = array_filter(explode('/', parse_url($url, PHP_URL_PATH) ?? ''));
        $last = end($parts);
        if ($last && preg_match('/^[a-z0-9]+$/i', $last)) {
            return $last;
        }

        return null;
    }
}
