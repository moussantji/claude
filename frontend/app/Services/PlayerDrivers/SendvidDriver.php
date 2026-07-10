<?php

namespace App\Services\PlayerDrivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Driver for Sendvid video hosting.
 *
 * Extract: parse the embed/video page to find the direct .mp4 source.
 * Upload: uses the Sendvid API with API key authentication.
 */
class SendvidDriver implements PlayerDriverInterface
{
    public function name(): string
    {
        return 'sendvid';
    }

    public function matchesHost(string $host): bool
    {
        return str_contains($host, 'sendvid');
    }

    public function extractDirectUrl(string $embedUrl): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Referer' => 'https://sendvid.com/',
                ])
                ->get($embedUrl);

            if ($response->failed()) {
                return null;
            }

            $html = $response->body();

            // Pattern 1: source src="...mp4"
            if (preg_match('/<source[^>]+src="([^"]+\.mp4[^"]*)"/', $html, $m)) {
                return $m[1];
            }

            // Pattern 2: video source in JS
            if (preg_match('/(?:source|src|file)\s*[:=]\s*["\']([^"\']+\.mp4[^"\']*)["\']/', $html, $m)) {
                return $m[1];
            }

            // Pattern 3: og:video meta tag
            if (preg_match('/property="og:video"\s+content="([^"]+)"/', $html, $m)) {
                return $m[1];
            }

            // Pattern 4: data-source attribute
            if (preg_match('/data-source="([^"]+\.mp4[^"]*)"/', $html, $m)) {
                return $m[1];
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
                    'Referer' => 'https://sendvid.com/',
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
        $apiBase = rtrim($credentials['api_base'] ?? 'https://sendvid.com/api', '/');

        if ($apiKey === '') {
            throw new RuntimeException('Sendvid target API key is missing.');
        }

        // 1. Request upload URL
        $initResp = Http::timeout(60)
            ->withHeaders(['Authorization' => "Bearer {$apiKey}"])
            ->post("{$apiBase}/v1/upload/init", [
                'title' => $title,
                'filename' => basename($localPath),
            ]);

        if ($initResp->failed()) {
            // Fallback: try direct multipart upload
            return $this->directUpload($localPath, $title, $apiKey, $apiBase);
        }

        $uploadUrl = $initResp->json('upload_url') ?? $initResp->json('url');
        if (! $uploadUrl) {
            return $this->directUpload($localPath, $title, $apiKey, $apiBase);
        }

        // 2. Upload to the provided URL
        $handle = fopen($localPath, 'r');
        if (! $handle) {
            throw new RuntimeException("Cannot open file: {$localPath}");
        }

        try {
            $uploadResp = Http::timeout(900)
                ->attach('file', $handle, basename($localPath))
                ->post($uploadUrl, ['title' => $title]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($uploadResp->failed()) {
            throw new RuntimeException('Sendvid upload failed (HTTP '.$uploadResp->status().').');
        }

        return $this->parseUploadResponse($uploadResp->json());
    }

    private function directUpload(string $localPath, string $title, string $apiKey, string $apiBase): array
    {
        $handle = fopen($localPath, 'r');
        if (! $handle) {
            throw new RuntimeException("Cannot open file: {$localPath}");
        }

        try {
            $response = Http::timeout(900)
                ->withHeaders(['Authorization' => "Bearer {$apiKey}"])
                ->attach('video', $handle, basename($localPath))
                ->post("{$apiBase}/v1/videos", ['title' => $title]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($response->failed()) {
            throw new RuntimeException('Sendvid upload failed (HTTP '.$response->status().'): '.$response->body());
        }

        return $this->parseUploadResponse($response->json());
    }

    private function parseUploadResponse(mixed $data): array
    {
        if (! is_array($data)) {
            throw new RuntimeException('Invalid response from Sendvid.');
        }

        $slug = $data['slug'] ?? $data['video_id'] ?? $data['id'] ?? $data['code'] ?? null;

        if (! $slug) {
            // Try to extract from URL in response
            $url = $data['url'] ?? $data['embed_url'] ?? $data['video_url'] ?? '';
            if (preg_match('/sendvid\.com\/(?:embed\/)?([a-z0-9]+)/i', $url, $m)) {
                $slug = $m[1];
            }
        }

        if (! $slug) {
            throw new RuntimeException('Sendvid did not return a video identifier.');
        }

        return [
            'file_code' => $slug,
            'embed_url' => "https://sendvid.com/embed/{$slug}",
        ];
    }
}
