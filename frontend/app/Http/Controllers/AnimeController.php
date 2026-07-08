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
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:200'],
        ]);

        $data = $this->store->remember(
            'search',
            'search:'.mb_strtolower($validated['q']),
            null,
            fn () => $this->client->search($validated['q'])
        );

        return response()->json(['data' => $data]);
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
