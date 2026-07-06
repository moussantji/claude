<?php

namespace App\Http\Controllers;

use App\Services\FzMovies\FzMoviesScraper;
use Illuminate\Http\Request;

/**
 * Server-rendered (Blade) frontend. Uses the PHP scraper directly - no
 * external Python service, no Docker.
 */
class MovieController extends Controller
{
    public function index(Request $request, FzMoviesScraper $scraper)
    {
        $q = trim((string) $request->query('q', ''));
        $searchby = $request->query('searchby', 'Name');
        $category = $request->query('category', 'All');

        $movies = [];
        $error = null;

        if ($q !== '') {
            try {
                $data = $scraper->search($q, $searchby, $category);
                $movies = $data['movies'] ?? [];
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('movies.index', compact('q', 'searchby', 'category', 'movies', 'error'));
    }

    public function browse(Request $request, FzMoviesScraper $scraper)
    {
        $filter = $request->query('filter', 'recently_released');
        $category = $request->query('category', 'Hollywood');
        $genre = $request->query('genre', 'Action');

        $movies = [];
        $error = null;

        try {
            $data = $scraper->browse($filter, [
                'category' => $category,
                'genre' => $genre,
            ]);
            $movies = $data['movies'] ?? [];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('movies.browse', compact('filter', 'category', 'genre', 'movies', 'error'));
    }

    public function show(Request $request, FzMoviesScraper $scraper)
    {
        $url = (string) $request->query('url', '');
        $title = (string) $request->query('title', 'Movie');
        $year = (int) $request->query('year', 0);

        if ($url === '') {
            return redirect()->route('movies.index');
        }

        $files = [];
        $trailer = null;
        $recommended = [];
        $error = null;

        try {
            $data = $scraper->movieFiles($url);
            $files = $data['files'] ?? [];
            $trailer = $data['trailer'] ?? null;
            $recommended = $data['recommended'] ?? [];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('movies.show', compact('title', 'year', 'url', 'files', 'trailer', 'recommended', 'error'));
    }

    public function download(Request $request, FzMoviesScraper $scraper)
    {
        $url = (string) $request->query('url', '');
        $title = (string) $request->query('title', 'Movie');

        if ($url === '') {
            return redirect()->route('movies.index');
        }

        $download = null;
        $error = null;

        try {
            $download = $scraper->downloadLinks($url);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('movies.download', compact('title', 'download', 'error'));
    }
}
