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
