<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP client for the FastAPI microservice that wraps the fzmovies-api SDK.
 *
 * The constructor has no required arguments so Laravel's container can
 * auto-resolve it (e.g. via controller method injection).
 */
class FzMoviesClient
{
    protected string $baseUrl;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('movieapi.base_url'), '/');
        $this->timeout = (int) config('movieapi.timeout', 45);
    }

    /**
     * Perform a GET request against the movie API and return decoded JSON.
     *
     * @throws RuntimeException on transport failure or non-2xx responses.
     */
    protected function get(string $path, array $query = []): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get($this->baseUrl . $path, $query);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Could not reach the movie API ({$this->baseUrl}): " . $e->getMessage(),
                previous: $e
            );
        }

        if ($response->failed()) {
            $detail = $response->json('detail') ?? $response->body();
            throw new RuntimeException(
                "Movie API returned {$response->status()}: {$detail}"
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Search movies by name / director / starcast.
     */
    public function search(string $query, string $searchby = 'Name', string $category = 'All', int $limit = 20): array
    {
        return $this->get('/api/search', [
            'q' => $query,
            'searchby' => $searchby,
            'category' => $category,
            'limit' => $limit,
        ]);
    }

    /**
     * Browse curated listings using a named filter.
     */
    public function browse(string $filter, array $options = []): array
    {
        return $this->get('/api/browse', array_merge(['filter' => $filter], $options));
    }

    /**
     * List downloadable files for a movie page URL.
     */
    public function movieFiles(string $url, string $title = 'N/A', int $year = 0): array
    {
        return $this->get('/api/movie-files', [
            'url' => $url,
            'title' => $title,
            'year' => $year,
        ]);
    }

    /**
     * Resolve direct download links for a specific movie file URL.
     */
    public function downloadLinks(string $url, string $title = 'N/A', string $size = 'N/A'): array
    {
        return $this->get('/api/download-links', [
            'url' => $url,
            'title' => $title,
            'size' => $size,
        ]);
    }

    /**
     * Fetch supported enumerations (searchby, categories, genres, filters).
     */
    public function meta(): array
    {
        return $this->get('/api/meta');
    }
}
