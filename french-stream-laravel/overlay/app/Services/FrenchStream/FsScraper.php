<?php

namespace App\Services\FrenchStream;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * High level, cached API over french-stream.one.
 */
class FsScraper
{
    protected FsParser $parser;

    public function __construct(protected FsClient $client)
    {
        $this->parser = new FsParser($client);
    }

    protected function ttl(): int
    {
        return (int) config('fstream.cache_ttl', 600);
    }

    /**
     * Homepage sections (Nouveautés Films, Séries, Box Office, ...).
     */
    public function home(): array
    {
        return Cache::remember('fs.home', $this->ttl(), function () {
            return $this->parser->homeSections($this->client->get('/'));
        });
    }

    /**
     * A listing page (films, séries, genre, year...). Optionally follow an
     * already-resolved next-page URL (for infinite scroll).
     *
     * @return array{items: array, next: ?string}
     */
    public function listing(string $path, ?string $absoluteNext = null): array
    {
        $url = $absoluteNext ?: $path;
        if ($absoluteNext) {
            $this->assertSameHost($absoluteNext);
        }

        $key = 'fs.list.' . md5($url);

        return Cache::remember($key, $this->ttl(), function () use ($url) {
            return $this->parser->listing($this->client->get($url));
        });
    }

    /**
     * Search movies/series by title/actor.
     *
     * @return array{items: array, next: ?string}
     */
    public function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['items' => [], 'next' => null];
        }

        // DataLife Engine search is far more reliable over POST than GET.
        $html = $this->client->post('/index.php?do=search', [
            'do' => 'search',
            'subaction' => 'search',
            'search_start' => 0,
            'full_search' => 0,
            'result_from' => 1,
            'story' => $query,
        ]);

        return $this->parser->listing($html);
    }

    /**
     * Full detail page for a given news id.
     */
    public function detail(string $id): array
    {
        if (! preg_match('/^\d+$/', $id)) {
            throw new InvalidArgumentException('Identifiant invalide.');
        }

        $key = 'fs.detail.' . $id;

        return Cache::remember($key, $this->ttl(), function () use ($id) {
            $html = $this->client->get('/index.php?newsid=' . $id);

            return $this->parser->detail($html);
        });
    }

    /**
     * Fetch an arbitrary next-page URL (infinite scroll). Host-guarded.
     *
     * @return array{items: array, next: ?string}
     */
    public function page(string $absoluteUrl): array
    {
        $this->assertSameHost($absoluteUrl);

        return $this->parser->listing($this->client->get($absoluteUrl));
    }

    /**
     * SSRF guard: only allow URLs on the configured source host / subdomains.
     */
    protected function assertSameHost(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        $base = $this->client->host();

        if (! $host) {
            throw new InvalidArgumentException('URL invalide.');
        }
        $baseRoot = implode('.', array_slice(explode('.', $base), -2));
        if ($host !== $base && ! str_ends_with($host, '.' . $baseRoot) && $host !== $baseRoot) {
            throw new InvalidArgumentException("Hôte non autorisé : {$host}");
        }
    }
}
