<?php

namespace App\Services\PlayerDrivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Driver for FileMoon (filemoon.sx / filemoonapi.com).
 *
 * FileMoon supports remote upload — the host downloads the file itself from a
 * direct link. No download/upload through our server is needed.
 *
 * API base: https://filemoonapi.com/api/
 * Embed:    https://filemoon.sx/e/{file_code}
 */
class FilemoonDriver implements PlayerDriverInterface, RemoteUploadInterface, FolderOrganizerInterface, FileStatusInterface
{
    public function name(): string
    {
        return 'filemoon';
    }

    public function matchesHost(string $host): bool
    {
        return (bool) preg_match('/(filemoon|filemoonapi|moonplayer)/i', $host);
    }

    /* ====================================================================
     * REMOTE UPLOAD (the host fetches the link itself — no local download)
     *
     * GET /remote/add?key=&url=&fld_id=  → { result: { filecode: "abc123" } }
     * GET /remote/status?key=&file_code= → { result: { ... status ... } }
     * ==================================================================== */

    public function remoteUpload(string $sourceUrl, string $title, array $credentials): array
    {
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://filemoonapi.com/api', '/');
        $embedBase = rtrim($credentials['embed_base'] ?? 'https://filemoon.sx', '/');

        if ($key === '') {
            throw new RuntimeException('FileMoon API key missing.');
        }

        // Do NOT pass a custom name — keep the original filename from the source URL.
        $resp = Http::timeout(30)->acceptJson()->get("{$apiBase}/remote/add", [
            'key' => $key,
            'url' => $sourceUrl,
        ]);

        if ($resp->failed()) {
            throw new RuntimeException('FileMoon remote upload failed (HTTP ' . $resp->status() . ').');
        }

        $status = (int) $resp->json('status');
        if ($status !== 200) {
            $msg = $resp->json('msg') ?? 'unknown error';
            throw new RuntimeException("FileMoon remote add refused ({$status}: {$msg}).");
        }

        $result = $resp->json('result');
        $fileCode = null;

        if (is_array($result)) {
            $fileCode = $result['filecode'] ?? $result['file_code'] ?? null;
        }

        // FileMoon may return the file code immediately (instant) or queue it.
        if ($fileCode && is_string($fileCode) && $fileCode !== '') {
            return [
                'job_id' => $fileCode,
                'embed_url' => "{$embedBase}/e/{$fileCode}",
                'file_code' => $fileCode,
                'state' => 'done',
            ];
        }

        // Queued — will need to poll via remoteStatus().
        $jobId = is_array($result) ? ($result['id'] ?? $result['filecode'] ?? null) : null;

        return [
            'job_id' => $jobId,
            'embed_url' => null,
            'file_code' => null,
            'state' => 'queued',
        ];
    }

    public function remoteStatus(string $jobId, array $credentials): array
    {
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://filemoonapi.com/api', '/');
        $embedBase = rtrim($credentials['embed_base'] ?? 'https://filemoon.sx', '/');

        $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/remote/status", [
            'key' => $key,
            'file_code' => $jobId,
        ]);

        if (! $resp->successful()) {
            return ['state' => 'pending', 'embed_url' => null, 'file_code' => null, 'error' => null];
        }

        $result = $resp->json('result');

        // Parse the result — may be keyed by file_code or be a direct object.
        $entry = null;
        if (is_array($result)) {
            if (isset($result[$jobId]) && is_array($result[$jobId])) {
                $entry = $result[$jobId];
            } elseif (isset($result['filecode']) || isset($result['file_code'])) {
                $entry = $result;
            } else {
                // Iterate to find the first valid entry.
                foreach ($result as $val) {
                    if (is_array($val) && (isset($val['filecode']) || isset($val['file_code']) || isset($val['status']))) {
                        $entry = $val;
                        break;
                    }
                }
            }
        }

        if (! is_array($entry)) {
            // If no status info returned, check if the file_code itself is valid
            // by querying file/info — it may already be done.
            $exists = $this->fileExists($jobId, $credentials);
            if ($exists === true) {
                return [
                    'state' => 'done',
                    'embed_url' => "{$embedBase}/e/{$jobId}",
                    'file_code' => $jobId,
                    'error' => null,
                ];
            }

            return ['state' => 'pending', 'embed_url' => null, 'file_code' => null, 'error' => null];
        }

        $status = strtolower(trim((string) ($entry['status'] ?? '')));

        if (in_array($status, ['error', 'failed'], true)) {
            return ['state' => 'error', 'embed_url' => null, 'file_code' => null, 'error' => 'FileMoon remote download failed.'];
        }

        // Completed: look for the file code.
        $code = $entry['filecode'] ?? $entry['file_code'] ?? null;
        if ($code && is_string($code) && $code !== '') {
            return [
                'state' => 'done',
                'embed_url' => "{$embedBase}/e/{$code}",
                'file_code' => $code,
                'error' => null,
            ];
        }

        if (in_array($status, ['completed', 'done', 'finished'], true)) {
            // Status says done but no code — use the jobId as code.
            return [
                'state' => 'done',
                'embed_url' => "{$embedBase}/e/{$jobId}",
                'file_code' => $jobId,
                'error' => null,
            ];
        }

        return ['state' => 'pending', 'embed_url' => null, 'file_code' => null, 'error' => null];
    }

    /* ====================================================================
     * LOCAL UPLOAD (aria2 downloaded file → FileMoon)
     *
     * GET /upload/server?key= → { result: "https://..." }
     * POST multipart to that URL with field "file"
     * ==================================================================== */

    public function upload(string $localPath, string $title, array $credentials): array
    {
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://filemoonapi.com/api', '/');
        $embedBase = rtrim($credentials['embed_base'] ?? 'https://filemoon.sx', '/');

        if ($key === '') {
            throw new RuntimeException('FileMoon API key is missing.');
        }

        // 1. Get upload server URL.
        $serverResp = Http::timeout(60)->acceptJson()
            ->get("{$apiBase}/upload/server", ['key' => $key]);

        if ($serverResp->failed()) {
            throw new RuntimeException('Could not get FileMoon upload server (HTTP ' . $serverResp->status() . ').');
        }

        $uploadUrl = $serverResp->json('result');
        if (! is_string($uploadUrl) || $uploadUrl === '') {
            throw new RuntimeException('FileMoon did not return an upload URL. Response: ' . mb_substr($serverResp->body(), 0, 200));
        }

        // 2. Upload file via multipart POST — keep original filename.
        $handle = fopen($localPath, 'r');
        if (! $handle) {
            throw new RuntimeException("Cannot open file: {$localPath}");
        }

        try {
            $response = Http::timeout(1800)
                ->attach('file', $handle, basename($localPath))
                ->post($uploadUrl, ['key' => $key]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($response->failed()) {
            throw new RuntimeException('FileMoon upload failed (HTTP ' . $response->status() . '). Body: ' . mb_substr($response->body(), 0, 200));
        }

        // Parse the file code from the response.
        $data = $response->json();
        $result = is_array($data) ? ($data['result'] ?? $data) : [];
        $entry = (is_array($result) && isset($result[0]) && is_array($result[0])) ? $result[0] : $result;
        $code = is_array($entry) ? ($entry['filecode'] ?? $entry['file_code'] ?? null) : null;

        if (! $code) {
            // Fallback: scrape from body.
            if (preg_match('/"(?:file_?code)"\s*:\s*"([a-z0-9]+)"/i', $response->body(), $m)) {
                $code = $m[1];
            }
        }

        if (! $code) {
            throw new RuntimeException('FileMoon: aucun file_code retourné. Réponse: ' . mb_substr($response->body(), 0, 300));
        }

        return [
            'file_code' => $code,
            'embed_url' => "{$embedBase}/e/{$code}",
        ];
    }

    /* ====================================================================
     * FOLDER ORGANIZATION
     *
     * GET /folder/list?key=&fld_id=              → { result: { folders: [...], files: [...] } }
     * GET /folder/create?key=&name=&parent_id=   → { result: { fld_id: "26" } }
     * GET /file/set_folder?key=&file_code=&fld_id= (move file)
     * ==================================================================== */

    public function ensureFolderPath(array $segments, array $credentials): ?string
    {
        $apiBase = rtrim($credentials['api_base'] ?? 'https://filemoonapi.com/api', '/');
        $key = $credentials['api_key'] ?? '';
        if ($key === '') {
            return null;
        }

        $parent = null; // null = root

        foreach ($segments as $segment) {
            $name = trim((string) preg_replace('/[\/\\\\:*?"<>|]/', '', $segment));
            if ($name === '') {
                continue;
            }

            // Look for existing subfolder.
            $found = null;
            try {
                $params = ['key' => $key];
                if ($parent !== null) {
                    $params['fld_id'] = $parent;
                }
                $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/folder/list", $params);
                foreach ($resp->json('result.folders') ?? [] as $f) {
                    if (strcasecmp((string) ($f['name'] ?? ''), $name) === 0) {
                        $found = (string) ($f['fld_id'] ?? $f['id'] ?? '');
                        break;
                    }
                }
            } catch (Throwable) {
                // ignore
            }

            // Create if not found.
            if ($found === null || $found === '') {
                try {
                    $createParams = ['key' => $key, 'name' => $name];
                    if ($parent !== null) {
                        $createParams['parent_id'] = $parent;
                    }
                    $c = Http::timeout(20)->acceptJson()->get("{$apiBase}/folder/create", $createParams);
                    $found = (string) ($c->json('result.fld_id') ?? $c->json('result.id') ?? '');
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
        $apiBase = rtrim($credentials['api_base'] ?? 'https://filemoonapi.com/api', '/');
        $key = $credentials['api_key'] ?? '';
        if ($key === '') {
            return false;
        }

        // Move file into folder. Do NOT rename (keep original filename).
        if ($folderId !== null && $folderId !== '') {
            try {
                $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/file/set_folder", [
                    'key' => $key,
                    'file_code' => $fileCode,
                    'fld_id' => $folderId,
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
        $apiBase = rtrim($credentials['api_base'] ?? 'https://filemoonapi.com/api', '/');
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
            // result can be an array of entries or keyed by filecode.
            foreach ($result as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $status = (int) ($entry['status'] ?? 0);
                if ($status === 200) {
                    return true;
                }
                if ($status === 404) {
                    return false;
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /* ====================================================================
     * EXTRACT / DOWNLOAD (for cross-host transfers if needed)
     * ==================================================================== */

    public function extractDirectUrl(string $embedUrl): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Referer' => 'https://filemoon.sx/',
                ])
                ->get($embedUrl);

            if ($response->failed()) {
                return null;
            }

            $html = $response->body();

            // FileMoon uses packed JS (eval(function(p,a,c,k,e,d)...))
            if (preg_match('/file\s*:\s*"(https?:\/\/[^"]+\.m3u8[^"]*)"/', $html, $m)) {
                return $m[1];
            }
            if (preg_match('/sources\s*:\s*\[\s*\{[^}]*file\s*:\s*"([^"]+)"/', $html, $m)) {
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
                    'Referer' => 'https://filemoon.sx/',
                ])
                ->get($directUrl);

            return $response->successful() && file_exists($destPath) && filesize($destPath) > 1024;
        } catch (Throwable) {
            return false;
        }
    }
}
