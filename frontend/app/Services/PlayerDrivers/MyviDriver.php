<?php

namespace App\Services\PlayerDrivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Driver for Myvi.ru / Myvi.top video hosting.
 *
 * Extract: parse the embed page to find the HLS (.m3u8) or direct .mp4 source.
 * Upload: Myvi uses a session-based upload with login credentials.
 */
class MyviDriver implements PlayerDriverInterface
{
    public function name(): string
    {
        return 'myvi';
    }

    public function matchesHost(string $host): bool
    {
        return str_contains($host, 'myvi');
    }

    public function extractDirectUrl(string $embedUrl): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Referer' => 'https://myvi.tv/',
                ])
                ->get($embedUrl);

            if ($response->failed()) {
                return null;
            }

            $html = $response->body();

            // Pattern 1: PlayerLoader.CreateEncoder with dataUrl
            if (preg_match('/dataUrl\s*:\s*["\']([^"\']+)["\']/', $html, $m)) {
                $dataUrl = $m[1];
                if (str_starts_with($dataUrl, '//')) {
                    $dataUrl = 'https:'.$dataUrl;
                }
                // Fetch the data URL for the actual video source
                $dataResp = Http::timeout(15)->get($dataUrl);
                if ($dataResp->successful()) {
                    $data = $dataResp->json();
                    $videoUrl = $data['sprutoData']['playlist'][0]['video'][0]['url']
                        ?? $data['session']['content']['sources'][0]['url']
                        ?? null;
                    if ($videoUrl) {
                        return str_starts_with($videoUrl, '//') ? 'https:'.$videoUrl : $videoUrl;
                    }
                }
            }

            // Pattern 2: direct .mp4 or .m3u8 URL in source/file
            if (preg_match('/(https?:\/\/[^\s"\']+\.(?:mp4|m3u8)[^\s"\']*)/i', $html, $m)) {
                return $m[1];
            }

            // Pattern 3: v.myvi.xyz or similar CDN
            if (preg_match('/((?:https?:)?\/\/[^"\']+myvi[^"\']+\.(?:mp4|m3u8)[^"\']*)/i', $html, $m)) {
                $url = $m[1];
                return str_starts_with($url, '//') ? 'https:'.$url : $url;
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    public function download(string $directUrl, string $destPath): bool
    {
        try {
            // If it's an m3u8, we can't just download it as a file — skip HLS for now
            if (str_contains($directUrl, '.m3u8')) {
                // For HLS, use the first segment approach or skip
                return $this->downloadHls($directUrl, $destPath);
            }

            $response = Http::timeout(600)
                ->withOptions(['sink' => $destPath, 'stream' => true])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Referer' => 'https://myvi.tv/',
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
        $apiBase = rtrim($credentials['api_base'] ?? 'https://myvi.tv', '/');

        if ($login === '' || $password === '') {
            throw new RuntimeException('Myvi target credentials (login/password) are missing.');
        }

        // 1. Authenticate
        $authResp = Http::timeout(30)->asForm()
            ->post("{$apiBase}/api/auth/login", [
                'login' => $login,
                'password' => $password,
            ]);

        if ($authResp->failed()) {
            throw new RuntimeException('Myvi authentication failed (HTTP '.$authResp->status().').');
        }

        $token = $authResp->json('token') ?? $authResp->json('access_token') ?? '';
        $cookies = $authResp->cookies();

        // 2. Upload
        $handle = fopen($localPath, 'r');
        if (! $handle) {
            throw new RuntimeException("Cannot open file: {$localPath}");
        }

        try {
            $uploadResp = Http::timeout(900)
                ->withHeaders(['Authorization' => "Bearer {$token}"])
                ->withCookies($cookies ? $cookies->toArray() : [], parse_url($apiBase, PHP_URL_HOST) ?? 'myvi.tv')
                ->attach('file', $handle, basename($localPath))
                ->post("{$apiBase}/api/videos/upload", ['title' => $title]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($uploadResp->failed()) {
            throw new RuntimeException('Myvi upload failed (HTTP '.$uploadResp->status().').');
        }

        $data = $uploadResp->json();
        $videoId = $data['id'] ?? $data['video_id'] ?? $data['code'] ?? null;

        if (! $videoId) {
            throw new RuntimeException('Myvi did not return a video ID.');
        }

        return [
            'file_code' => (string) $videoId,
            'embed_url' => "{$apiBase}/embed/{$videoId}",
        ];
    }

    /**
     * Basic HLS download — fetches the playlist, concatenates TS segments.
     */
    private function downloadHls(string $m3u8Url, string $destPath): bool
    {
        try {
            $playlist = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0', 'Referer' => 'https://myvi.tv/'])
                ->get($m3u8Url)->body();

            // Get the best quality stream
            $lines = explode("\n", $playlist);
            $segments = [];
            $baseUrl = preg_replace('/[^\/]+$/', '', $m3u8Url);

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $segUrl = str_starts_with($line, 'http') ? $line : $baseUrl.$line;
                $segments[] = $segUrl;
            }

            if (empty($segments)) {
                return false;
            }

            // If it points to another m3u8 (multi-bitrate), follow it
            if (str_contains($segments[0], '.m3u8')) {
                return $this->downloadHls($segments[count($segments) - 1], $destPath);
            }

            // Download and concatenate TS segments
            $out = fopen($destPath, 'wb');
            if (! $out) {
                return false;
            }

            foreach ($segments as $segUrl) {
                $segResp = Http::timeout(60)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0', 'Referer' => 'https://myvi.tv/'])
                    ->get($segUrl);
                if ($segResp->successful()) {
                    fwrite($out, $segResp->body());
                }
            }
            fclose($out);

            return file_exists($destPath) && filesize($destPath) > 1024;
        } catch (Throwable) {
            return false;
        }
    }
}
