<?php

namespace App\Http\Controllers;

use App\Models\Anime;
use App\Models\AnimeCache;
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
            // Search everything already captured in the DB (the full snapshot
            // PLUS titles you've already searched or opened), so anything stored
            // is findable locally without calling anime-sama again. The snapshot
            // is not exhaustive, so we only fall back to the site's own search
            // when nothing local matches.
            $local = $this->filterCatalogues($this->localCatalogueIndex(), $query);
            if ($local !== []) {
                return response()->json(['data' => $local]);
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
     * Build the local searchable catalogue index from everything already in the
     * DB: the full snapshot, plus any titles previously searched (cached search
     * results) or opened (catalogue details). This makes anything already
     * captured findable locally — even offline — without calling anime-sama.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function localCatalogueIndex(): array
    {
        $index = [];

        $snapshot = $this->store->get('all-catalogues');
        if (is_array($snapshot)) {
            foreach ($snapshot as $item) {
                if (is_array($item) && ! empty($item['url'])) {
                    $index[$item['url']] = $item;
                }
            }
        }

        // Fold in previously cached search results and opened catalogue details.
        // Each search payload is a list of catalogue summaries; each catalogue
        // payload is a single detail — both expose url/name/alternative_names.
        AnimeCache::query()
            ->whereIn('type', ['search', 'catalogue'])
            ->get(['payload', 'type'])
            ->each(function (AnimeCache $row) use (&$index) {
                $payload = $row->payload;
                if (! is_array($payload)) {
                    return;
                }
                $items = array_is_list($payload) ? $payload : [$payload];
                foreach ($items as $item) {
                    if (is_array($item) && ! empty($item['url']) && ! isset($index[$item['url']])) {
                        $index[$item['url']] = $item;
                    }
                }
            });

        return array_values($index);
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

        return response()->json(['data' => $this->applyLocalOverrides($validated['url'], $data)]);
    }

    /**
     * Overlay admin-edited fields (synopsis, image, trailer) from the local
     * `animes` table onto a catalogue payload, so edits show up in the app.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function applyLocalOverrides(string $url, array $data): array
    {
        $anime = Anime::query()
            ->whereIn('url', [$url, rtrim($url, '/').'/'])
            ->first();

        if (! $anime) {
            return $data;
        }

        if (! empty($anime->synopsis)) {
            $data['synopsis'] = $anime->synopsis;
        }
        if (! empty($anime->image_url)) {
            $data['image_url'] = $anime->image_url;
        }
        if (! empty($anime->trailer_url)) {
            $data['trailer_url'] = $anime->trailer_url;
            $data['trailer_key'] = $this->youtubeKey($anime->trailer_url);
        }

        return $data;
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

        // Prefer a trailer manually set in the admin area (matched by title).
        $manual = $this->manualTrailerKey($validated['title']);
        if ($manual !== null) {
            return response()->json(['data' => ['youtube_key' => $manual, 'source' => 'manual']]);
        }

        $data = $this->store->remember(
            'trailer',
            'trailer:'.mb_strtolower($validated['title']),
            null,
            fn () => $this->client->trailer($validated['title'])
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Find a manually-set trailer whose anime name/alt matches the title.
     */
    protected function manualTrailerKey(string $title): ?string
    {
        $needle = $this->normalizeTerm($title);
        if ($needle === '') {
            return null;
        }

        foreach (Anime::query()->whereNotNull('trailer_url')->get(['name', 'alternative_names', 'trailer_url']) as $anime) {
            $names = array_merge([$anime->name], (array) $anime->alternative_names);
            foreach ($names as $candidate) {
                if ($this->normalizeTerm((string) $candidate) === $needle) {
                    return $this->youtubeKey((string) $anime->trailer_url);
                }
            }
        }

        return null;
    }

    /**
     * Extract an 11-char YouTube id from a URL (or accept a bare id).
     */
    protected function youtubeKey(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        if (preg_match('~(?:youtu\.be/|[?&]v=|/embed/|/shorts/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return $m[1];
        }
        if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url)) {
            return $url;
        }

        return null;
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
