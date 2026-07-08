<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal client to upload your OWN media files to your vidmoly account.
 *
 * Follows the standard XFileSharing upload flow:
 *   1. GET  {api_base}/api/upload/server?key=KEY   -> upload URL (+ sess_id)
 *   2. POST the file (multipart) to that URL        -> file_code
 * The embed URL is then {embed_base}/embed-{file_code}.html
 *
 * Endpoints are configurable (config/services.php -> vidmoly); confirm them
 * against your account's API panel if your host differs.
 */
class VidmolyClient
{
    public function __construct(
        protected string $apiKey,
        protected string $apiBase,
        protected string $embedBase,
        protected int $timeout = 900,
    ) {
    }

    public function enabled(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Upload a local file and return its identifiers.
     *
     * @return array{file_code: string, embed_url: string, download_url: string}
     */
    public function upload(string $path, ?string $title = null): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('VIDMOLY_API_KEY is not set.');
        }
        if (! is_file($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        [$uploadUrl, $sessId] = $this->uploadServer();

        $fields = ['key' => $this->apiKey];
        if ($sessId !== null && $sessId !== '') {
            $fields['sess_id'] = $sessId;
        }
        if ($title !== null && $title !== '') {
            $fields['file_title'] = $title;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Unable to open file: {$path}");
        }

        try {
            $response = Http::timeout($this->timeout)
                ->attach('file', $handle, basename($path))
                ->post($uploadUrl, $fields);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Vidmoly is unreachable: '.$e->getMessage(), 0, $e);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($response->failed()) {
            throw new RuntimeException('Vidmoly upload failed (HTTP '.$response->status().').');
        }

        $code = $this->extractFileCode($response->json());
        if ($code === null) {
            throw new RuntimeException('Vidmoly did not return a file code: '.$response->body());
        }

        return [
            'file_code' => $code,
            'embed_url' => $this->embedBase.'/embed-'.$code.'.html',
            'download_url' => $this->embedBase.'/'.$code,
        ];
    }

    /**
     * @return array{0: string, 1: string|null}  [uploadUrl, sessId]
     */
    protected function uploadServer(): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get($this->apiBase.'/api/upload/server', ['key' => $this->apiKey]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Vidmoly is unreachable: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new RuntimeException('Could not get a vidmoly upload server (HTTP '.$response->status().').');
        }

        $uploadUrl = $response->json('result');
        if (! is_string($uploadUrl) || $uploadUrl === '') {
            throw new RuntimeException('Vidmoly did not return an upload URL: '.$response->body());
        }

        $sessId = $response->json('sess_id');

        return [$uploadUrl, is_string($sessId) ? $sessId : null];
    }

    /**
     * Extract the file code from the (list or object) upload response.
     */
    protected function extractFileCode(mixed $data): ?string
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
}
