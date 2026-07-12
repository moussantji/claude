<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Minimal aria2c client for the 100%-free provider.
 *
 * aria2c downloads the magnet/.torrent on OUR server; the resulting video
 * files are then uploaded to the target player host by TorrentService.
 *
 * Two modes:
 *   • RPC daemon (recommended): non-blocking, supports polling progress.
 *       aria2c --enable-rpc --rpc-listen-port=6800 --dir=/tmp/torrents --seed-time=0
 *   • CLI fallback: a blocking `aria2c` subprocess per torrent (works, but ties
 *       up the process for the whole download — only for small/quick torrents).
 */
class Aria2Client
{
    private string $rpcUrl;
    private ?string $rpcSecret;
    private string $binary;
    private string $downloadDir;
    private int $timeout;

    public function __construct()
    {
        $this->rpcUrl = env('ARIA2_RPC_URL', 'http://localhost:6800/jsonrpc');
        $this->rpcSecret = env('ARIA2_RPC_SECRET') ?: null;
        $this->binary = (string) env('ARIA2_BINARY', 'aria2c');
        $this->downloadDir = rtrim(env('TORRENT_DOWNLOAD_DIR', storage_path('app/torrents')), '/');
        $this->timeout = (int) env('TORRENT_DOWNLOAD_TIMEOUT', 3600);

        if (! is_dir($this->downloadDir)) {
            @mkdir($this->downloadDir, 0755, true);
        }
    }

    public function downloadDir(): string
    {
        return $this->downloadDir;
    }

    /**
     * Whether aria2c is reachable (RPC daemon up, or the CLI binary present).
     */
    public function isAvailable(): bool
    {
        return $this->rpcAvailable() || $this->cliAvailable();
    }

    /**
     * Start downloading a magnet / .torrent URL. Returns a GID to poll.
     */
    public function add(string $url): string
    {
        return $this->rpcAvailable()
            ? $this->addViaRpc($url)
            : $this->addViaCli($url);
    }

    /**
     * Poll a download.
     *
     * @return array{status:string, progress:int, files:array, error:string|null}
     *         status: active|waiting|paused|complete|error|removed
     */
    public function poll(string $gid): array
    {
        // CLI mode is blocking: if we are polling a cli_ gid the files already
        // exist on disk (the process returned successfully in addViaCli()).
        if (str_starts_with($gid, 'cli_') || ! $this->rpcAvailable()) {
            return [
                'status' => 'complete',
                'progress' => 100,
                'files' => $this->scanDir(),
                'error' => null,
            ];
        }

        $resp = $this->rpc('aria2.tellStatus', [$gid, [
            'status', 'totalLength', 'completedLength', 'files', 'errorCode',
            'errorMessage', 'followedBy',
        ]]);

        // A magnet's metadata download spawns a child GID holding the files.
        if (! empty($resp['followedBy'][0])) {
            $resp = $this->rpc('aria2.tellStatus', [$resp['followedBy'][0], [
                'status', 'totalLength', 'completedLength', 'files', 'errorCode', 'errorMessage',
            ]]);
        }

        $status = $resp['status'] ?? 'error';
        $total = (int) ($resp['totalLength'] ?? 0);
        $done = (int) ($resp['completedLength'] ?? 0);
        $progress = $total > 0 ? (int) round($done / $total * 100) : 0;

        $error = null;
        if ($status === 'error') {
            $error = ($resp['errorMessage'] ?? 'aria2c error')
                . ' (code ' . ($resp['errorCode'] ?? '?') . ')';
        }

        // Normalize files[] → [{path, length}]
        $files = [];
        foreach ($resp['files'] ?? [] as $f) {
            if (! empty($f['path'])) {
                $files[] = ['path' => $f['path'], 'length' => (int) ($f['length'] ?? 0)];
            }
        }

        return ['status' => $status, 'progress' => $progress, 'files' => $files, 'error' => $error];
    }

    /* ---- RPC ---- */

    private function rpcAvailable(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $resp = Http::timeout(5)->post($this->rpcUrl, [
                'jsonrpc' => '2.0', 'id' => 'ping', 'method' => 'aria2.getVersion',
                'params' => $this->params([]),
            ]);
            $ok = $resp->successful() && isset($resp->json()['result']);
        } catch (Throwable) {
            $ok = false;
        }

        return $ok;
    }

    /**
     * Raw JSON-RPC call. The `result` can be a string (e.g. addUri returns the
     * GID) or an array (e.g. tellStatus), so the return type is mixed.
     */
    private function rpc(string $method, array $params = []): mixed
    {
        $resp = Http::timeout(30)->post($this->rpcUrl, [
            'jsonrpc' => '2.0', 'id' => uniqid('a2_'), 'method' => $method,
            'params' => $this->params($params),
        ]);

        if ($resp->failed()) {
            throw new RuntimeException("aria2c RPC {$method} failed (HTTP {$resp->status()}).");
        }
        $json = $resp->json();
        if (isset($json['error'])) {
            throw new RuntimeException('aria2c RPC error: ' . ($json['error']['message'] ?? 'unknown'));
        }

        return $json['result'] ?? [];
    }

    private function params(array $params): array
    {
        return $this->rpcSecret ? array_merge(["token:{$this->rpcSecret}"], $params) : $params;
    }

    private function addViaRpc(string $url): string
    {
        $options = [
            'dir' => $this->downloadDir,
            'seed-time' => '0',
            'bt-stop-timeout' => '0',
            'follow-torrent' => 'true',
            'file-allocation' => 'none',
        ];
        $result = $this->rpc('aria2.addUri', [[$url], $options]);
        if (is_string($result)) {
            return $result;
        }
        throw new RuntimeException('aria2c RPC did not return a GID.');
    }

    /* ---- CLI fallback ---- */

    private function cliAvailable(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $ok = Process::timeout(15)->run([$this->binary, '--version'])->successful();
        } catch (Throwable) {
            $ok = false;
        }

        return $ok;
    }

    private function addViaCli(string $url): string
    {
        try {
            $result = Process::timeout($this->timeout)->run([
                $this->binary,
                '--dir=' . $this->downloadDir,
                '--seed-time=0',
                '--bt-stop-timeout=0',
                '--follow-torrent=true',
                '--file-allocation=none',
                '--max-connection-per-server=8',
                '--split=8',
                '--check-certificate=false',
                '--allow-overwrite=true',
                $url,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException("aria2c CLI error: {$e->getMessage()}");
        }

        if (! $result->successful()) {
            $err = trim($result->errorOutput() ?: $result->output());
            throw new RuntimeException('aria2c CLI failed: ' . mb_substr($err, 0, 300));
        }

        return 'cli_' . md5($url . microtime());
    }

    /**
     * Scan the download directory for video files (CLI-mode fallback).
     *
     * @return list<array{path:string, length:int}>
     */
    private function scanDir(): array
    {
        $files = [];
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->downloadDir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $info) {
                if ($info->isFile()) {
                    $files[] = ['path' => $info->getPathname(), 'length' => $info->getSize()];
                }
            }
        } catch (Throwable) {
            // empty
        }

        return $files;
    }
}
