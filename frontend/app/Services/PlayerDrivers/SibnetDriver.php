<?php

namespace App\Services\PlayerDrivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Driver for Sibnet video hosting.
 *
 * Extract: parse the embed page to find the direct .mp4 source.
 * Upload: Sibnet uses a form-based upload (no official API), so we simulate it.
 */
class SibnetDriver implements PlayerDriverInterface
{
    public function name(): string
    {
        return 'sibnet';
    }

    public function matchesHost(string $host): bool
    {
        return str_contains($host, 'sibnet');
    }

    public function extractDirectUrl(string $embedUrl): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Referer' => 'https://video.sibnet.ru/',
                ])
                ->get($embedUrl);

            if ($response->failed()) {
                return null;
            }

            $html = $response->body();

            // Sibnet pattern: player.src([{src: "/v/...mp4", ...}])
            if (preg_match('/src\s*:\s*["\']([^"\']+\.mp4[^"\']*)["\']/', $html, $m)) {
                $path = $m[1];
                // Relative paths need the base
                if (str_starts_with($path, '/')) {
                    return 'https://video.sibnet.ru'.$path;
                }

                return $path;
            }

            // Alternative: direct player.src = "..."
            if (preg_match('/player\.src\s*\(\s*["\']([^"\']+)["\']/', $html, $m)) {
                $path = $m[1];
                if (str_starts_with($path, '/')) {
                    return 'https://video.sibnet.ru'.$path;
                }

                return $path;
            }

            // Another pattern: file: "..."
            if (preg_match('/file\s*:\s*["\']([^"\']+\.mp4[^"\']*)["\']/', $html, $m)) {
                $path = $m[1];
                if (str_starts_with($path, '/')) {
                    return 'https://video.sibnet.ru'.$path;
                }

                return $path;
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
                    'Referer' => 'https://video.sibnet.ru/',
                ])
                ->get($directUrl);

            return $response->successful() && file_exists($destPath) && filesize($destPath) > 1024;
        } catch (Throwable) {
            return false;
        }
    }

    public function upload(string $localPath, string $title, array $credentials): array
    {
        $login = $credentials['login'] ?? '';
        $password = $credentials['password'] ?? '';

        if ($login === '' || $password === '') {
            throw new RuntimeException('Sibnet target credentials (login/password) are missing.');
        }

        // 1. Authenticate — get session cookie
        $loginResp = Http::timeout(30)->asForm()
            ->post('https://video.sibnet.ru/login.php', [
                'login' => $login,
                'password' => $password,
            ]);

        $cookies = $loginResp->cookies();

        // 2. Get upload page to find the upload token/form action
        $uploadPage = Http::timeout(30)
            ->withCookies($cookies->toArray(), 'video.sibnet.ru')
            ->get('https://video.sibnet.ru/upload/');

        $html = $uploadPage->body();

        // Find the upload form action URL
        $uploadUrl = 'https://video.sibnet.ru/upload/upload_video.php';
        if (preg_match('/action="([^"]*upload[^"]*)"/', $html, $m)) {
            $uploadUrl = $m[1];
            if (str_starts_with($uploadUrl, '/')) {
                $uploadUrl = 'https://video.sibnet.ru'.$uploadUrl;
            }
        }

        // 3. Upload the file
        $handle = fopen($localPath, 'r');
        if (! $handle) {
            throw new RuntimeException("Cannot open file: {$localPath}");
        }

        try {
            $response = Http::timeout(900)
                ->withCookies($cookies->toArray(), 'video.sibnet.ru')
                ->attach('file', $handle, basename($localPath))
                ->post($uploadUrl, [
                    'title' => $title,
                ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($response->failed()) {
            throw new RuntimeException('Sibnet upload failed (HTTP '.$response->status().').');
        }

        // Extract the video ID from the response
        $body = $response->body();
        $videoId = null;

        if (preg_match('/video(\d+)/', $response->header('Location') ?? $body, $m)) {
            $videoId = $m[1];
        } elseif (preg_match('/"id"\s*:\s*(\d+)/', $body, $m)) {
            $videoId = $m[1];
        }

        if (! $videoId) {
            throw new RuntimeException('Sibnet did not return a video ID. Response: '.substr($body, 0, 500));
        }

        return [
            'file_code' => $videoId,
            'embed_url' => "https://video.sibnet.ru/shell.php?videoid={$videoId}",
        ];
    }
}
