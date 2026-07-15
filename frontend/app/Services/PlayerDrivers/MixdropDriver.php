<?php

namespace App\Services\PlayerDrivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Driver for MixDrop (mixdrop.ag).
 *
 * MixDrop supports remote upload — the host downloads the file itself from a
 * direct link. Auth uses email + API key.
 *
 * API base: https://api.mixdrop.ag
 * Upload:   https://ul.mixdrop.ag/api
 * Embed:    https://mixdrop.ag/e/{fileref}
 */
class MixdropDriver implements PlayerDriverInterface, RemoteUploadInterface, FolderOrganizerInterface, FileStatusInterface
{
    public function name(): string
    {
        return 'mixdrop';
    }

    public function matchesHost(string $host): bool
    {
        return (bool) preg_match('/(mixdrop)/i', $host);
    }

    /* ====================================================================
     * REMOTE UPLOAD
     *
     * GET /remoteupload?email=&key=&url=&folder=
     * → { success: true, result: { id, fileref, url, embedurl } }
     *
     * GET /remotestatus?email=&key=&id=
     * → { success: true, result: { status, fileref, progress, ... } }
     * ==================================================================== */

    public function remoteUpload(string $sourceUrl, string $title, array $credentials): array
    {
        $email = $credentials['email'] ?? '';
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.mixdrop.ag', '/');
        $embedBase = rtrim($credentials['embed_base'] ?? 'https://mixdrop.ag', '/');

        if ($email === '' || $key === '') {
            throw new RuntimeException('MixDrop credentials (email/api_key) missing.');
        }

        // Do NOT pass 'name' — keep the original filename from the source URL.
        $resp = Http::timeout(30)->acceptJson()->get("{$apiBase}/remoteupload", [
            'email' => $email,
            'key' => $key,
            'url' => $sourceUrl,
        ]);

        if ($resp->failed()) {
            throw new RuntimeException('MixDrop remote upload failed (HTTP ' . $resp->status() . ').');
        }

        $data = $resp->json();
        if (! ($data['success'] ?? false)) {
            $msg = $data['result']['msg'] ?? $data['result'] ?? 'unknown error';
            throw new RuntimeException("MixDrop remote upload refused: {$msg}");
        }

        $result = $data['result'] ?? [];
        $fileRef = $result['fileref'] ?? null;
        $embedUrl = $result['embedurl'] ?? null;
        $jobId = $result['id'] ?? null;

        // MixDrop often returns the fileref immediately.
        if ($fileRef && is_string($fileRef) && $fileRef !== '') {
            return [
                'job_id' => (string) ($jobId ?? $fileRef),
                'embed_url' => $embedUrl ?: "{$embedBase}/e/{$fileRef}",
                'file_code' => $fileRef,
                'state' => 'done',
            ];
        }

        // Queued — poll via remotestatus later.
        return [
            'job_id' => (string) ($jobId ?? ''),
            'embed_url' => null,
            'file_code' => null,
            'state' => 'queued',
        ];
    }

    public function remoteStatus(string $jobId, array $credentials): array
    {
        $email = $credentials['email'] ?? '';
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.mixdrop.ag', '/');
        $embedBase = rtrim($credentials['embed_base'] ?? 'https://mixdrop.ag', '/');

        $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/remotestatus", [
            'email' => $email,
            'key' => $key,
            'id' => $jobId,
        ]);

        if (! $resp->successful()) {
            return ['state' => 'pending', 'embed_url' => null, 'file_code' => null, 'error' => null];
        }

        $data = $resp->json();
        $result = $data['result'] ?? [];

        if (! is_array($result)) {
            return ['state' => 'pending', 'embed_url' => null, 'file_code' => null, 'error' => null];
        }

        $status = strtolower(trim((string) ($result['status'] ?? '')));

        if ($status === 'error' || ! empty($result['error'])) {
            return ['state' => 'error', 'embed_url' => null, 'file_code' => null, 'error' => $result['error'] ?? 'MixDrop remote download failed.'];
        }

        if ($status === 'complete' || $status === 'completed') {
            $fileRef = $result['fileref'] ?? null;
            if ($fileRef && is_string($fileRef) && $fileRef !== '') {
                return [
                    'state' => 'done',
                    'embed_url' => "{$embedBase}/e/{$fileRef}",
                    'file_code' => $fileRef,
                    'error' => null,
                ];
            }
        }

        // Still downloading / in queue.
        return ['state' => 'pending', 'embed_url' => null, 'file_code' => null, 'error' => null];
    }

    /* ====================================================================
     * LOCAL UPLOAD (aria2 downloaded file → MixDrop)
     *
     * POST https://ul.mixdrop.ag/api  (multipart: email + key + file)
     * → { success: true, result: { fileref, url, embedurl } }
     * ==================================================================== */

    public function upload(string $localPath, string $title, array $credentials): array
    {
        $email = $credentials['email'] ?? '';
        $key = $credentials['api_key'] ?? '';
        $embedBase = rtrim($credentials['embed_base'] ?? 'https://mixdrop.ag', '/');

        if ($email === '' || $key === '') {
            throw new RuntimeException('MixDrop credentials (email/api_key) missing.');
        }

        $handle = fopen($localPath, 'r');
        if (! $handle) {
            throw new RuntimeException("Cannot open file: {$localPath}");
        }

        try {
            // Keep original filename.
            $response = Http::timeout(1800)
                ->attach('file', $handle, basename($localPath))
                ->post('https://ul.mixdrop.ag/api', [
                    'email' => $email,
                    'key' => $key,
                ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($response->failed()) {
            throw new RuntimeException('MixDrop upload failed (HTTP ' . $response->status() . ').');
        }

        $data = $response->json();
        if (! ($data['success'] ?? false)) {
            $msg = $data['result']['msg'] ?? 'unknown error';
            throw new RuntimeException("MixDrop upload refused: {$msg}");
        }

        $result = $data['result'] ?? [];
        $fileRef = $result['fileref'] ?? null;

        if (! $fileRef) {
            throw new RuntimeException('MixDrop: aucun fileref retourné. Réponse: ' . mb_substr($response->body(), 0, 300));
        }

        return [
            'file_code' => $fileRef,
            'embed_url' => $result['embedurl'] ?? "{$embedBase}/e/{$fileRef}",
        ];
    }

    /* ====================================================================
     * FOLDER ORGANIZATION
     *
     * GET /folderlist?email=&key=&id=        → { result: { folders: [...], files: [...] } }
     * GET /foldercreate?email=&key=&title=&parent=  → { result: { id: "5" } }
     * GET /filemove?email=&key=&ref[]=&folder=      → move file into folder
     * ==================================================================== */

    public function ensureFolderPath(array $segments, array $credentials): ?string
    {
        $email = $credentials['email'] ?? '';
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.mixdrop.ag', '/');
        if ($email === '' || $key === '') {
            return null;
        }

        $parent = '0'; // root = 0

        foreach ($segments as $segment) {
            $name = trim((string) preg_replace('/[\/\\\\:*?"<>|]/', '', $segment));
            if ($name === '') {
                continue;
            }

            // Look for existing subfolder.
            $found = null;
            try {
                $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/folderlist", [
                    'email' => $email,
                    'key' => $key,
                    'id' => $parent,
                ]);
                foreach ($resp->json('result.folders') ?? [] as $f) {
                    if (strcasecmp((string) ($f['title'] ?? ''), $name) === 0) {
                        $found = (string) ($f['id'] ?? '');
                        break;
                    }
                }
            } catch (Throwable) {
                // ignore
            }

            // Create if not found.
            if ($found === null || $found === '') {
                try {
                    $createParams = [
                        'email' => $email,
                        'key' => $key,
                        'title' => $name,
                    ];
                    if ($parent !== '0') {
                        $createParams['parent'] = $parent;
                    }
                    $c = Http::timeout(20)->acceptJson()->get("{$apiBase}/foldercreate", $createParams);
                    $found = (string) ($c->json('result.id') ?? '');
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
        $email = $credentials['email'] ?? '';
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.mixdrop.ag', '/');
        if ($email === '' || $key === '') {
            return false;
        }

        // Move file into folder. Do NOT rename (keep original filename).
        if ($folderId !== null && $folderId !== '' && $folderId !== '0') {
            try {
                $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/filemove", [
                    'email' => $email,
                    'key' => $key,
                    'ref[]' => $fileCode,
                    'folder' => $folderId,
                ]);

                return (bool) ($resp->json('success') ?? false);
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
        $email = $credentials['email'] ?? '';
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.mixdrop.ag', '/');
        if ($email === '' || $key === '') {
            return null;
        }

        try {
            $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/fileinfo2", [
                'email' => $email,
                'key' => $key,
                'ref[]' => $fileCode,
            ]);
            if (! $resp->successful()) {
                return null;
            }
            $result = $resp->json('result');
            if (! is_array($result)) {
                return null;
            }
            $entry = $result[$fileCode] ?? null;
            if (! is_array($entry)) {
                return null;
            }
            $status = strtolower((string) ($entry['status'] ?? ''));
            if ($status === 'notfound') {
                return false;
            }
            if ($status === 'ok' || ! empty($entry['fileref'])) {
                return true;
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
                    'Referer' => 'https://mixdrop.ag/',
                ])
                ->get($embedUrl);

            if ($response->failed()) {
                return null;
            }

            $html = $response->body();

            if (preg_match('/MDCore\.wurl\s*=\s*"(\/\/[^"]+)"/', $html, $m)) {
                return 'https:' . $m[1];
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
                    'Referer' => 'https://mixdrop.ag/',
                ])
                ->get($directUrl);

            return $response->successful() && file_exists($destPath) && filesize($destPath) > 1024;
        } catch (Throwable) {
            return false;
        }
    }
}
