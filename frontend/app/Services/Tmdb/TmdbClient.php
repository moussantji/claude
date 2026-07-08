<?php

namespace App\Services\Tmdb;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

/**
 * Minimal TMDB client used to fetch a YouTube trailer key for an anime by
 * title. Optional: when no API key is configured the client is a no-op and
 * the UI falls back to the animated image background.
 *
 * Get a free API key at https://www.themoviedb.org/settings/api
 */
class TmdbClient
{
    private const BASE = 'https://api.themoviedb.org/3';

    public function __construct(
        protected Factory $http,
        protected string $apiKey = '',
        protected string $language = 'fr-FR',
        protected float $timeout = 12.0,
    ) {
    }

    public function enabled(): bool
    {
        return trim($this->apiKey) !== '';
    }

    /**
     * Find a YouTube trailer for the given title.
     *
     * @return array{youtube_key: ?string, name: ?string} Always returns an
     *         array when enabled (youtube_key may be null); null when disabled.
     */
    public function trailerFor(string $title): ?array
    {
        if (! $this->enabled() || trim($title) === '') {
            return null;
        }

        try {
            $search = $this->http->timeout($this->timeout)->acceptJson()->get(self::BASE.'/search/multi', [
                'api_key' => $this->apiKey,
                'language' => $this->language,
                'query' => $title,
                'include_adult' => 'false',
            ]);

            if ($search->failed()) {
                return ['youtube_key' => null, 'name' => null];
            }

            $hit = null;
            foreach ($search->json('results') ?? [] as $result) {
                $mediaType = $result['media_type'] ?? '';
                if ($mediaType === 'tv' || $mediaType === 'movie') {
                    $hit = $result;
                    break;
                }
            }

            if ($hit === null) {
                return ['youtube_key' => null, 'name' => null];
            }

            $video = $this->findVideo($hit['media_type'], $hit['id'], $this->language)
                ?? $this->findVideo($hit['media_type'], $hit['id'], 'en-US');

            return $video ?? ['youtube_key' => null, 'name' => null];
        } catch (ConnectionException) {
            return ['youtube_key' => null, 'name' => null];
        }
    }

    /**
     * @return array{youtube_key: string, name: string}|null
     */
    private function findVideo(string $mediaType, int|string $id, string $language): ?array
    {
        $res = $this->http->timeout($this->timeout)->acceptJson()->get(self::BASE."/{$mediaType}/{$id}/videos", [
            'api_key' => $this->apiKey,
            'language' => $language,
        ]);

        if ($res->failed()) {
            return null;
        }

        $best = null;
        foreach ($res->json('results') ?? [] as $video) {
            if (($video['site'] ?? '') !== 'YouTube' || empty($video['key'])) {
                continue;
            }
            if (! in_array($video['type'] ?? '', ['Trailer', 'Teaser', 'Clip'], true)) {
                continue;
            }
            // Prefer an official trailer; otherwise keep the first candidate.
            if (($video['type'] ?? '') === 'Trailer' && ($video['official'] ?? false)) {
                $best = $video;
                break;
            }
            $best ??= $video;
        }

        if ($best === null) {
            return null;
        }

        return ['youtube_key' => $best['key'], 'name' => $best['name'] ?? 'Trailer'];
    }
}
