<?php

namespace App\Services\PlayerDrivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Driver for Mp4upload.com.
 *
 * Mp4upload does NOT support remote upload (no /remotedl/add endpoint).
 * Files must be downloaded locally first, then uploaded via the cgi-bin
 * upload endpoint. The API uses /api/upload/server to get the upload URL
 * and a sess_id, then a multipart POST with field "file".
 *
 * Folder organization uses /api/folder/list, /api/folder/create and
 * /api/file/set_folder (NOT the standard XFileSharing paths).
 */
class Mp4uploadDriver implements PlayerDriverInterface, FolderOrganizerInterface, FileStatusInterface
{
    public function name(): string
    {
        return 'mp4upload';
    }

    public function matchesHost(string $host): bool
    {
        return str_contains($host, 'mp4upload');
    }

    /* ====================================================================
     * UPLOAD (local file → mp4upload cgi-bin)
     *
     * 1. GET /api/upload/server?key=  → { result: "http://sX.mp4upload.com/cgi-bin/upload.cgi", sess_id: "..." }
     * 2. POST multipart to that URL with fields: sess_id + file
     * 3. Response: JSON array [{ file_code: "...", file_status: "OK" }]
     * ==================================================================== */

    public function upload(string $localPath, string $title, array $credentials): array
    {
        $apiKey = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://www.mp4upload.com/api', '/');

        if ($apiKey === '') {
            throw new RuntimeException('Mp4upload target API key is missing.');
        }

        // 1. Get an upload server URL + sess_id.
        $serverResp = Http::timeout(60)->acceptJson()
            ->get("{$apiBase}/upload/server", ['key' => $apiKey]);

        if ($serverResp->failed()) {
            throw new RuntimeException('Could not get Mp4upload upload server (HTTP ' . $serverResp->status() . ').');
        }

        $uploadUrl = $serverResp->json('result');
        if (! is_string($uploadUrl) || $uploadUrl === '') {
            throw new RuntimeException('Mp4upload did not return an upload URL. Response: ' . mb_substr($serverResp->body(), 0, 200));
        }

        // The sess_id from the /upload/server response is MANDATORY.
        // Without it the upload silently fails (file never appears in account).
        $sessId = $serverResp->json('sess_id');
        if (! $sessId || $sessId === '') {
            throw new RuntimeException('Mp4upload did not return a sess_id. Response: ' . mb_substr($serverResp->body(), 0, 200));
        }

        // 2. Upload the file via multipart POST.
        // Per the official docs the form has: name="sess_id" + name="file" type="file"
        $handle = fopen($localPath, 'r');
        if (! $handle) {
            throw new RuntimeException("Cannot open file: {$localPath}");
        }

        try {
            $response = Http::timeout(1800)
                ->attach('file', $handle, basename($localPath))
                ->post($uploadUrl, [
                    'sess_id' => $sessId,
                ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($response->failed()) {
            throw new RuntimeException('Mp4upload upload failed (HTTP ' . $response->status() . '). Body: ' . mb_substr($response->body(), 0, 200));
        }

        // 3. Parse the response to get the file_code.
        // Response format: JSON array [{"file_code":"abc123","file_status":"OK"}]
        // or sometimes just {"file_code":"abc123",...}
        $code = null;
        $data = $response->json();
        if (is_array($data)) {
            $entry = isset($data[0]) && is_array($data[0]) ? $data[0] : $data;
            $code = $entry['file_code'] ?? $entry['filecode'] ?? null;
        }
        // Fallback: scrape the code from the raw body.
        if (! $code && preg_match('/"file_code"\s*:\s*"([A-Za-z0-9]+)"|embed-([A-Za-z0-9]+)/', $response->body(), $m)) {
            $code = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? null);
        }

        if (! $code) {
            throw new RuntimeException('Mp4upload: aucun file_code retourné. Réponse: ' . mb_substr($response->body(), 0, 300));
        }

        return [
            'file_code' => $code,
            'embed_url' => "https://www.mp4upload.com/embed-{$code}.html",
        ];
    }

    /* ====================================================================
     * FOLDER ORGANIZATION
     *
     * Mp4upload uses:
     *   /api/folder/list?key=&fld_id=       → { result: { folders: [...], files: [...] } }
     *   /api/folder/create?key=&parent_id=&name=  → { result: { fld_id: "26" } }
     *   /api/file/set_folder?key=&file_code=&fld_id=  → move file into folder
     * ==================================================================== */

    public function ensureFolderPath(array $segments, array $credentials): ?string
    {
        $apiBase = rtrim($credentials['api_base'] ?? 'https://www.mp4upload.com/api', '/');
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

            // Look for existing subfolder with this name.
            $found = null;
            try {
                $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/folder/list", [
                    'key' => $key,
                    'fld_id' => $parent,
                ]);
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
                    $c = Http::timeout(20)->acceptJson()->get("{$apiBase}/folder/create", [
                        'key' => $key,
                        'parent_id' => $parent,
                        'name' => $name,
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
        $apiBase = rtrim($credentials['api_base'] ?? 'https://www.mp4upload.com/api', '/');
        $key = $credentials['api_key'] ?? '';
        if ($key === '') {
            return false;
        }

        // Move file into folder. Do NOT rename (keep original filename).
        if ($folderId !== null && $folderId !== '' && $folderId !== '0') {
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
     * FILE STATUS (check if a file exists on mp4upload)
     * ==================================================================== */

    public function fileExists(string $fileCode, array $credentials): ?bool
    {
        $apiBase = rtrim($credentials['api_base'] ?? 'https://www.mp4upload.com/api', '/');
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
            foreach ($result as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                if ((int) ($entry['status'] ?? 0) === 200) {
                    return true;
                }
                if ((int) ($entry['status'] ?? 0) === 404) {
                    return false;
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /* ====================================================================
     * EXTRACT / DOWNLOAD (for cross-host transfers)
     * ==================================================================== */

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
}
