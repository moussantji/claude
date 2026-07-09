<?php

namespace App\Http\Controllers;

use App\Models\Anime;
use App\Models\Season;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public anime catalogue controller — serves everything from MySQL.
 * No external API dependency.
 */
class AnimeController extends Controller
{
    /**
     * Search animes by name/alternative names in the local database.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:200'],
        ]);

        $query = $validated['q'];
        $normalized = $this->normalizeTerm($query);

        $animes = Anime::query()
            ->where(function ($q) use ($query, $normalized) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('slug', 'like', "%{$normalized}%");
            })
            ->orderByDesc('updated_at')
            ->limit(60)
            ->get();

        $data = $animes->map(fn($a) => $this->animeToSummary($a))->values()->all();

        return response()->json(['data' => $data]);
    }

    /**
     * All animes in the catalogue, paginated, ordered by most recent.
     */
    public function allCatalogues(Request $request): JsonResponse
    {
        $perPage = min(60, max(1, (int) $request->query('per_page', 30)));

        $paginated = Anime::query()
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        $data = collect($paginated->items())->map(fn($a) => $this->animeToSummary($a))->values()->all();

        return response()->json([
            'data' => $data,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    /**
     * Homepage: animes ordered by most recently updated (acts as "new episodes").
     * Supports pagination via ?page= and sort via ?sort=newest|oldest.
     */
    public function newEpisodes(Request $request): JsonResponse
    {
        $sort = $request->query('sort', 'newest');
        $perPage = min(60, max(1, (int) $request->query('per_page', 30)));

        $query = Anime::query();

        if ($sort === 'oldest') {
            $query->orderBy('created_at');
        } else {
            $query->orderByDesc('updated_at');
        }

        $paginated = $query->paginate($perPage);

        $data = collect($paginated->items())->map(fn($a) => $this->animeToSummary($a))->values()->all();

        return response()->json([
            'data' => $data,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    /**
     * Full detail of an anime (synopsis, seasons, ...).
     */
    public function catalogue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ]);

        $url = $validated['url'];

        $anime = Anime::query()
            ->with(['seasons' => fn($q) => $q->orderBy('position')])
            ->where(function ($query) use ($url) {
                $query->where('url', $url)
                      ->orWhere('url', rtrim($url, '/') . '/');
                // Match by slug for local:// URLs
                if (str_starts_with($url, 'local://anime/')) {
                    $slug = str_replace('local://anime/', '', rtrim($url, '/'));
                    $query->orWhere('slug', $slug);
                }
                // Also try by ID if it's numeric
                if (is_numeric($url)) {
                    $query->orWhere('id', (int) $url);
                }
            })
            ->first();

        if (! $anime) {
            return response()->json(['message' => 'Anime introuvable.'], 404);
        }

        $data = [
            'url'         => $anime->url,
            'name'        => $anime->name,
            'synopsis'    => $anime->synopsis,
            'image_url'   => $anime->image_url,
            'trailer_url' => $anime->trailer_url,
            'trailer_key' => $this->youtubeKey($anime->trailer_url),
            'genres'      => $anime->genres ?? [],
            'categories'  => $anime->categories ?? [],
            'languages'   => $anime->languages ?? [],
            'is_mature'   => $anime->is_mature,
            'seasons'     => $anime->seasons->map(fn($s) => [
                'name'     => $s->name,
                'url'      => $s->url,
                'position' => $s->position,
            ])->values()->all(),
        ];

        return response()->json(['data' => $data]);
    }

    /**
     * Episodes (with player links) for a given season URL.
     */
    public function episodes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ]);

        $url = $validated['url'];

        $season = Season::query()
            ->with(['episodes' => fn($q) => $q->with('links')->orderBy('index')])
            ->where(function ($query) use ($url) {
                $query->where('url', $url)
                      ->orWhere('url', rtrim($url, '/') . '/');
                // Also try by ID
                if (is_numeric($url)) {
                    $query->orWhere('id', (int) $url);
                }
            })
            ->first();

        if (! $season) {
            return response()->json(['data' => []]);
        }

        $data = $season->episodes->map(function ($ep) {
            // Group links by language
            $languages = [];
            foreach ($ep->links->sortBy('position') as $link) {
                $languages[$link->language][] = $link->url;
            }

            return [
                'index'               => $ep->index,
                'name'                => $ep->name ?? ('Épisode ' . $ep->index),
                'available_languages' => array_keys($languages),
                'languages'           => $languages,
                'best_player'         => $ep->links->first()?->url,
            ];
        })->values()->all();

        return response()->json(['data' => $data]);
    }

    /**
     * Trailer lookup — returns the YouTube key from the local database.
     */
    public function trailer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
        ]);

        $key = $this->findTrailerKey($validated['title']);

        return response()->json(['data' => ['youtube_key' => $key]]);
    }

    /**
     * Find a trailer whose anime name matches the title.
     */
    protected function findTrailerKey(string $title): ?string
    {
        $needle = $this->normalizeTerm($title);
        if ($needle === '') {
            return null;
        }

        $animes = Anime::query()
            ->whereNotNull('trailer_url')
            ->where('trailer_url', '!=', '')
            ->get(['name', 'alternative_names', 'trailer_url']);

        foreach ($animes as $anime) {
            $names = array_merge([$anime->name], (array) ($anime->alternative_names ?? []));
            foreach ($names as $candidate) {
                if ($this->normalizeTerm((string) $candidate) === $needle) {
                    return $this->youtubeKey((string) $anime->trailer_url);
                }
            }
        }

        return null;
    }

    /**
     * Convert an Anime model to a summary array for the frontend.
     */
    protected function animeToSummary(Anime $anime): array
    {
        return [
            'url'               => $anime->url,
            'name'              => $anime->name,
            'image_url'         => $anime->image_url,
            'categories'        => $anime->categories ?? [],
            'languages'         => $anime->languages ?? [],
            'genres'            => $anime->genres ?? [],
            'alternative_names' => $anime->alternative_names ?? [],
            'synopsis'          => $anime->synopsis ? Str::limit($anime->synopsis, 150) : null,
            'descriptive'       => $anime->synopsis ? Str::limit($anime->synopsis, 120) : null,
        ];
    }

    /**
     * Normalise a title/query for lenient matching.
     */
    protected function normalizeTerm(string $value): string
    {
        $value = mb_strtolower(Str::ascii($value));
        return (string) preg_replace('/[^a-z0-9]+/', '', $value);
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
}
