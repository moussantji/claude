<?php

namespace App\Services\PlayerDrivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Driver for Streamtape (streamtape.com, streamtape.to, etc.).
 *
 * Extract: Streamtape hides the direct link in obfuscated JS. We reconstruct it.
 * Upload: Uses the Streamtape API.
 */
class StreamtapeDriver implements PlayerDriverInterface, RemoteUploadInterface
{
    public function name(): string
    {
        return 'streamtape';
    }

    /**
     * Streamtape remote upload — the target server downloads the URL itself.
     * Docs: /remotedl/add?login=&key=&url=&name=  → returns result.id
     */
    public function remoteUpload(string $sourceUrl, string $title, array $credentials): array
    {
        $login = $credentials['login'] ?? '';
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.streamtape.com', '/');

        if ($login === '' || $key === '') {
            throw new RuntimeException('Streamtape credentials (login/api_key) missing.');
        }

        $resp = Http::timeout(30)->acceptJson()->get("{$apiBase}/remotedl/add", [
            'login' => $login,
            'key' => $key,
            'url' => $sourceUrl,
            'name' => $title !== '' ? $title.'.mp4' : null,
        ]);

        if ($resp->failed() || (int) $resp->json('status') !== 200) {
            throw new RuntimeException('Streamtape remote add failed: '.($resp->json('msg') ?? $resp->status()));
        }

        return [
            'job_id' => $resp->json('result.id'),
            'embed_url' => null,
            'file_code' => null,
            'state' => 'queued',
        ];
    }

    public function remoteStatus(string $jobId, array $credentials): array
    {
        $login = $credentials['login'] ?? '';
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.streamtape.com', '/');

        $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/remotedl/status", [
            'login' => $login, 'key' => $key, 'id' => $jobId,
        ]);

        $entry = $resp->json("result.{$jobId}");
        if (! is_array($entry)) {
            return ['state' => 'pending', 'embed_url' => null, 'file_code' => null, 'error' => null];
        }

        $status = $entry['status'] ?? 'new';
        // Streamtape statuses: new, downloading, ... , finished
        if (in_array($status, ['finished', 'completed'], true) || ! empty($entry['url'])) {
            $fileId = $entry['extid'] ?: null;
            $url = $entry['url'] ?: ($fileId ? "https://streamtape.com/e/{$fileId}" : null);

            return ['state' => 'done', 'embed_url' => $url, 'file_code' => $fileId, 'error' => null];
        }
        if (in_array($status, ['error', 'failed'], true)) {
            return ['state' => 'error', 'embed_url' => null, 'file_code' => null, 'error' => 'remote download failed'];
        }

        return ['state' => 'pending', 'embed_url' => null, 'file_code' => null, 'error' => null];
    }

    public function matchesHost(string $host): bool
    {
        return str_contains($host, 'streamtape') || str_contains($host, 'strtape') || str_contains($host, 'strcloud');
    }

    public function extractDirectUrl(string $embedUrl): ?string
    {
        try {
            // Normalize to embed
            $embedUrl = preg_replace('#/v/([a-z0-9]+)#i', '/e/$1', $embedUrl);

            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Referer' => 'https://streamtape.com/',
                ])
                ->get($embedUrl);

            if ($response->failed()) {
                return null;
            }

            $html = $response->body();

            // Streamtape pattern: innerHTML splits the URL across two parts
            // document.getElementById('XXX').innerHTML = "..." + "..."
            // The pattern is: //streamtape.com/get_video?id=...&expires=...&ip=...&token=...
            if (preg_match("/innerHTML\s*=\s*[\"'](\/\/[^\"']+)[\"']\s*\+\s*\('([^']+)'\)/", $html, $m)) {
                return 'https:'.$m[1].$m[2];
            }

            // Alternative: just find the /get_video URL
            if (preg_match('#(//(?:streamtape|strtape)[^"\']+/get_video[^"\']+)#i', $html, $m)) {
                $url = 'https:'.$m[1];
                // Follow redirect to get final URL
                $headResp = Http::timeout(15)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0', 'Referer' => $embedUrl])
                    ->withOptions(['allow_redirects' => false])
                    ->get($url);
                $location = $headResp->header('Location');

                return $location ?: $url;
            }

            // Fallback: look for any .mp4 URL
            if (preg_match('/(https?:\/\/[^\s"\']+\.mp4[^\s"\']*)/i', $html, $m)) {
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
                    'Referer' => 'https://streamtape.com/',
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
        $apiKey = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.streamtape.com', '/');

        if ($login === '' || $apiKey === '') {
            throw new RuntimeException('Streamtape target credentials (login/api_key) are missing.');
        }

        // 1. Get upload URL
        $serverResp = Http::timeout(60)->acceptJson()
            ->get("{$apiBase}/file/ul", [
                'login' => $login,
                'key' => $apiKey,
            ]);

        if ($serverResp->failed()) {
            throw new RuntimeException('Could not get Streamtape upload server (HTTP '.$serverResp->status().').');
        }

        $uploadUrl = $serverResp->json('result.url');
        if (! $uploadUrl) {
            throw new RuntimeException('Streamtape did not return an upload URL: '.$serverResp->body());
        }

        // 2. Upload file
        $handle = fopen($localPath, 'r');
        if (! $handle) {
            throw new RuntimeException("Cannot open file: {$localPath}");
        }

        try {
            $response = Http::timeout(900)
                ->attach('file1', $handle, $title ?: basename($localPath))
                ->post($uploadUrl);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($response->failed()) {
            throw new RuntimeException('Streamtape upload failed (HTTP '.$response->status().').');
        }

        $data = $response->json();
        $result = $data['result'] ?? $data;
        $fileId = $result['id'] ?? null;
        $url = $result['url'] ?? null;

        if (! $fileId && $url) {
            if (preg_match('/\/([a-z0-9]+)(?:\/|$)/i', $url, $m)) {
                $fileId = $m[1];
            }
        }

        if (! $fileId) {
            throw new RuntimeException('Streamtape did not return a file ID: '.$response->body());
        }

        return [
            'file_code' => $fileId,
            'embed_url' => "https://streamtape.com/e/{$fileId}",
        ];
    }
}
