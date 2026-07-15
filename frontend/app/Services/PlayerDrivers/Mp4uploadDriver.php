<?php

namespace App\Services\PlayerDrivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Driver for Mp4upload.com (XFileSharing-based).
 */
class Mp4uploadDriver implements PlayerDriverInterface, RemoteUploadInterface, FolderOrganizerInterface
{
    use OrganizesViaXFileSharing;

    public function name(): string
    {
        return 'mp4upload';
    }

    /* ---- Folder organization (standard XFileSharing) ---- */

    protected function xfsBase(array $credentials): string
    {
        return rtrim($credentials['api_base'] ?? 'https://www.mp4upload.com/api', '/');
    }

    protected function xfsAuth(array $credentials): array
    {
        return ['key' => $credentials['api_key'] ?? ''];
    }

    /** XFileSharing remote upload: {api}/upload/url?key=&url= */
    public function remoteUpload(string $sourceUrl, string $title, array $credentials): array
    {
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://www.mp4upload.com/api', '/');
        if ($key === '') {
            throw new RuntimeException('Mp4upload API key missing.');
        }

        $resp = Http::timeout(30)->acceptJson()->get("{$apiBase}/upload/url", [
            'key' => $key, 'url' => $sourceUrl, 'file_title' => $title ?: null,
        ]);

        if ($resp->failed()) {
            throw new RuntimeException('Mp4upload remote upload failed (HTTP '.$resp->status().').');
        }

        $data = $resp->json();
        $entry = is_array($data) && isset($data[0]) ? $data[0] : $data;
        $code = $entry['file_code'] ?? $entry['filecode'] ?? null;

        return $code
            ? ['job_id' => $code, 'embed_url' => "https://www.mp4upload.com/embed-{$code}.html", 'file_code' => $code, 'state' => 'done']
            : ['job_id' => null, 'embed_url' => null, 'file_code' => null, 'state' => 'queued'];
    }

    public function remoteStatus(string $jobId, array $credentials): array
    {
        return $jobId
            ? ['state' => 'done', 'embed_url' => "https://www.mp4upload.com/embed-{$jobId}.html", 'file_code' => $jobId, 'error' => null]
            : ['state' => 'pending', 'embed_url' => null, 'file_code' => null, 'error' => null];
    }

    public function matchesHost(string $host): bool
    {
        return str_contains($host, 'mp4upload');
    }

    public function extractDirectUrl(string $embedUrl): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                    'Referer' => 'https://www.mp4upload.com/',
                ])
                ->get($embedUrl);

            if ($response->failed()) {
                return null;
            }

            $html = $response->body();

            if (preg_match('/src\s*:\s*"(https?:\/\/[^"]+\.mp4[^"]*)"/', $html, $m)) {
                return $m[1];
            }
            if (preg_match('/file\s*:\s*"(https?:\/\/[^"]+\.mp4[^"]*)"/', $html, $m)) {
                return $m[1];
            }
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
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                    'Referer' => 'https://www.mp4upload.com/',
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
        $apiBase = rtrim($credentials['api_base'] ?? 'https://www.mp4upload.com/api', '/');

        if ($apiKey === '') {
            throw new RuntimeException('Mp4upload target API key is missing.');
        }

        // 1. Get upload server
        $serverResp = Http::timeout(60)->acceptJson()
            ->get("{$apiBase}/upload/server", ['key' => $apiKey]);

        if ($serverResp->failed()) {
            throw new RuntimeException('Could not get Mp4upload server.');
        }

        $uploadUrl = $serverResp->json('result');
        if (! is_string($uploadUrl) || $uploadUrl === '') {
            throw new RuntimeException('Mp4upload did not return an upload URL.');
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
                    'key' => $apiKey,
                    'file_title' => $title,
                ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($response->failed()) {
            throw new RuntimeException('Mp4upload upload failed.');
        }

        $data = $response->json();
        $entry = is_array($data) && isset($data[0]) ? $data[0] : $data;
        $code = $entry['file_code'] ?? $entry['filecode'] ?? null;

        if (! $code) {
            throw new RuntimeException('Mp4upload did not return a file code.');
        }

        return [
            'file_code' => $code,
            'embed_url' => "https://www.mp4upload.com/embed-{$code}.html",
        ];
    }
}
