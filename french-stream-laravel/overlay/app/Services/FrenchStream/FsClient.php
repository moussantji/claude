<?php

namespace App\Services\FrenchStream;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use RuntimeException;

/**
 * Low level HTTP client for french-stream.one (DataLife Engine + Cloudflare).
 *
 * - Sends realistic browser headers (Cloudflare is picky about UA/Accept).
 * - Persists the DLE PHPSESSID cookie across requests via the Laravel session.
 * - Exposes get() (pages) and searchPost()/ajax() helpers.
 *
 * No required constructor args => auto-resolvable by the container.
 */
class FsClient
{
    protected string $baseUrl;

    protected string $referer;

    protected int $timeout;

    protected int $connectTimeout;

    protected string $cookieDomain;

    protected const SESSION_KEY = 'fs_phpsessid';

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('fstream.base_url', 'https://french-stream.one'), '/');
        $this->referer = (string) config('fstream.referer', $this->baseUrl . '/');
        $this->timeout = (int) config('fstream.timeout', 20);
        $this->connectTimeout = (int) config('fstream.connect_timeout', 10);
        $this->cookieDomain = parse_url($this->baseUrl, PHP_URL_HOST) ?: 'french-stream.one';
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function host(): string
    {
        return $this->cookieDomain;
    }

    /**
     * Make a relative path absolute against the base URL.
     */
    public function absolute(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return $this->baseUrl . '/' . ltrim($url, '/');
    }

    /**
     * Browser-like headers. Cloudflare tends to reject bare/library UAs.
     */
    protected function headers(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
            'Referer' => $this->referer,
            'Upgrade-Insecure-Requests' => '1',
        ];
    }

    protected function request(bool $ajax = false)
    {
        $headers = $this->headers();
        if ($ajax) {
            $headers['X-Requested-With'] = 'XMLHttpRequest';
        }

        $req = Http::withHeaders($headers)
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout)
            ->withOptions(['allow_redirects' => true]);

        if ($id = Session::get(self::SESSION_KEY)) {
            $req = $req->withCookies(['PHPSESSID' => $id], $this->cookieDomain);
        }

        return $req;
    }

    protected function rememberSession($response): void
    {
        $cookie = $response->cookies()?->getCookieByName('PHPSESSID');
        if ($cookie) {
            Session::put(self::SESSION_KEY, $cookie->getValue());
        }
    }

    /**
     * GET a page and return the HTML body.
     */
    public function get(string $url, bool $ajax = false): string
    {
        $url = $this->absolute($url);
        $resp = $this->request($ajax)->get($url);

        if ($resp->status() === 403 || $resp->status() === 503) {
            throw new RuntimeException(
                'Cloudflare a bloqué la requête (HTTP ' . $resp->status() . '). '
                . 'Le site protège ses pages ; il faut passer par un résolveur Cloudflare '
                . '(ex: FlareSolverr) ou une IP autorisée.'
            );
        }
        if ($resp->failed()) {
            throw new RuntimeException("Requête échouée (HTTP {$resp->status()}) : {$url}");
        }

        $this->rememberSession($resp);

        return $resp->body();
    }

    /**
     * DLE AJAX call (used by home tabs), e.g. custom category listings.
     * Kept for completeness; the UI relies mostly on server-rendered pages.
     */
    public function ajaxCustom(string $castom): string
    {
        $resp = $this->request(true)->asForm()->post(
            $this->baseUrl . '/engine/ajax/controller.php?mod=custom_ajax',
            ['custom' => $castom]
        );

        if ($resp->failed()) {
            throw new RuntimeException("AJAX échoué (HTTP {$resp->status()}).");
        }

        $this->rememberSession($resp);

        return $resp->body();
    }
}
