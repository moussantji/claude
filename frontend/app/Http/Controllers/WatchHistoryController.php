<?php

namespace App\Http\Controllers;

use App\Models\WatchHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchHistoryController extends Controller
{
    /**
     * List the authenticated user's watch history (most recent first).
     */
    public function index(Request $request): JsonResponse
    {
        $history = $request->user()
            ->watchHistory()
            ->latest('updated_at')
            ->limit(200)
            ->get();

        return response()->json(['data' => $history]);
    }

    /**
     * Record (or update) progress for an episode.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'serie_name' => ['required', 'string', 'max:255'],
            'season_url' => ['required', 'url', 'max:2048'],
            'season_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'episode_index' => ['required', 'integer', 'min:0'],
            'episode_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'player_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'language' => ['sometimes', 'nullable', 'string', 'max:20'],
            'position_seconds' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $entry = $request->user()->watchHistory()->updateOrCreate(
            [
                'season_url' => $validated['season_url'],
                'episode_index' => $validated['episode_index'],
            ],
            [
                'serie_name' => $validated['serie_name'],
                'season_name' => $validated['season_name'] ?? null,
                'episode_name' => $validated['episode_name'] ?? null,
                'player_url' => $validated['player_url'] ?? null,
                'language' => $validated['language'] ?? null,
                'position_seconds' => $validated['position_seconds'] ?? 0,
            ]
        );

        return response()->json(['data' => $entry], $entry->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Delete a watch history entry belonging to the user.
     */
    public function destroy(Request $request, WatchHistory $watchHistory): JsonResponse
    {
        abort_if($watchHistory->user_id !== $request->user()->id, 403, 'Forbidden.');

        $watchHistory->delete();

        return response()->json(['message' => 'History entry removed.']);
    }
}
