<?php

namespace App\Services;

use App\Exceptions\AnimeSamaException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for the anime-sama FastAPI backend.
 *
 * The backend is a thin REST layer over the `anime-sama_api` Python library
 * (https://github.com/Sky-NiniKo/anime-sama_api). This client wraps every
 * backend endpoint and normalises errors into an {@see AnimeSamaException}
 * so controllers can translate them into clean JSON responses.
 */
class AnimeSamaClient
{
    public function __construct(
        protected string $baseUrl,
        protected string $apiKey = '',
        protected float $timeout = 25.0,
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    /**
     * Search catalogues by query.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query): array
    {
        return $this->get('/api/search', ['q' => $query]);
    }

    /**
     * Latest episode releases from the homepage.
     *
     * @return array<int, array<string, mixed>>
     */
    public function newEpisodes(): array
    {
        return $this->get('/api/new-episodes');
    }

    /**
     * Release planning grouped into scheduled slots.
     *
     * @return array<int, array<string, mixed>>
     */
    public function planning(): array
    {
        return $this->get('/api/planning');
    }

    /**
     * Full catalogue detail (synopsis, seasons, ...).
     *
     * @return array<string, mixed>
     */
    public function catalogue(string $url): array
    {
        return $this->get('/api/catalogue', ['url' => $url]);
    }

    /**
     * Episodes (with player links) for a season URL.
     *
     * @return array<int, array<string, mixed>>
     */
    public function episodes(string $seasonUrl, ?string $prefer = null): array
    {
        $params = ['url' => $seasonUrl];
        if ($prefer !== null && $prefer !== '') {
            $params['prefer'] = $prefer;
        }

        return $this->get('/api/episodes', $params);
    }

    /**
     * Best YouTube trailer key for a title (TMDB-backed, optional).
     *
     * @return array<string, mixed>
     */
    public function trailer(string $title): array
    {
        return $this->get('/api/trailer', ['title' => $title]);
    }

    /**
     * Current anime-sama site URL used by the backend.
     *
     * @return array<string, mixed>
     */
    public function site(): array
    {
        return $this->get('/api/site');
    }

    /**
     * Ask the backend to auto-resolve the current anime-sama domain.
     *
     * @return array<string, mixed>
     */
    public function resolveSite(): array
    {
        return $this->post('/api/site/resolve');
    }

    /**
     * Backend health check.
     *
     * @return array<string, mixed>
     */
    public function health(): array
    {
        return $this->get('/health');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int|string, mixed>
     */
    protected function get(string $path, array $query = []): array
    {
        return $this->request('get', $path, $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int|string, mixed>
     */
    protected function post(string $path, array $query = []): array
    {
        return $this->request('post', $path, $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int|string, mixed>
     */
    protected function request(string $method, string $path, array $query = []): array
    {
        $request = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson();

        if ($this->apiKey !== '') {
            $request = $request->withHeaders(['X-API-Key' => $this->apiKey]);
        }

        try {
            $response = $method === 'post'
                ? $request->post($path, $query)
                : $request->get($path, $query);
        } catch (ConnectionException $e) {
            Log::warning('anime-sama backend unreachable', ['error' => $e->getMessage()]);

            throw new AnimeSamaException(
                'The anime-sama backend is unreachable.',
                503,
                $e
            );
        }

        if ($response->failed()) {
            $detail = $response->json('detail') ?? $response->body();

            throw new AnimeSamaException(
                is_string($detail) ? $detail : 'Upstream backend error.',
                $response->status() >= 500 ? 502 : $response->status(),
            );
        }

        return $response->json() ?? [];
    }
}
