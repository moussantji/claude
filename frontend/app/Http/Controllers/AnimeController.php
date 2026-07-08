<?php

namespace App\Http\Controllers;

use App\Services\AnimeSama\Scraper;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AnimeController extends Controller
{
    public function __construct(protected Scraper $scraper)
    {
    }

    /**
     * Return a YouTube search URL for a title's trailer.
     * This replaces TMDB-based trailer lookup with a direct YouTube search.
     */
    public function trailer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
        ]);

        // Instead of fetching from TMDB, return a YouTube search query
        // The frontend will handle opening YouTube search
        $data = [
            'youtube_key' => null, // No direct key available without TMDB
            'search_query' => $validated['title'] . ' anime bande annonce',
        ];

        return response()->json(['data' => $data]);
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
            fn () => $this->scraper->search($validated['q'])
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Latest episode releases from the homepage.
     */
    public function newEpisodes(): JsonResponse
    {
        $data = $this->remember('new-episodes', fn () => $this->scraper->newEpisodes());

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
            fn () => $this->scraper->catalogue($validated['url'])
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

        $prefer = isset($validated['prefer'])
            ? array_values(array_filter(array_map('trim', explode(',', $validated['prefer']))))
            : null;

        $data = $this->remember(
            'episodes:'.$validated['url'].':'.($validated['prefer'] ?? ''),
            fn () => $this->scraper->episodes($validated['url'], $prefer)
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Current anime-sama site URL used by the scraper.
     */
    public function site(): JsonResponse
    {
        return response()->json(['data' => $this->scraper->site()]);
    }

    /**
     * Auto-detect the current anime-sama domain.
     */
    public function resolveSite(): JsonResponse
    {
        return response()->json(['data' => $this->scraper->resolveSite()]);
    }

    /**
     * Cache a scraper call for the configured TTL.
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
