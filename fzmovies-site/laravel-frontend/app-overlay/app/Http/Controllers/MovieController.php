<?php

namespace App\Http\Controllers;

use App\Services\FzMoviesClient;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    /**
     * Home / search page.
     */
    public function index(Request $request, FzMoviesClient $client)
    {
        $q = trim((string) $request->query('q', ''));
        $searchby = $request->query('searchby', 'Name');
        $category = $request->query('category', 'All');

        $movies = [];
        $error = null;

        if ($q !== '') {
            try {
                $data = $client->search($q, $searchby, $category, 40);
                $movies = $data['movies'] ?? [];
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('movies.index', [
            'q' => $q,
            'searchby' => $searchby,
            'category' => $category,
            'movies' => $movies,
            'error' => $error,
        ]);
    }

    /**
     * Browse curated listings (genre, recently released, IMDB top 250, ...).
     */
    public function browse(Request $request, FzMoviesClient $client)
    {
        $filter = $request->query('filter', 'recently_released');
        $category = $request->query('category', 'Hollywood');
        $genre = $request->query('genre', 'Action');

        $movies = [];
        $error = null;

        try {
            $data = $client->browse($filter, [
                'category' => $category,
                'genre' => $genre,
                'limit' => 40,
            ]);
            $movies = $data['movies'] ?? [];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('movies.browse', [
            'filter' => $filter,
            'category' => $category,
            'genre' => $genre,
            'movies' => $movies,
            'error' => $error,
        ]);
    }

    /**
     * Movie detail page: list downloadable files + trailer + recommendations.
     */
    public function show(Request $request, FzMoviesClient $client)
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
            $data = $client->movieFiles($url, $title, $year);
            $files = $data['files'] ?? [];
            $trailer = $data['trailer'] ?? null;
            $recommended = $data['recommended'] ?? [];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('movies.show', [
            'title' => $title,
            'year' => $year,
            'url' => $url,
            'files' => $files,
            'trailer' => $trailer,
            'recommended' => $recommended,
            'error' => $error,
        ]);
    }

    /**
     * Resolve and display the direct download links for a movie file.
     */
    public function download(Request $request, FzMoviesClient $client)
    {
        $url = (string) $request->query('url', '');
        $title = (string) $request->query('title', 'Movie');
        $size = (string) $request->query('size', 'N/A');

        if ($url === '') {
            return redirect()->route('movies.index');
        }

        $download = null;
        $error = null;

        try {
            $download = $client->downloadLinks($url, $title, $size);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('movies.download', [
            'title' => $title,
            'download' => $download,
            'error' => $error,
        ]);
    }
}
