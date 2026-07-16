<?php

namespace App\Services\PlayerDrivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Driver for StreamWish / StreamHG (streamwish.to, streamwish.com, ...).
 *
 * XFileSharing-based host that PAYS creators (CPM). Supports remote upload:
 * the host downloads the file itself from a source URL.
 *
 * API base: https://api.streamwish.com/api
 * Embed:    https://streamwish.to/e/{file_code}
 *
 * Remote upload flow (like Vidmoly):
 *   GET /api/upload/url?key=&url=  → returns a FUTURE filecode (queued)
 *   GET /api/file/info?key=&file_code=  → status 200 + canplay=1 once ready
 */
class StreamwishDriver implements PlayerDriverInterface, RemoteUploadInterface, FolderOrganizerInterface, FileStatusInterface
{
    public function name(): string
    {
        return 'streamwish';
    }

    public function matchesHost(string $host): bool
    {
        return (bool) preg_match('/(streamwish|embedwish|wishembed|streamhg|awish|dwish|swish|hlswish|obeywish|strwish|mwish|ewish|jwish|kwish|swh151|streamwish)/i', $host);
    }

    /* ====================================================================
     * REMOTE UPLOAD (the host downloads the file itself)
     * ==================================================================== */

    public function remoteUpload(string $sourceUrl, string $title, array $credentials): array
    {
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.streamwish.com/api', '/');
        if ($key === '') {
            throw new RuntimeException('StreamWish API key missing.');
        }

        // Keep the original name but force .mp4 so the host processes it.
        $name = $this->mp4NameFromUrl($sourceUrl);

        // Build the URL manually to avoid double-encoding the source URL (it may
        // already contain %5B, %60, …). Escape only the query-breaking chars.
        $escapedUrl = strtr($sourceUrl, [
            '&' => '%26',
            '?' => '%3F',
            '#' => '%23',
            '+' => '%2B',
            ' ' => '%20',
        ]);

        $apiUrl = "{$apiBase}/upload/url?" . http_build_query(['key' => $key, 'file_title' => $name]) . '&url=' . $escapedUrl;

        $resp = Http::timeout(30)->acceptJson()->get($apiUrl);

        if ($resp->failed()) {
            throw new RuntimeException('StreamWish remote upload failed (HTTP ' . $resp->status() . '). Body: ' . mb_substr($resp->body(), 0, 200));
        }

        $json = $resp->json();
        $status = (int) ($json['status'] ?? 200);
        if ($status !== 200) {
            $msg = $json['msg'] ?? 'unknown error';
            throw new RuntimeException("StreamWish refused ({$status}): {$msg}");
        }

        $code = $this->extractFileCode($json) ?? $this->extractFileCode($json['result'] ?? null);

        if ($code) {
            // "returns future filecode that will work after upload complete":
            // the download is only QUEUED. Return 'queued' so the batch polls
            // remoteStatus() until the file is actually downloaded & playable.
            return ['job_id' => $code, 'embed_url' => null, 'file_code' => $code, 'state' => 'queued'];
        }

        $body = mb_substr($resp->body(), 0, 300);
        throw new RuntimeException("StreamWish: pas de file_code retourné. Réponse: {$body}");
    }

    public function remoteStatus(string $jobId, array $credentials): array
    {
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.streamwish.com/api', '/');
        $embedBase = rtrim($credentials['embed_base'] ?? 'https://streamwish.to', '/');

        if ($jobId === '' || $key === '') {
            return ['state' => 'pending', 'embed_url' => null, 'file_code' => null, 'error' => null];
        }

        // The file only becomes available (status 200 + canplay) once StreamWish
        // has finished downloading AND encoding it. Until then, keep polling.
        try {
            $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/file/info", [
                'key' => $key,
                'file_code' => $jobId,
            ]);

            if ($resp->successful()) {
                $result = $resp->json('result');
                $entry = null;
                if (is_array($result)) {
                    $entry = (array_is_list($result) && isset($result[0]) && is_array($result[0]))
                        ? $result[0]
                        : $result;
                }

                if (is_array($entry)) {
                    $fileStatus = (int) ($entry['status'] ?? 0);
                    $canplay = (int) ($entry['canplay'] ?? 0);
                    $length = (int) ($entry['file_length'] ?? 0);

                    if ($fileStatus === 200 && ($canplay === 1 || $length > 0)) {
                        return [
                            'state' => 'done',
                            'embed_url' => "{$embedBase}/e/{$jobId}",
                            'file_code' => $jobId,
                            'error' => null,
                        ];
                    }
                }
            }
        } catch (Throwable) {
            // Network hiccup — keep pending, retry next poll.
        }

        return ['state' => 'pending', 'embed_url' => null, 'file_code' => $jobId, 'error' => null];
    }

    /* ====================================================================
     * LOCAL UPLOAD (aria2 downloaded file → StreamWish)
     * ==================================================================== */

    public function upload(string $localPath, string $title, array $credentials): array
    {
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.streamwish.com/api', '/');
        $embedBase = rtrim($credentials['embed_base'] ?? 'https://streamwish.to', '/');
        $timeout = (int) ($credentials['timeout'] ?? 900);

        if ($key === '') {
            throw new RuntimeException('StreamWish API key is missing.');
        }

        // 1. Get an upload server URL.
        $serverResp = Http::timeout(60)->acceptJson()->get("{$apiBase}/upload/server", ['key' => $key]);
        if ($serverResp->failed()) {
            throw new RuntimeException('Could not get StreamWish upload server (HTTP ' . $serverResp->status() . ').');
        }

        $uploadUrl = $serverResp->json('result');
        if (! is_string($uploadUrl) || $uploadUrl === '') {
            throw new RuntimeException('StreamWish did not return an upload URL. Response: ' . mb_substr($serverResp->body(), 0, 200));
        }

        // 2. Upload the file (field "file"), keep base name but force .mp4.
        $handle = fopen($localPath, 'r');
        if (! $handle) {
            throw new RuntimeException("Cannot open file: {$localPath}");
        }

        try {
            $response = Http::timeout($timeout)
                ->attach('file', $handle, $this->mp4NameFromUrl($localPath))
                ->post($uploadUrl, ['key' => $key]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($response->failed()) {
            throw new RuntimeException('StreamWish upload failed (HTTP ' . $response->status() . ').');
        }

        // Response: {"status":200,"files":[{"filecode":"...","status":"OK"}]}
        $code = null;
        $data = $response->json();
        if (is_array($data)) {
            $files = $data['files'] ?? null;
            if (is_array($files) && isset($files[0]) && is_array($files[0])) {
                $code = $files[0]['filecode'] ?? $files[0]['file_code'] ?? null;
            }
            $code = $code ?? $this->extractFileCode($data) ?? $this->extractFileCode($data['result'] ?? null);
        }

        if (! $code) {
            throw new RuntimeException('StreamWish: aucun file_code retourné. Réponse: ' . mb_substr($response->body(), 0, 300));
        }

        return [
            'file_code' => $code,
            'embed_url' => "{$embedBase}/e/{$code}",
        ];
    }

    /* ====================================================================
     * FOLDER ORGANIZATION
     *   GET /api/folder/list?key=&fld_id=      → result.folders[]{name, fld_id}
     *   GET /api/folder/create?key=&name=&parent_id=  → result.fld_id
     *   GET /api/file/edit?key=&file_code=&file_fld_id=  (move file)
     * ==================================================================== */

    public function ensureFolderPath(array $segments, array $credentials): ?string
    {
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.streamwish.com/api', '/');
        $key = $credentials['api_key'] ?? '';
        if ($key === '') {
            return null;
        }

        $parent = '0'; // root

        foreach ($segments as $segment) {
            $name = trim((string) preg_replace('/[\/\\\\:*?"<>|]/', '', $segment));
            if ($name === '') {
                continue;
            }

            $found = null;
            try {
                $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/folder/list", [
                    'key' => $key,
                    'fld_id' => $parent,
                ]);
                foreach ($resp->json('result.folders') ?? [] as $f) {
                    if (strcasecmp((string) ($f['name'] ?? ''), $name) === 0) {
                        $found = (string) ($f['fld_id'] ?? '');
                        break;
                    }
                }
            } catch (Throwable) {
                // ignore
            }

            if ($found === null || $found === '') {
                try {
                    $c = Http::timeout(20)->acceptJson()->get("{$apiBase}/folder/create", [
                        'key' => $key,
                        'name' => $name,
                        'parent_id' => $parent,
                    ]);
                    $found = (string) ($c->json('result.fld_id') ?? '');
                } catch (Throwable) {
                    return null;
                }
            }

            if (! $found || $found === '') {
                return null;
            }
            $parent = $found;
        }

        return $parent;
    }

    public function organizeFile(string $fileCode, ?string $folderId, ?string $title, array $credentials): bool
    {
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.streamwish.com/api', '/');
        $key = $credentials['api_key'] ?? '';
        if ($key === '') {
            return false;
        }

        // Move into folder via file/edit. Do NOT rename (keep original name).
        if ($folderId !== null && $folderId !== '' && $folderId !== '0') {
            try {
                $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/file/edit", [
                    'key' => $key,
                    'file_code' => $fileCode,
                    'file_fld_id' => $folderId,
                ]);

                return $resp->successful();
            } catch (Throwable) {
                return false;
            }
        }

        return true;
    }

    /* ====================================================================
     * FILE STATUS
     * ==================================================================== */

    public function fileExists(string $fileCode, array $credentials): ?bool
    {
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.streamwish.com/api', '/');
        $key = $credentials['api_key'] ?? '';
        if ($key === '') {
            return null;
        }

        try {
            $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/file/info", [
                'key' => $key,
                'file_code' => $fileCode,
            ]);
            if (! $resp->successful()) {
                return null;
            }
            $result = $resp->json('result');
            if (! is_array($result)) {
                return null;
            }
            $entry = (array_is_list($result) && isset($result[0]) && is_array($result[0])) ? $result[0] : $result;
            if (! is_array($entry)) {
                return null;
            }
            $st = (int) ($entry['status'] ?? 0);
            if ($st === 200) {
                return true;
            }
            if ($st === 404) {
                return false;
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /* ====================================================================
     * EXTRACT / DOWNLOAD
     * ==================================================================== */

    public function extractDirectUrl(string $embedUrl): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Referer' => 'https://streamwish.to/',
                ])
                ->get($embedUrl);

            if ($response->failed()) {
                return null;
            }
            $html = $response->body();

            if (preg_match('/sources\s*:\s*\[\s*\{[^}]*file\s*:\s*"([^"]+\.(?:mp4|m3u8)[^"]*)"/', $html, $m)) {
                return $m[1];
            }
            if (preg_match('/file\s*:\s*"(https?:\/\/[^"]+\.(?:mp4|m3u8)[^"]*)"/', $html, $m)) {
                return $m[1];
            }
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
                    'Referer' => 'https://streamwish.to/',
                ])
                ->get($directUrl);

            return $response->successful() && file_exists($destPath) && filesize($destPath) > 1024;
        } catch (Throwable) {
            return false;
        }
    }

    /* ====================================================================
     * HELPERS
     * ==================================================================== */

    /** Derive a filename from a URL/path and force a .mp4 extension. */
    private function mp4NameFromUrl(string $sourceUrl): string
    {
        $path = parse_url($sourceUrl, PHP_URL_PATH) ?: $sourceUrl;
        $base = urldecode(basename($path));
        $base = preg_replace('/\.[a-z0-9]{2,4}$/i', '', $base);
        $base = trim((string) $base);
        if ($base === '') {
            $base = 'video';
        }

        return $base . '.mp4';
    }

    private function extractFileCode(mixed $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }
        foreach (['file_code', 'filecode', 'code'] as $field) {
            if (! empty($data[$field]) && is_string($data[$field])) {
                return $data[$field];
            }
        }
        if (array_is_list($data) && isset($data[0]) && is_array($data[0])) {
            foreach (['file_code', 'filecode', 'code'] as $field) {
                if (! empty($data[0][$field]) && is_string($data[0][$field])) {
                    return $data[0][$field];
                }
            }
        }
        if (isset($data['result'])) {
            return $this->extractFileCode($data['result']);
        }

        return null;
    }
}
