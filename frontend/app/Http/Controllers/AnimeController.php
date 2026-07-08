<?php

namespace App\Http\Controllers;

use App\Services\AnimeCacheStore;
use App\Services\AnimeSamaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnimeController extends Controller
{
    public function __construct(
        protected AnimeSamaClient $client,
        protected AnimeCacheStore $store,
    ) {
    }

    /**
     * Search the anime-sama catalogue.
     *
     * When the full catalogue has been ingested (see anime:ingest) and local
     * search is enabled, results are filtered from the stored snapshot with no
     * upstream call. Otherwise the query is proxied to the backend and cached.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:200'],
        ]);
        $query = $validated['q'];

        if ($this->store->enabled() && config('services.anime_sama.local_search', true)) {
            $snapshot = $this->store->get('all-catalogues');
            if (is_array($snapshot)) {
                return response()->json(['data' => $this->filterCatalogues($snapshot, $query)]);
            }
        }

        $data = $this->store->remember(
            'search',
            'search:'.mb_strtolower($query),
            null,
            fn () => $this->client->search($query)
        );

        return response()->json(['data' => $data]);
    }

    /**
     * The entire anime-sama catalogue (metadata). One call fetches ~1k+ entries
     * and persists them so browsing/search can be served from the database.
     */
    public function allCatalogues(): JsonResponse
    {
        $data = $this->store->remember(
            'catalogues',
            'all-catalogues',
            null,
            fn () => $this->client->allCatalogues()
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Filter a stored catalogue snapshot by query (name / alternative names).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function filterCatalogues(array $items, string $query): array
    {
        $q = mb_strtolower(trim($query));
        if ($q === '') {
            return array_slice(array_values($items), 0, 60);
        }

        $matches = [];
        foreach ($items as $item) {
            $name = mb_strtolower((string) ($item['name'] ?? ''));
            $hit = str_contains($name, $q);

            if (! $hit) {
                foreach ((array) ($item['alternative_names'] ?? []) as $alt) {
                    if (str_contains(mb_strtolower((string) $alt), $q)) {
                        $hit = true;
                        break;
                    }
                }
            }

            if ($hit) {
                $matches[] = $item;
            }
        }

        return array_slice($matches, 0, 60);
    }

    /**
     * Latest episode releases from the homepage.
     */
    public function newEpisodes(): JsonResponse
    {
        $data = $this->store->remember(
            'new_episodes',
            'new-episodes',
            null,
            fn () => $this->client->newEpisodes()
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Release planning grouped into scheduled slots.
     */
    public function planning(): JsonResponse
    {
        $data = $this->store->remember(
            'planning',
            'planning',
            null,
            fn () => $this->client->planning()
        );

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

        $data = $this->store->remember(
            'catalogue',
            'catalogue:'.$validated['url'],
            null,
            fn () => $this->client->catalogue($validated['url'])
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Episodes (with player links) for a given season URL.
     *
     * The episode payload — including every mirror/player ("vid") URL — is
     * persisted so playback links are served from the database.
     */
    public function episodes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'prefer' => ['sometimes', 'string', 'max:100'],
        ]);

        $prefer = $validated['prefer'] ?? null;

        $data = $this->store->remember(
            'episodes',
            'episodes:'.$validated['url'].':'.($prefer ?? ''),
            null,
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

        $data = $this->store->remember(
            'trailer',
            'trailer:'.mb_strtolower($validated['title']),
            null,
            fn () => $this->client->trailer($validated['title'])
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Current anime-sama site URL used by the backend. Not cached (live).
     */
    public function site(): JsonResponse
    {
        return response()->json(['data' => $this->client->site()]);
    }

    /**
     * Ask the backend to auto-detect the current anime-sama domain. Not cached.
     */
    public function resolveSite(): JsonResponse
    {
        return response()->json(['data' => $this->client->resolveSite()]);
    }
}
