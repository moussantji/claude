<?php

namespace App\Http\Controllers;

use App\Services\AnimeSamaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AnimeController extends Controller
{
    public function __construct(protected AnimeSamaClient $client)
    {
    }

    /**
     * Search the anime-sama catalogue.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:200'],
        ]);

        $data = $this->remember(
            'search:'.mb_strtolower($validated['q']),
            fn () => $this->client->search($validated['q'])
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Latest episode releases from the homepage.
     */
    public function newEpisodes(): JsonResponse
    {
        $data = $this->remember('new-episodes', fn () => $this->client->newEpisodes());

        return response()->json(['data' => $data]);
    }

    /**
     * Release planning grouped into scheduled slots.
     */
    public function planning(): JsonResponse
    {
        $data = $this->remember('planning', fn () => $this->client->planning());

        return response()->json(['data' => $data]);
    }

    /**
     * Full detail of a catalogue (synopsis, seasons, ...).
     */
    public function catalogue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $data = $this->remember(
            'catalogue:'.$validated['url'],
            fn () => $this->client->catalogue($validated['url'])
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Episodes (with player links) for a given season URL.
     */
    public function episodes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'prefer' => ['sometimes', 'string', 'max:100'],
        ]);

        $prefer = $validated['prefer'] ?? null;

        $data = $this->remember(
            'episodes:'.$validated['url'].':'.($prefer ?? ''),
            fn () => $this->client->episodes($validated['url'], $prefer)
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Best YouTube trailer key for a title (TMDB-backed via the backend).
     *
     * anime-sama has no trailer data; the backend resolves it on TMDB and
     * returns {"youtube_key": null} when trailers are disabled/unavailable.
     */
    public function trailer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
        ]);

        $data = $this->remember(
            'trailer:'.mb_strtolower($validated['title']),
            fn () => $this->client->trailer($validated['title'])
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Current anime-sama site URL used by the backend.
     */
    public function site(): JsonResponse
    {
        return response()->json(['data' => $this->client->site()]);
    }

    /**
     * Ask the backend to auto-detect the current anime-sama domain.
     */
    public function resolveSite(): JsonResponse
    {
        return response()->json(['data' => $this->client->resolveSite()]);
    }

    /**
     * Cache a backend call for the configured TTL.
     */
    protected function remember(string $key, callable $callback): mixed
    {
        $ttl = (int) config('services.anime_sama.cache_ttl', 300);

        if ($ttl <= 0) {
            return $callback();
        }

        return Cache::remember('anime_sama:'.md5($key), $ttl, $callback);
    }
}
