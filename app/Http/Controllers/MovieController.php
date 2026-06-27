<?php

namespace App\Http\Controllers;

use App\Models\Movie;

class MovieController extends Controller
{
    public function index()
    {
        return view('movies.index', [
            'featured' => Movie::active()->where('is_featured', true)->latest()->first(),
        ]);
    }

    public function show(Movie $movie)
    {
        abort_unless($movie->is_active, 404);

        return view('movies.show', [
            'movie' => $movie,
            'related' => Movie::active()
                ->where('id', '!=', $movie->id)
                ->when($movie->genre, fn ($q) => $q->where('genre', $movie->genre))
                ->take(6)
                ->get(),
        ]);
    }
}
