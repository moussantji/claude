<?php

namespace App\Services\PlayerDrivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Driver for Vidmoly (XFileSharing-based host).
 *
 * Extract: parse the embed page JS to find the direct .mp4/.m3u8 source.
 * Upload: standard XFileSharing API flow (get upload server → POST file).
 */
class VidmolyDriver implements PlayerDriverInterface
{
    public function name(): string
    {
        return 'vidmoly';
    }

    public function matchesHost(string $host): bool
    {
        return str_contains($host, 'vidmoly');
    }

    public function extractDirectUrl(string $embedUrl): ?string
    {
        try {
            // Normalize: ensure it's an embed URL
            $embedUrl = $this->normalizeEmbedUrl($embedUrl);

            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Referer' => 'https://vidmoly.to/',
                ])
                ->get($embedUrl);

            if ($response->failed()) {
                return null;
            }

            $html = $response->body();

            // Method 1: Look for sources array in JS (common XFileSharing pattern)
            // Pattern: sources: [{file:"https://...mp4"...}]
            if (preg_match('/sources\s*:\s*\[\s*\{[^}]*file\s*:\s*"([^"]+\.mp4[^"]*)"/', $html, $m)) {
                return $m[1];
            }

            // Method 2: Look for file:"..." in player setup
            if (preg_match('/file\s*:\s*"(https?:\/\/[^"]+\.(?:mp4|m3u8)[^"]*)"/', $html, $m)) {
                return $m[1];
            }

            // Method 3: Look for packed JS and decode
            if (preg_match("/eval\(function\(p,a,c,k,e,[dr]\).+?\{.*?\}\('([^']+)'/s", $html, $packed)) {
                $decoded = $this->unpackJs($packed[0]);
                if ($decoded && preg_match('/(https?:\/\/[^\s"\']+\.(?:mp4|m3u8)[^\s"\']*)/i', $decoded, $dm)) {
                    return $dm[1];
                }
            }

            // Method 4: Direct src= or source src= in HTML
            if (preg_match('/(?:src|source)\s*=\s*"(https?:\/\/[^"]+\.(?:mp4|m3u8)[^"]*)"/', $html, $m)) {
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
                    'Referer' => 'https://vidmoly.to/',
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
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.vidmoly.to', '/');
        $embedBase = rtrim($credentials['embed_base'] ?? 'https://vidmoly.to', '/');
        $timeout = (int) ($credentials['timeout'] ?? 900);

        if ($apiKey === '') {
            throw new RuntimeException('Vidmoly target API key is missing.');
        }

        // 1. Get upload server
        $serverResp = Http::timeout($timeout)->acceptJson()
            ->get("{$apiBase}/api/upload/server", ['key' => $apiKey]);

        if ($serverResp->failed()) {
            throw new RuntimeException('Could not get vidmoly upload server (HTTP '.$serverResp->status().').');
        }

        $uploadUrl = $serverResp->json('result');
        $sessId = $serverResp->json('sess_id');

        if (! is_string($uploadUrl) || $uploadUrl === '') {
            throw new RuntimeException('Vidmoly did not return an upload URL.');
        }

        // 2. Upload file
        $fields = ['key' => $apiKey];
        if ($sessId) {
            $fields['sess_id'] = $sessId;
        }
        if ($title) {
            $fields['file_title'] = $title;
        }

        $handle = fopen($localPath, 'r');
        if (! $handle) {
            throw new RuntimeException("Cannot open file: {$localPath}");
        }

        try {
            $response = Http::timeout($timeout)
                ->attach('file', $handle, basename($localPath))
                ->post($uploadUrl, $fields);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($response->failed()) {
            throw new RuntimeException('Vidmoly upload failed (HTTP '.$response->status().').');
        }

        $code = $this->extractFileCode($response->json());
        if (! $code) {
            throw new RuntimeException('Vidmoly did not return a file code: '.$response->body());
        }

        return [
            'file_code' => $code,
            'embed_url' => "{$embedBase}/embed-{$code}.html",
        ];
    }

    private function normalizeEmbedUrl(string $url): string
    {
        // Convert page URL to embed if needed
        if (preg_match('/vidmoly\.to\/(?:embed-)?([a-z0-9]+)/i', $url, $m)) {
            return "https://vidmoly.to/embed-{$m[1]}.html";
        }

        return $url;
    }

    private function extractFileCode(mixed $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }
        $entry = (array_is_list($data) && isset($data[0]) && is_array($data[0])) ? $data[0] : $data;
        foreach (['file_code', 'filecode', 'code'] as $field) {
            if (! empty($entry[$field]) && is_string($entry[$field])) {
                return $entry[$field];
            }
        }

        return null;
    }

    /**
     * Minimal JS p.a.c.k.e.r unpacker for extracting URLs from obfuscated code.
     * Handles the standard Dean Edwards packer format:
     *   eval(function(p,a,c,k,e,d){...}('payload',base,count,'kw1|kw2|...'.split('|')...))
     */
    private function unpackJs(string $packed): ?string
    {
        if (! preg_match("/\}\s*\(\s*'(.+?)'\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*'([^']*)'\.split\('\|'\)/s", $packed, $m)) {
            return null;
        }

        $payload = $m[1];
        $base = (int) $m[2];
        $keywords = explode('|', $m[4]);

        // Replace each token in the payload with its keyword from the dictionary.
        return preg_replace_callback('/\b\w+\b/', function ($match) use ($keywords, $base) {
            $n = $this->baseDecode($match[0], $base);

            return ($n >= 0 && $n < count($keywords) && $keywords[$n] !== '')
                ? $keywords[$n]
                : $match[0];
        }, $payload);
    }

    /**
     * Decode a string encoded in the given base (supports up to base 62).
     */
    private function baseDecode(string $str, int $base): int
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $num = 0;
        $len = strlen($str);

        for ($i = 0; $i < $len; $i++) {
            $pos = strpos($chars, $str[$i]);
            if ($pos === false || $pos >= $base) {
                return -1;
            }
            $num = $num * $base + $pos;
        }

        return $num;
    }
}
