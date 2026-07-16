<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
 * Known error_code values from the RD API:
 *   -1  Internal error
 *    1  Missing parameter
 *    2  Bad parameter value
 *    3  Unknown method
 *    4  Method not allowed
 *    5  Slow down (rate limit)
 *    6  Ressource unreachable
 *    7  Resource not found
 *    8  Bad token
 *    9  Permission denied
 *   10  Two-Factor Auth needed
 *   11  Two-Factor Auth pending
 *   12  Invalid login
 *   13  Invalid password
 *   14  Account locked
 *   15  Account not activated
 *   16  Unsupported hoster
 *   17  Hoster in maintenance
 *   18  Hoster limit reached
 *   19  Hoster temporarily unavailable
 *   20  Hoster not available for free users
 *   21  Too many active downloads
 *   22  IP Address not allowed
 *   23  Traffic exhausted
 *   24  File unavailable
 *   25  Service unavailable
 *   26  Upload too big
 *   27  Upload error
 *   28  File not allowed
 *   29  Torrent too big
 *   30  Torrent file invalid
 *   31  Action already done
 *   32  Image resolution error
 *   33  Torrent already active
 *   34  Too many active torrents (simultaneous download limit)
 *   35  Infringing file (DMCA / copyright blocked)
 *   36  Fair Usage Limit (daily bandwidth/download exceeded)
 *
 * @see https://api.real-debrid.com/  (official API reference)
 */
class RealDebridClient
{
    private string $base;
    private ?string $token;
    private int $timeout;
    private int $maxRetries;

    /** Error codes that should never be retried (permanent failures). */
    private const PERMANENT_ERROR_CODES = [
        8,  // Bad token
        9,  // Permission denied
        14, // Account locked
        22, // IP not allowed
        28, // File not allowed
        29, // Torrent too big
        30, // Torrent file invalid
        35, // Infringing file
    ];

    /** Error codes where a retry after a delay might succeed. */
    private const TRANSIENT_ERROR_CODES = [
        -1, // Internal error
        5,  // Slow down (rate limit)
        6,  // Resource unreachable
        17, // Hoster in maintenance
        19, // Hoster temporarily unavailable
        21, // Too many active downloads
        25, // Service unavailable
        34, // Too many active torrents
    ];

    public function __construct()
    {
        $this->base = rtrim(env('REALDEBRID_API_BASE', 'https://api.real-debrid.com/rest/1.0'), '/');
        $this->token = env('REALDEBRID_API_TOKEN') ?: null;
        $this->timeout = (int) env('REALDEBRID_TIMEOUT', 30);
        $this->maxRetries = (int) env('REALDEBRID_MAX_RETRIES', 2);
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
        $resp = $this->withRetry(function () use ($magnet) {
            return $this->client()->asForm()->post("{$this->base}/torrents/addMagnet", [
                'magnet' => $magnet,
            ]);
        }, 'addMagnet');

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
        $resp = $this->withRetry(function () use ($torrentBinary) {
            return $this->client()
                ->withBody($torrentBinary, 'application/x-bittorrent')
                ->put("{$this->base}/torrents/addTorrent");
        }, 'addTorrent');

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
        $this->withRetry(function () use ($id, $files) {
            return $this->client()->asForm()->post("{$this->base}/torrents/selectFiles/{$id}", [
                'files' => $files,
            ]);
        }, 'selectFiles');
    }

    /**
     * Torrent info: status, progress, files[] and (once ready) links[].
     *
     * @return array{id:string, filename:string, status:string, progress:int,
     *               bytes:int, files:array, links:array}
     */
    public function info(string $id): array
    {
        $resp = $this->withRetry(function () use ($id) {
            return $this->client()->get("{$this->base}/torrents/info/{$id}");
        }, 'info');

        return $resp->json() ?? [];
    }

    /**
     * Unrestrict a Real-Debrid hoster link into a direct download URL.
     *
     * @throws RealDebridPermanentException for infringing/blocked content
     * @throws RuntimeException for other failures
     */
    public function unrestrict(string $link): string
    {
        $resp = $this->withRetry(function () use ($link) {
            return $this->client()->asForm()->post("{$this->base}/unrestrict/link", [
                'link' => $link,
            ]);
        }, 'unrestrict');

        $download = $resp->json('download');
        if (! $download) {
            throw new RuntimeException('Real-Debrid unrestrict returned no download URL: ' . $resp->body());
        }

        return (string) $download;
    }

    /**
     * Get the number of active torrents and the account limit.
     *
     * @return array{nb:int, limit:int}
     */
    public function activeTorrentCount(): array
    {
        $resp = $this->withRetry(function () {
            return $this->client()->get("{$this->base}/torrents/activeCount");
        }, 'activeCount');

        return [
            'nb' => (int) ($resp->json('nb') ?? 0),
            'limit' => (int) ($resp->json('limit') ?? 0),
        ];
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

    /* =====================================================================
     * ERROR CLASSIFICATION
     * =================================================================== */

    /**
     * Whether an error_code represents a permanent failure (no point retrying).
     */
    public static function isPermanentError(?int $errorCode): bool
    {
        return $errorCode !== null && in_array($errorCode, self::PERMANENT_ERROR_CODES, true);
    }

    /**
     * Whether an error_code represents a transient/rate-limit failure.
     */
    public static function isTransientError(?int $errorCode): bool
    {
        return $errorCode !== null && in_array($errorCode, self::TRANSIENT_ERROR_CODES, true);
    }

    /**
     * Whether the error is related to content being blocked (DMCA).
     */
    public static function isInfringingError(?int $errorCode, ?string $message = null): bool
    {
        if ($errorCode === 35) {
            return true;
        }
        if ($message && (str_contains($message, 'infringing') || str_contains($message, '451'))) {
            return true;
        }

        return false;
    }

    /**
     * Whether the error indicates the active torrent limit has been reached.
     */
    public static function isActiveLimitError(?int $errorCode): bool
    {
        return $errorCode === 34;
    }

    /* =====================================================================
     * RETRY + GUARD
     * =================================================================== */

    /**
     * Execute an API call with automatic retry on transient failures (503, 429,
     * and RD error codes known to be temporary).
     *
     * @param  callable  $call  Must return an \Illuminate\Http\Client\Response
     * @return \Illuminate\Http\Client\Response  The successful response
     *
     * @throws RealDebridPermanentException on permanent RD errors
     * @throws RuntimeException on other unrecoverable failures
     */
    private function withRetry(callable $call, string $context): \Illuminate\Http\Client\Response
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                $resp = $call();
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Network timeout / connection refused — retry.
                $attempt++;
                $lastException = new RuntimeException(
                    "Real-Debrid {$context}: connexion impossible — {$e->getMessage()}"
                );
                if ($attempt <= $this->maxRetries) {
                    $this->backoff($attempt);
                    continue;
                }
                throw $lastException;
            }

            if ($resp->successful()) {
                return $resp;
            }

            $errorCode = $resp->json('error_code');
            $errorMsg = $resp->json('error') ?: $resp->body();
            $httpStatus = $resp->status();

            // Permanent RD error — throw immediately, no retry.
            if (self::isPermanentError($errorCode)) {
                throw new RealDebridPermanentException(
                    $context, $errorMsg, $errorCode, $httpStatus
                );
            }

            // Transient RD error codes or HTTP 429/503 — retry after backoff.
            $isTransient = self::isTransientError($errorCode)
                || $httpStatus === 429
                || $httpStatus === 503;

            if ($isTransient && $attempt < $this->maxRetries) {
                $attempt++;
                Log::warning("Real-Debrid {$context}: transient error (HTTP {$httpStatus}, code {$errorCode}) — retry #{$attempt}", [
                    'error' => $errorMsg,
                ]);
                $this->backoff($attempt);
                continue;
            }

            // Not retryable or retries exhausted.
            $suffix = $errorCode !== null ? " (code {$errorCode})" : '';
            throw new RuntimeException(
                "Real-Debrid {$context} failed [HTTP {$httpStatus}]: {$errorMsg}{$suffix}"
            );
        }

        throw $lastException ?? new RuntimeException("Real-Debrid {$context}: retries exhausted.");
    }

    /**
     * Exponential backoff: 2s, 5s, 10s.
     */
    private function backoff(int $attempt): void
    {
        $delays = [2, 5, 10];
        $delay = $delays[min($attempt - 1, count($delays) - 1)];
        sleep($delay);
    }

    /**
     * Throw a helpful exception on API errors (surfacing RD's error message).
     *
     * @deprecated Use withRetry() which handles guard internally.
     */
    private function guard(\Illuminate\Http\Client\Response $resp, string $context): void
    {
        if ($resp->successful()) {
            return;
        }

        $msg = $resp->json('error') ?: $resp->body();
        $code = $resp->json('error_code');
        $suffix = $code !== null ? " (code {$code})" : '';

        if (self::isPermanentError($code)) {
            throw new RealDebridPermanentException($context, $msg, $code, $resp->status());
        }

        throw new RuntimeException("Real-Debrid {$context} failed [HTTP {$resp->status()}]: {$msg}{$suffix}");
    }
}
