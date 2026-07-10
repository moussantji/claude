<?php

namespace App\Http\Controllers;

use App\Models\Anime;
use App\Models\Episode;
use App\Models\EpisodeLink;
use App\Models\MediaUpload;
use App\Models\Season;
use App\Models\TransferProgress;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Admin area: manage the normalized library (animes/seasons/episodes/links),
 * including editing the synopsis and setting a trailer.
 */
class AdminController extends Controller
{
    /**
     * Row counts across the app's data.
     */
    public function stats(): JsonResponse
    {
        return response()->json(['data' => [
            'animes' => Anime::count(),
            'seasons' => Season::count(),
            'episodes' => Episode::count(),
            'episode_links' => EpisodeLink::count(),
            'users' => User::count(),
            'uploads' => MediaUpload::count(),
            'requests' => \App\Models\ContentRequest::where('status', 'pending')->count(),
        ]]);
    }

    /**
     * Paginated, searchable list of animes.
     */
    public function animes(Request $request): JsonResponse
    {
        $query = Anime::query()->withCount('seasons');

        if ($term = trim((string) $request->query('q', ''))) {
            $query->where(function ($w) use ($term) {
                $w->where('name', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%");
            });
        }

        return response()->json(
            $query->orderBy('name')->paginate(min(100, (int) $request->query('per_page', 30)))
        );
    }

    /**
     * A single anime with its seasons (and episode counts).
     */
    public function showAnime(Anime $anime): JsonResponse
    {
        $anime->load(['seasons' => fn($q) => $q->withCount('episodes')->orderBy('position')]);

        return response()->json(['data' => $anime]);
    }

    /**
     * Update editable fields: name, synopsis, image, trailer, mature flag.
     */
    public function updateAnime(Request $request, Anime $anime): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'synopsis' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'trailer_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'is_mature' => ['sometimes', 'boolean'],
        ]);

        $anime->fill($validated)->save();

        return response()->json(['data' => $anime->fresh()]);
    }

    public function storeAnime(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'synopsis'    => ['sometimes', 'nullable', 'string', 'max:20000'],
            'image_url'   => ['sometimes', 'nullable', 'string', 'max:2048'],
            'trailer_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'is_mature'   => ['sometimes', 'boolean'],
        ]);

        $slug = Str::slug($validated['name']) . '-' . Str::lower(Str::random(6));

        $anime = Anime::create([
            ...$validated,
            'slug' => $slug,
            'url'  => 'local://anime/' . $slug,
        ]);

        return response()->json(['data' => $anime], 201);
    }

    public function storeSeason(Request $request, Anime $anime): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        $position = $validated['position'] ?? ((int) $anime->seasons()->max('position') + 1);

        $season = $anime->seasons()->create([
            'name'     => $validated['name'],
            'position' => $position,
            'url'      => 'local://anime/' . $anime->slug . '/saison/' . $position . '-' . Str::lower(Str::random(4)),
        ]);

        return response()->json(['data' => $season], 201);
    }

    public function storeEpisode(Request $request, Season $season): JsonResponse
    {
        $validated = $request->validate([
            'name'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'long_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'index'      => ['sometimes', 'integer', 'min:1'],
        ]);

        $index = $validated['index'] ?? ((int) $season->episodes()->max('index') + 1);

        $episode = $season->episodes()->create([
            'index'      => $index,
            'name'       => $validated['name'] ?? null,
            'long_name'  => $validated['long_name'] ?? null,
            'short_name' => $validated['short_name'] ?? null,
        ]);

        return response()->json(['data' => $episode], 201);
    }

    /**
     * Delete an anime (cascades to its seasons, episodes and links).
     */
    public function destroyAnime(Anime $anime): JsonResponse
    {
        $anime->delete();

        return response()->json(['message' => 'Anime deleted.']);
    }

    /**
     * Episodes (with their links) for a season.
     */
    public function seasonEpisodes(Season $season): JsonResponse
    {
        $season->load(['episodes' => fn($q) => $q->with('links')->orderBy('index')]);

        return response()->json(['data' => $season]);
    }

    /* ---- Seasons ---- */

    public function updateSeason(Request $request, Season $season): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        $season->fill($validated)->save();

        return response()->json(['data' => $season->fresh()]);
    }

    public function destroySeason(Season $season): JsonResponse
    {
        $season->delete();

        return response()->json(['message' => 'Season deleted.']);
    }

    /* ---- Episodes ---- */

    public function updateEpisode(Request $request, Episode $episode): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'index' => ['sometimes', 'integer', 'min:1'],
        ]);

        $episode->fill($validated)->save();

        return response()->json(['data' => $episode->fresh()]);
    }

    public function destroyEpisode(Episode $episode): JsonResponse
    {
        $episode->delete();

        return response()->json(['message' => 'Episode deleted.']);
    }

    /* ---- Episode video links ---- */

    public function episodeLinks(Episode $episode): JsonResponse
    {
        return response()->json(['data' => $episode->links()->orderBy('position')->get()]);
    }

    public function storeLink(Request $request, Episode $episode): JsonResponse
    {
        $validated = $request->validate([
            'language' => ['required', 'string', 'max:16'],
            'url' => ['required', 'string', 'max:2048'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        $link = $episode->links()->create([
            'language' => $validated['language'],
            'url' => $validated['url'],
            'position' => $validated['position'] ?? ((int) $episode->links()->max('position') + 1),
            'host' => $this->host($validated['url']),
        ]);

        return response()->json(['data' => $link], 201);
    }

    public function updateLink(Request $request, EpisodeLink $link): JsonResponse
    {
        $validated = $request->validate([
            'language' => ['sometimes', 'string', 'max:16'],
            'url' => ['sometimes', 'string', 'max:2048'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (isset($validated['url'])) {
            $validated['host'] = $this->host($validated['url']);
        }

        $link->fill($validated)->save();

        return response()->json(['data' => $link->fresh()]);
    }

    public function destroyLink(EpisodeLink $link): JsonResponse
    {
        $link->delete();

        return response()->json(['message' => 'Link deleted.']);
    }

    private function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? preg_replace('/^www\./', '', $host) : null;
    }

    /* ---- Planning ---- */

    public function planningIndex(): JsonResponse
    {
        $entries = \App\Models\Planning::query()
            ->with('anime:id,name,image_url')
            ->orderBy('day_of_week')
            ->orderBy('time')
            ->get();

        return response()->json(['data' => $entries]);
    }

    public function storePlanning(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'anime_id'    => ['required', 'exists:animes,id'],
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'time'        => ['sometimes', 'nullable', 'string', 'max:5'],
            'note'        => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $entry = \App\Models\Planning::create($validated);

        return response()->json(['data' => $entry->load('anime:id,name,image_url')], 201);
    }

    public function updatePlanning(Request $request, \App\Models\Planning $planning): JsonResponse
    {
        $validated = $request->validate([
            'anime_id'    => ['sometimes', 'exists:animes,id'],
            'day_of_week' => ['sometimes', 'integer', 'min:0', 'max:6'],
            'time'        => ['sometimes', 'nullable', 'string', 'max:5'],
            'note'        => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $planning->fill($validated)->save();

        return response()->json(['data' => $planning->fresh()->load('anime:id,name,image_url')]);
    }

    public function destroyPlanning(\App\Models\Planning $planning): JsonResponse
    {
        $planning->delete();

        return response()->json(['message' => 'Planning entry deleted.']);
    }

    /* ---- Content Requests ---- */

    public function contentRequests(): JsonResponse
    {
        $requests = \App\Models\ContentRequest::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json(['data' => $requests]);
    }

    public function updateContentRequest(Request $request, \App\Models\ContentRequest $contentRequest): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,approved,rejected'],
        ]);

        $contentRequest->status = $validated['status'];
        $contentRequest->save();

        return response()->json(['data' => $contentRequest->fresh()->load('user:id,name,email')]);
    }

    public function destroyContentRequest(\App\Models\ContentRequest $contentRequest): JsonResponse
    {
        $contentRequest->delete();

        return response()->json(['message' => 'Request deleted.']);
    }

    /* ---- Episode Transfers (episodes:auto / :quota) ---- */

    /**
     * Overall transfer progress + today's throughput + per-anime error counts.
     */
    public function transferStats(): JsonResponse
    {
        $today = Carbon::today()->toDateString();

        $total = TransferProgress::count();
        $done = TransferProgress::where('status', 'done')->count();
        $pending = TransferProgress::where('status', 'pending')->count();
        $failed = TransferProgress::where('status', 'failed')->count();
        $doneToday = TransferProgress::where('status', 'done')
            ->whereDate('transferred_at', $today)->count();

        // Animes that currently have at least one failed transfer
        $animesWithErrors = TransferProgress::query()
            ->selectRaw('anime_id, COUNT(*) as errors')
            ->where('status', 'failed')
            ->groupBy('anime_id')
            ->orderByDesc('errors')
            ->limit(100)
            ->with('anime:id,name')
            ->get()
            ->map(fn ($r) => [
                'anime_id' => $r->anime_id,
                'anime' => $r->anime?->name ?? ('#'.$r->anime_id),
                'errors' => (int) $r->errors,
            ]);

        return response()->json(['data' => [
            'total' => $total,
            'done' => $done,
            'pending' => $pending,
            'failed' => $failed,
            'done_today' => $doneToday,
            'percent' => $total > 0 ? round($done / $total * 100, 1) : 0,
            'animes_with_errors' => $animesWithErrors,
        ]]);
    }

    /**
     * Detailed list of failed transfers (with anime / episode / host / error).
     */
    public function transferErrors(Request $request): JsonResponse
    {
        $query = TransferProgress::query()
            ->where('status', 'failed')
            ->with(['episodeLink.episode.season', 'anime:id,name']);

        if ($animeId = $request->query('anime_id')) {
            $query->where('anime_id', (int) $animeId);
        }

        $items = $query->orderByDesc('last_attempt_at')
            ->limit(300)
            ->get()
            ->map(function (TransferProgress $p) {
                $ep = $p->episodeLink?->episode;
                $season = $ep?->season;

                return [
                    'id' => $p->id,
                    'anime' => $p->anime?->name ?? ('#'.$p->anime_id),
                    'anime_id' => $p->anime_id,
                    'season' => ($season?->position ?? 0) + 1,
                    'episode' => $ep?->index,
                    'language' => $p->episodeLink?->language,
                    'host' => $p->host,
                    'attempts' => $p->attempts,
                    'error' => $p->error,
                    'last_attempt_at' => $p->last_attempt_at,
                ];
            });

        return response()->json(['data' => $items]);
    }

    /**
     * Re-arm failed transfers so they get retried (all, or one anime).
     */
    public function retryTransfers(Request $request): JsonResponse
    {
        $q = TransferProgress::where('status', 'failed');
        if ($animeId = $request->input('anime_id')) {
            $q->where('anime_id', (int) $animeId);
        }

        $count = $q->update(['status' => 'pending', 'attempts' => 0, 'error' => null]);

        return response()->json(['message' => "{$count} transfert(s) réarmé(s).", 'count' => $count]);
    }

    /**
     * Re-do finished transfers: put completed ones back in the queue (at the end).
     * Optionally scope to a single anime.
     */
    public function redoTransfers(Request $request): JsonResponse
    {
        $q = TransferProgress::where('status', 'done');
        if ($animeId = $request->input('anime_id')) {
            $q->where('anime_id', (int) $animeId);
        }

        $count = $q->update([
            'status' => 'pending',
            'attempts' => 0,
            'transferred_at' => null,
            'error' => null,
        ]);

        return response()->json(['message' => "{$count} transfert(s) terminé(s) remis en file.", 'count' => $count]);
    }
}
