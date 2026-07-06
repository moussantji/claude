<?php

namespace App\Services\FzMovies;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use RuntimeException;

/**
 * Low level HTTP client for fzmovies.
 *
 * Responsibilities:
 *  - Hold the site base URL / headers.
 *  - Initialise and persist the PHPSESSID cookie (mirrors the reference SDK,
 *    which reuses a single requests.Session across the whole download flow).
 *  - Perform GET / search-POST requests and surface "session expired" errors.
 *
 * The constructor has no required arguments so it is auto-resolvable by the
 * Laravel container.
 */
class FzMoviesClient
{
    protected string $baseUrl;

    protected string $referer;

    protected int $timeout;

    protected int $connectTimeout;

    protected string $cookieDomain;

    /** Session key used to persist PHPSESSID across web requests. */
    protected const SESSION_KEY = 'fz_phpsessid';

    /** Pattern signalling the download keys are no longer valid. */
    protected const EXPIRED_PATTERN = '/Your download keys have expired/i';

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('fzmovies.base_url', 'https://fzmovies.live'), '/');
        $this->referer = (string) config('fzmovies.referer', 'https://fzmovies.net/');
        $this->timeout = (int) config('fzmovies.timeout', 20);
        $this->connectTimeout = (int) config('fzmovies.connect_timeout', 10);
        $this->cookieDomain = parse_url($this->baseUrl, PHP_URL_HOST) ?: 'fzmovies.live';
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Turn a relative path into an absolute URL against the base URL.
     */
    public function absolute(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return $this->baseUrl . '/' . ltrim($url, '/');
    }

    protected function headers(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:129.0) Gecko/20100101 Firefox/129.0',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/png,image/svg+xml,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Referer' => $this->referer,
        ];
    }

    /**
     * A pending request pre-configured with headers, timeouts and the
     * current PHPSESSID cookie.
     */
    protected function request(?string $phpSessId = null)
    {
        $req = Http::withHeaders($this->headers())
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout);

        if ($phpSessId) {
            $req = $req->withCookies(['PHPSESSID' => $phpSessId], $this->cookieDomain);
        }

        return $req;
    }

    /**
     * Ensure a PHPSESSID exists, loading the index page once if needed.
     */
    protected function sessionId(): ?string
    {
        $id = Session::get(self::SESSION_KEY);
        if ($id) {
            return $id;
        }

        $resp = $this->request()->get($this->baseUrl . '/');
        if ($resp->failed()) {
            throw new RuntimeException(
                "Impossible de charger la page d'accueil de {$this->baseUrl} (HTTP {$resp->status()})."
            );
        }

        $cookie = $resp->cookies()->getCookieByName('PHPSESSID');
        $id = $cookie ? $cookie->getValue() : null;

        if ($id) {
            Session::put(self::SESSION_KEY, $id);
        }

        return $id;
    }

    /**
     * Perform a GET request and return the HTML body.
     * Retries once with a fresh session if download keys expired.
     */
    public function get(string $url): string
    {
        $url = $this->absolute($url);

        $html = $this->doGet($url);

        if (preg_match(self::EXPIRED_PATTERN, $html)) {
            // Session/keys expired: drop cached session and retry once.
            Session::forget(self::SESSION_KEY);
            $html = $this->doGet($url);
        }

        return $html;
    }

    protected function doGet(string $url): string
    {
        $resp = $this->request($this->sessionId())->get($url);
        if ($resp->failed()) {
            throw new RuntimeException("Requête échouée ({$resp->status()}) : {$url}");
        }

        return $resp->body();
    }

    /**
     * Perform the search POST against /csearch.php and return the HTML body.
     *
     * @param  string  $query
     * @param  string  $searchby  Name|Director|Starcast
     * @param  string  $category  All|Bollywood|Hollywood|DHollywood
     */
    public function search(string $query, string $searchby = 'Name', string $category = 'All'): string
    {
        $query = trim($query);
        if ($query === '') {
            throw new RuntimeException('La requête de recherche ne peut pas être vide.');
        }

        $resp = $this->request($this->sessionId())->asForm()->post(
            $this->baseUrl . '/csearch.php',
            [
                'searchname' => $query,
                'Search' => 'Search',
                'searchby' => $searchby,
                'category' => $category,
                'vsearch' => '',
            ]
        );

        if ($resp->failed()) {
            throw new RuntimeException("Recherche échouée (HTTP {$resp->status()}).");
        }

        return $resp->body();
    }
}
