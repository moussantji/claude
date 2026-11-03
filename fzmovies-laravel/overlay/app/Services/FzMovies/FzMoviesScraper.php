<?php

namespace App\Services\FzMovies;

use InvalidArgumentException;

/**
 * High level, framework-agnostic API over the fzmovies website.
 *
 * Mirrors the reference SDK's Search / Navigate / DownloadLinks classes but
 * returns plain arrays (ready for Blade or JSON).
 */
class FzMoviesScraper
{
    protected Parser $parser;

    public function __construct(protected FzMoviesClient $client)
    {
        $this->parser = new Parser($client);
    }

    /**
     * Search movies by name / director / starcast.
     */
    public function search(string $query, string $searchby = 'Name', string $category = 'All'): array
    {
        $html = $this->client->search($query, $searchby, $category);

        return $this->parser->searchResults($html);
    }

    /**
     * Browse curated listings using a named filter.
     *
     * @param  array{category?: string, genre?: string, year?: int}  $options
     */
    public function browse(string $filter, array $options = []): array
    {
        $url = $this->filterUrl($filter, $options);
        $html = $this->client->get($url);

        return $this->parser->searchResults($html);
    }

    /**
     * List downloadable files for a movie page URL.
     */
    public function movieFiles(string $movieUrl): array
    {
        $html = $this->client->get($movieUrl);

        return $this->parser->movieFiles($html);
    }

    /**
     * Resolve the direct download links for a specific movie file URL.
     *
     * Two-step flow (same session):
     *   file url (/download1.php?downloadoptionskey=) -> to-download page
     *   -> download links page (/download.php?downloadkey=)
     */
    public function downloadLinks(string $fileUrl): array
    {
        $toDownloadHtml = $this->client->get($fileUrl);
        $downloadUrl = $this->parser->toDownloadLink($toDownloadHtml);

        $linksHtml = $this->client->get($downloadUrl);

        return $this->parser->downloadLinks($linksHtml);
    }

    /**
     * Fetch an arbitrary listing/search page URL (used for pagination /
     * infinite scroll). The URL must belong to the source host.
     */
    public function page(string $url): array
    {
        $this->assertSameHost($url);

        return $this->parser->searchResults($this->client->get($url));
    }

    /**
     * Resolve a movie file all the way to a direct, streamable URL.
     *
     * file url -> download-links page -> pick a mirror -> dlink page
     * -> final direct file URL.
     *
     * @return array{url: string, filename: ?string, size: ?string}
     */
    public function resolvePlayable(string $fileUrl, int $mirror = 0): array
    {
        $download = $this->downloadLinks($fileUrl);
        $links = $download['links'] ?? [];

        if (empty($links)) {
            throw new \RuntimeException("Aucun lien exploitable pour ce fichier.");
        }

        $mirror = max(0, min($mirror, count($links) - 1));
        $dlinkUrl = $links[$mirror]['url'];

        $finalHtml = $this->client->get($dlinkUrl);
        $directUrl = $this->parser->finalDownloadLink($finalHtml);

        return [
            'url' => $directUrl,
            'filename' => $download['filename'] ?? null,
            'size' => $download['size'] ?? null,
        ];
    }

    /**
     * Guard against SSRF: only allow URLs on the configured source host
     * (or one of its subdomains).
     */
    protected function assertSameHost(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        $base = parse_url($this->client->baseUrl(), PHP_URL_HOST);

        if (! $host || ! $base) {
            throw new InvalidArgumentException('URL invalide.');
        }

        // Allow exact host or any subdomain of the base registrable domain.
        $baseRoot = implode('.', array_slice(explode('.', $base), -2));
        if ($host !== $base && ! str_ends_with($host, '.' . $baseRoot) && $host !== $baseRoot) {
            throw new InvalidArgumentException("Hôte non autorisé : {$host}");
        }
    }

    /**
     * Build the listing URL for a browse filter.
     */
    protected function filterUrl(string $filter, array $options): string
    {
        $category = $options['category'] ?? 'Hollywood';
        $genre = $options['genre'] ?? 'Action';
        $year = $options['year'] ?? (int) date('Y');

        $ids = (array) config('fzmovies.category_ids', ['Bollywood' => 1, 'Hollywood' => 2]);
        $catId = $ids[$category] ?? 2;

        return match ($filter) {
            'imdb_top250' => '/imdb250.php',
            'most_downloaded' => "/movieslist.php?catID={$catId}&by=downloads",
            'recently_released' => "/movieslist.php?catID={$catId}&by=date",
            'recently_published' => "/movieslist.php?catID={$catId}&by=latest",
            'genre' => '/genre.php?catID=' . $catId . '&genre=' . rawurlencode($genre),
            'release_year' => "/year.php?year={$year}&catID={$catId}",
            default => throw new InvalidArgumentException("Filtre inconnu : {$filter}"),
        };
    }
}
