<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FzMovies\FzMoviesScraper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON API for the movie data. Protected by Sanctum (see routes/api.php).
 */
class MovieApiController extends Controller
{
    public function __construct(protected FzMoviesScraper $scraper) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1'],
            'searchby' => ['nullable', 'in:Name,Director,Starcast'],
            'category' => ['nullable', 'in:All,Bollywood,Hollywood,DHollywood'],
        ]);

        return $this->wrap(fn () => $this->scraper->search(
            $validated['q'],
            $validated['searchby'] ?? 'Name',
            $validated['category'] ?? 'All',
        ));
    }

    public function browse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filter' => ['required', 'in:imdb_top250,most_downloaded,recently_released,recently_published,genre,release_year'],
            'category' => ['nullable', 'in:Bollywood,Hollywood'],
            'genre' => ['nullable', 'string'],
            'year' => ['nullable', 'integer'],
        ]);

        return $this->wrap(fn () => $this->scraper->browse($validated['filter'], [
            'category' => $validated['category'] ?? 'Hollywood',
            'genre' => $validated['genre'] ?? 'Action',
            'year' => $validated['year'] ?? (int) date('Y'),
        ]));
    }

    public function movieFiles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url'],
        ]);

        return $this->wrap(fn () => $this->scraper->movieFiles($validated['url']));
    }

    public function downloadLinks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url'],
        ]);

        return $this->wrap(fn () => $this->scraper->downloadLinks($validated['url']));
    }

    /**
     * Run a scraper call and normalise success / failure into JSON.
     */
    protected function wrap(callable $fn): JsonResponse
    {
        try {
            return response()->json(['data' => $fn()]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}
