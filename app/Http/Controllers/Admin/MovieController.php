<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MovieController extends Controller
{
    public function index(Request $request)
    {
        $movies = Movie::query()
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.movies.index', ['movies' => $movies, 'search' => $request->search]);
    }

    public function create()
    {
        return view('admin.movies.form', [
            'movie' => new Movie(['is_active' => true, 'quality' => 'HD', 'rating' => 0]),
        ]);
    }

    public function store(Request $request)
    {
        Movie::create($this->validated($request));

        return redirect()->route('admin.movies.index')->with('status', __('Movie created.'));
    }

    public function edit(Movie $movie)
    {
        return view('admin.movies.form', ['movie' => $movie]);
    }

    public function update(Request $request, Movie $movie)
    {
        $movie->update($this->validated($request, $movie));

        return redirect()->route('admin.movies.index')->with('status', __('Movie updated.'));
    }

    public function destroy(Movie $movie)
    {
        $movie->delete();

        return redirect()->route('admin.movies.index')->with('status', __('Movie deleted.'));
    }

    private function validated(Request $request, ?Movie $movie = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'poster_url' => ['nullable', 'url', 'max:2048'],
            'backdrop_url' => ['nullable', 'url', 'max:2048'],
            'genre' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'quality' => ['required', 'in:SD,HD,FHD,4K'],
            'stream_url' => ['nullable', 'string', 'max:2048'],
            'trailer_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');
        $data['rating'] = $data['rating'] ?? 0;
        $data['slug'] = $this->uniqueSlug($data['title'], $movie);

        return $data;
    }

    private function uniqueSlug(string $title, ?Movie $movie): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;
        while (Movie::where('slug', $slug)->when($movie, fn ($q) => $q->where('id', '!=', $movie->id))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
