<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * List the authenticated user's favorites.
     */
    public function index(Request $request): JsonResponse
    {
        $favorites = $request->user()
            ->favorites()
            ->latest()
            ->get();

        return response()->json(['data' => $favorites]);
    }

    /**
     * Add a catalogue to the user's favorites (idempotent).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'catalogue_url' => ['required', 'url', 'max:2048'],
            'name' => ['required', 'string', 'max:255'],
            'image_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ]);

        $favorite = $request->user()->favorites()->updateOrCreate(
            ['catalogue_url' => $validated['catalogue_url']],
            [
                'name' => $validated['name'],
                'image_url' => $validated['image_url'] ?? null,
            ]
        );

        return response()->json(['data' => $favorite], $favorite->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Remove a favorite belonging to the user.
     */
    public function destroy(Request $request, Favorite $favorite): JsonResponse
    {
        abort_if($favorite->user_id !== $request->user()->id, 403, 'Forbidden.');

        $favorite->delete();

        return response()->json(['message' => 'Favorite removed.']);
    }
}
