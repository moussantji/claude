<?php

namespace App\Services\PlayerDrivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Driver for Doodstream (dood.to, dood.watch, dood.la, doodstream.com, etc.).
 *
 * Extract: Doodstream uses a randomized token-based direct link. We need to
 *          parse the embed page and reconstruct the download URL.
 * Upload: Uses the Doodstream API (XFileSharing derivative).
 */
class DoodstreamDriver implements PlayerDriverInterface, RemoteUploadInterface
{
    public function name(): string
    {
        return 'doodstream';
    }

    /**
     * Doodstream remote upload — its server fetches the URL directly.
     * It can resolve many source hosts (sibnet, streamtape, direct mp4, ...).
     * Endpoint: /upload/url?key=&url=
     */
    public function remoteUpload(string $sourceUrl, string $title, array $credentials): array
    {
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://doodapi.com/api', '/');

        if ($key === '') {
            throw new RuntimeException('Doodstream API key missing.');
        }

        $resp = Http::timeout(30)->acceptJson()->get("{$apiBase}/upload/url", [
            'key' => $key,
            'url' => $sourceUrl,
            'title' => $title !== '' ? $title : null,
        ]);

        if ($resp->failed() || (int) $resp->json('status') !== 200) {
            throw new RuntimeException('Doodstream remote upload failed: '.($resp->json('msg') ?? $resp->status()));
        }

        $result = $resp->json('result');
        // Result can be an object or a list; try to find a file code
        $entry = (is_array($result) && array_is_list($result) && isset($result[0])) ? $result[0] : $result;
        $code = $entry['filecode'] ?? $entry['file_code'] ?? $entry['code'] ?? null;

        if ($code) {
            return [
                'job_id' => $code,
                'embed_url' => "https://dood.to/e/{$code}",
                'file_code' => $code,
                'state' => 'done',
            ];
        }

        return [
            'job_id' => $entry['id'] ?? null,
            'embed_url' => null,
            'file_code' => null,
            'state' => 'queued',
        ];
    }

    public function remoteStatus(string $jobId, array $credentials): array
    {
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://doodapi.com/api', '/');

        // If jobId already looks like a file code, confirm it exists.
        $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/file/info", [
            'key' => $key, 'file_code' => $jobId,
        ]);

        $result = $resp->json('result');
        $entry = (is_array($result) && array_is_list($result) && isset($result[0])) ? $result[0] : $result;

        if (is_array($entry) && ! empty($entry['filecode'] ?? $entry['file_code'] ?? null)) {
            $code = $entry['filecode'] ?? $entry['file_code'];

            return ['state' => 'done', 'embed_url' => "https://dood.to/e/{$code}", 'file_code' => $code, 'error' => null];
        }

        return ['state' => 'pending', 'embed_url' => null, 'file_code' => null, 'error' => null];
    }

    public function matchesHost(string $host): bool
    {
        return str_contains($host, 'dood') || str_contains($host, 'doodstream') || str_contains($host, 'ds2play');
    }

    public function extractDirectUrl(string $embedUrl): ?string
    {
        try {
            // Normalize to embed URL
            $embedUrl = preg_replace('#/(d|e)/([a-z0-9]+)#i', '/e/$2', $embedUrl);

            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Referer' => $embedUrl,
                ])
                ->get($embedUrl);

            if ($response->failed()) {
                return null;
            }

            $html = $response->body();

            // Pattern 1: /pass_md5/... endpoint that returns a direct URL token
            if (preg_match('#(/pass_md5/[^"\']+)#', $html, $m)) {
                $passUrl = $this->resolveBaseUrl($embedUrl).$m[1];

                $passResp = Http::timeout(15)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0',
                        'Referer' => $embedUrl,
                    ])
                    ->get($passUrl);

                if ($passResp->successful()) {
                    $directBase = trim($passResp->body());
                    // Append random token + expiry
                    $token = substr(md5(uniqid()), 0, 10);
                    $expiry = time();

                    return "{$directBase}{$token}?token={$token}&expiry={$expiry}";
                }
            }

            // Pattern 2: direct source URL
            if (preg_match('/(https?:\/\/[^\s"\']+\.(?:mp4|m3u8)[^\s"\']*)/i', $html, $m)) {
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
                    'Referer' => 'https://dood.to/',
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
        $apiBase = rtrim($credentials['api_base'] ?? 'https://doodapi.com/api', '/');

        if ($apiKey === '') {
            throw new RuntimeException('Doodstream target API key is missing.');
        }

        // 1. Get upload server
        $serverResp = Http::timeout(60)->acceptJson()
            ->get("{$apiBase}/upload/server", ['key' => $apiKey]);

        if ($serverResp->failed()) {
            throw new RuntimeException('Could not get Doodstream upload server (HTTP '.$serverResp->status().').');
        }

        $uploadUrl = $serverResp->json('result');
        if (! is_string($uploadUrl) || $uploadUrl === '') {
            throw new RuntimeException('Doodstream did not return an upload URL.');
        }

        // Ensure it ends with /upload/
        if (! str_ends_with($uploadUrl, '/')) {
            $uploadUrl .= '/';
        }

        // 2. Upload file
        $handle = fopen($localPath, 'r');
        if (! $handle) {
            throw new RuntimeException("Cannot open file: {$localPath}");
        }

        try {
            $response = Http::timeout(900)
                ->attach('file', $handle, basename($localPath))
                ->post($uploadUrl, [
                    'api_key' => $apiKey,
                    'title' => $title,
                ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($response->failed()) {
            throw new RuntimeException('Doodstream upload failed (HTTP '.$response->status().').');
        }

        $data = $response->json();
        $result = $data['result'] ?? $data;

        $fileCode = $result['filecode'] ?? $result['file_code'] ?? null;
        if (! $fileCode) {
            throw new RuntimeException('Doodstream did not return a file code: '.$response->body());
        }

        return [
            'file_code' => $fileCode,
            'embed_url' => "https://dood.to/e/{$fileCode}",
        ];
    }

    private function resolveBaseUrl(string $url): string
    {
        $parsed = parse_url($url);

        return ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? 'dood.to');
    }
}
