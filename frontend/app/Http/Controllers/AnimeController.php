<?php

namespace App\Http\Controllers;

use App\Services\AnimeCacheStore;
use App\Services\AnimeSamaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
                $local = $this->filterCatalogues($snapshot, $query);
                // The catalogue snapshot is not exhaustive and lacks some titles,
                // so only trust it when it actually matched something; otherwise
                // fall back to the site's own (alt-name aware) search below.
                if ($local !== []) {
                    return response()->json(['data' => $local]);
                }
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
        $q = $this->normalizeTerm($query);
        if ($q === '') {
            return array_slice(array_values($items), 0, 60);
        }

        $matches = [];
        foreach ($items as $item) {
            if (str_contains($this->normalizeTerm((string) ($item['name'] ?? '')), $q)) {
                $matches[] = $item;

                continue;
            }

            foreach ((array) ($item['alternative_names'] ?? []) as $alt) {
                if (str_contains($this->normalizeTerm((string) $alt), $q)) {
                    $matches[] = $item;
                    break;
                }
            }
        }

        return array_slice($matches, 0, 60);
    }

    /**
     * Normalise a title/query for lenient matching: lowercase, strip accents
     * and any non-alphanumeric characters. So "Kono Subarashii..." and
     * "konosuba" both reduce to a comparable form.
     */
    protected function normalizeTerm(string $value): string
    {
        $value = mb_strtolower(Str::ascii($value));

        return (string) preg_replace('/[^a-z0-9]+/', '', $value);
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
