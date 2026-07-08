<?php

namespace App\Http\Controllers;

use App\Models\Anime;
use App\Models\Episode;
use App\Models\EpisodeLink;
use App\Models\MediaUpload;
use App\Models\Season;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $anime->load(['seasons' => fn ($q) => $q->withCount('episodes')->orderBy('position')]);

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
            'is_mature' => ['sometimes', 'boolean'],
        ]);

        $anime->fill($validated)->save();

        return response()->json(['data' => $anime->fresh()]);
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
     * Episodes (with their links) for a season — read-only inspection.
     */
    public function seasonEpisodes(Season $season): JsonResponse
    {
        $season->load(['episodes' => fn ($q) => $q->with('links')->orderBy('index')]);

        return response()->json(['data' => $season]);
    }
}
