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
class StreamtapeDriver implements PlayerDriverInterface, RemoteUploadInterface, FolderOrganizerInterface, FileStatusInterface
{
    public function name(): string
    {
        return 'streamtape';
    }

    /** Check file existence via /file/info?file={code} (status 200 = present). */
    public function fileExists(string $fileCode, array $credentials): ?bool
    {
        $login = $credentials['login'] ?? '';
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.streamtape.com', '/');
        if ($login === '' || $key === '') {
            return null;
        }

        try {
            $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/file/info", [
                'login' => $login, 'key' => $key, 'file' => $fileCode,
            ]);
            if (! $resp->successful()) {
                return null;
            }
            $entry = $resp->json("result.{$fileCode}");
            if (is_array($entry)) {
                return (int) ($entry['status'] ?? 0) === 200;
            }
            $result = $resp->json('result');
            if (is_array($result)) {
                foreach ($result as $e) {
                    if (is_array($e)) {
                        return (int) ($e['status'] ?? 0) === 200;
                    }
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /* ---- Folder organization (Streamtape file management API) ---- */

    public function ensureFolderPath(array $segments, array $credentials): ?string
    {
        $login = $credentials['login'] ?? '';
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.streamtape.com', '/');
        if ($login === '' || $key === '') {
            return null;
        }

        $parent = null; // null = root (Streamtape: omit the param for root)
        foreach ($segments as $segment) {
            $name = trim((string) preg_replace('/[\/\\\\:*?"<>|]/', '', $segment));
            if ($name === '') {
                continue;
            }

            // Look for an existing subfolder with this name.
            $found = null;
            try {
                $listParams = ['login' => $login, 'key' => $key];
                if ($parent !== null) {
                    $listParams['folder'] = $parent;
                }
                $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/file/listfolder", $listParams);
                foreach ($resp->json('result.folders') ?? [] as $f) {
                    if (strcasecmp((string) ($f['name'] ?? ''), $name) === 0) {
                        $found = (string) ($f['id'] ?? '');
                        break;
                    }
                }
            } catch (Throwable) {
                // ignore
            }

            if ($found === null || $found === '') {
                try {
                    $createParams = ['login' => $login, 'key' => $key, 'name' => $name];
                    if ($parent !== null) {
                        $createParams['pid'] = $parent; // omit at root
                    }
                    $c = Http::timeout(20)->acceptJson()->get("{$apiBase}/file/createfolder", $createParams);
                    $found = $c->json('result.folderid');
                } catch (Throwable) {
                    return null;
                }
            }

            if (! $found) {
                return null;
            }
            $parent = (string) $found;
        }

        return $parent;
    }

    public function organizeFile(string $fileCode, ?string $folderId, ?string $title, array $credentials): bool
    {
        $login = $credentials['login'] ?? '';
        $key = $credentials['api_key'] ?? '';
        $apiBase = rtrim($credentials['api_base'] ?? 'https://api.streamtape.com', '/');
        if ($login === '' || $key === '') {
            return false;
        }

        $ok = true;
        if ($folderId !== null && $folderId !== '') {
            try {
                $resp = Http::timeout(20)->acceptJson()->get("{$apiBase}/file/move", [
                    'login' => $login, 'key' => $key, 'file' => $fileCode, 'folder' => $folderId,
                ]);
                $ok = $resp->successful();
            } catch (Throwable) {
                $ok = false;
            }
        }

        // Do NOT rename — keep the original filename and extension as-is.

        return $ok;
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

        // Do NOT pass 'name' — let Streamtape use the default filename from
        // the source URL (keeps original name + extension as-is on the player).
        $resp = Http::timeout(30)->acceptJson()->get("{$apiBase}/remotedl/add", [
            'login' => $login,
            'key' => $key,
            'url' => $sourceUrl,
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

        if (! $resp->successful()) {
            return ['state' => 'pending', 'embed_url' => null, 'file_code' => null, 'error' => null];
        }

        // The API can return the entry directly as result.{id} or as the first
        // (and only) element inside result when queried by a single id.
        $result = $resp->json('result');
        $entry = null;

        if (is_array($result)) {
            // Try by key first: result.{jobId}
            if (isset($result[$jobId]) && is_array($result[$jobId])) {
                $entry = $result[$jobId];
            } else {
                // Some API versions return result as a single object directly
                // when queried by id, or the first array element.
                foreach ($result as $val) {
                    if (is_array($val) && isset($val['id'])) {
                        $entry = $val;
                        break;
                    }
                }
                // If result itself looks like an entry (has 'id' key)
                if (! $entry && isset($result['id'])) {
                    $entry = $result;
                }
            }
        }

        if (! is_array($entry)) {
            return ['state' => 'pending', 'embed_url' => null, 'file_code' => null, 'error' => null];
        }

        $status = strtolower(trim((string) ($entry['status'] ?? 'new')));

        // Error states
        if (in_array($status, ['error', 'failed'], true)) {
            return ['state' => 'error', 'embed_url' => null, 'file_code' => null, 'error' => 'remote download failed'];
        }

        // `extid` is set once the file is saved on Streamtape. It's `false` or
        // empty while still downloading. Once it's a non-empty string, the
        // embed link is valid (the file may still be converting but is playable).
        $extid = $entry['extid'] ?? null;
        if (is_string($extid) && $extid !== '' && $extid !== 'false') {
            return [
                'state' => 'done',
                'embed_url' => "https://streamtape.com/e/{$extid}",
                'file_code' => $extid,
                'error' => null,
            ];
        }

        // If status is "downloaded" or "finished" but extid is not yet
        // populated, treat it as done by checking if a URL is provided.
        if (in_array($status, ['downloaded', 'finished'], true)) {
            if (! empty($entry['url']) && is_string($entry['url'])) {
                // Extract the file code from the URL if possible.
                $code = null;
                if (preg_match('#/(?:v|e)/([a-zA-Z0-9]+)#', $entry['url'], $m)) {
                    $code = $m[1];
                }

                return [
                    'state' => 'done',
                    'embed_url' => $code ? "https://streamtape.com/e/{$code}" : $entry['url'],
                    'file_code' => $code,
                    'error' => null,
                ];
            }
        }

        // Some responses expose the final URL directly even before status changes.
        if (! empty($entry['url']) && is_string($entry['url']) && $entry['url'] !== 'false') {
            $code = null;
            if (preg_match('#/(?:v|e)/([a-zA-Z0-9]+)#', $entry['url'], $m)) {
                $code = $m[1];
            }
            if ($code) {
                return ['state' => 'done', 'embed_url' => "https://streamtape.com/e/{$code}", 'file_code' => $code, 'error' => null];
            }
        }

        return ['state' => 'pending', 'embed_url' => null, 'file_code' => null, 'error' => null];
    }

    public function matchesHost(string $host): bool
    {
        return (bool) preg_match('/(streamtape|strtape|strcloud|tapecontent|streamadblocker|stape)/i', $host);
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
            // Always use the real filename (keeps original name + extension on the player).
            $response = Http::timeout(900)
                ->attach('file1', $handle, basename($localPath))
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
