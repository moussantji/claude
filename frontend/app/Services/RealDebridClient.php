<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the Real-Debrid REST API (v1.0).
 *
 * Used to turn a magnet / .torrent into direct HTTP download links:
 *   addMagnet()/addTorrent() → selectFiles() → info() (poll) → unrestrict()
 *
 * Auth: a private API token from https://real-debrid.com/apitoken sent as
 * `Authorization: Bearer <token>`.
 *
 * @see https://api.real-debrid.com/  (official API reference)
 */
class RealDebridClient
{
    private string $base;
    private ?string $token;
    private int $timeout;

    public function __construct()
    {
        $this->base = rtrim(env('REALDEBRID_API_BASE', 'https://api.real-debrid.com/rest/1.0'), '/');
        $this->token = env('REALDEBRID_API_TOKEN') ?: null;
        $this->timeout = (int) env('REALDEBRID_TIMEOUT', 30);
    }

    public function isConfigured(): bool
    {
        return ! empty($this->token);
    }

    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Real-Debrid API token missing. Set REALDEBRID_API_TOKEN in .env');
        }

        return Http::withToken($this->token)
            ->timeout($this->timeout)
            ->acceptJson();
    }

    /**
     * Add a magnet link. Returns the Real-Debrid torrent id.
     */
    public function addMagnet(string $magnet): string
    {
        $resp = $this->client()->asForm()->post("{$this->base}/torrents/addMagnet", [
            'magnet' => $magnet,
        ]);

        $this->guard($resp, 'addMagnet');

        $id = $resp->json('id');
        if (! $id) {
            throw new RuntimeException('Real-Debrid addMagnet returned no id: ' . $resp->body());
        }

        return (string) $id;
    }

    /**
     * Upload a raw .torrent file (binary). Returns the Real-Debrid torrent id.
     */
    public function addTorrentFile(string $torrentBinary): string
    {
        $resp = $this->client()
            ->withBody($torrentBinary, 'application/x-bittorrent')
            ->put("{$this->base}/torrents/addTorrent");

        $this->guard($resp, 'addTorrent');

        $id = $resp->json('id');
        if (! $id) {
            throw new RuntimeException('Real-Debrid addTorrent returned no id: ' . $resp->body());
        }

        return (string) $id;
    }

    /**
     * Fetch a remote .torrent file over HTTP, then add it to Real-Debrid.
     */
    public function addTorrentUrl(string $url): string
    {
        $resp = Http::timeout($this->timeout)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get($url);

        if ($resp->failed()) {
            throw new RuntimeException("Could not fetch .torrent file (HTTP {$resp->status()}): {$url}");
        }

        return $this->addTorrentFile($resp->body());
    }

    /**
     * Select which files to download. Pass "all" or a comma-separated id list.
     */
    public function selectFiles(string $id, string $files = 'all'): void
    {
        $resp = $this->client()->asForm()->post("{$this->base}/torrents/selectFiles/{$id}", [
            'files' => $files,
        ]);

        // 204 No Content on success.
        $this->guard($resp, 'selectFiles');
    }

    /**
     * Torrent info: status, progress, files[] and (once ready) links[].
     *
     * @return array{id:string, filename:string, status:string, progress:int,
     *               bytes:int, files:array, links:array}
     */
    public function info(string $id): array
    {
        $resp = $this->client()->get("{$this->base}/torrents/info/{$id}");
        $this->guard($resp, 'info');

        return $resp->json() ?? [];
    }

    /**
     * Unrestrict a Real-Debrid hoster link into a direct download URL.
     */
    public function unrestrict(string $link): string
    {
        $resp = $this->client()->asForm()->post("{$this->base}/unrestrict/link", [
            'link' => $link,
        ]);

        $this->guard($resp, 'unrestrict');

        $download = $resp->json('download');
        if (! $download) {
            throw new RuntimeException('Real-Debrid unrestrict returned no download URL: ' . $resp->body());
        }

        return (string) $download;
    }

    /**
     * Delete a torrent from the account (housekeeping after resolving).
     */
    public function deleteTorrent(string $id): void
    {
        try {
            $this->client()->delete("{$this->base}/torrents/delete/{$id}");
        } catch (\Throwable) {
            // Non-fatal cleanup.
        }
    }

    /**
     * Throw a helpful exception on API errors (surfacing RD's error message).
     */
    private function guard(\Illuminate\Http\Client\Response $resp, string $context): void
    {
        if ($resp->successful()) {
            return;
        }

        $msg = $resp->json('error') ?: $resp->body();
        $code = $resp->json('error_code');
        $suffix = $code !== null ? " (code {$code})" : '';

        throw new RuntimeException("Real-Debrid {$context} failed [HTTP {$resp->status()}]: {$msg}{$suffix}");
    }
}
