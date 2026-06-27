<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Series;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeriesController extends Controller
{
    public function index(Request $request)
    {
        $series = Series::query()
            ->withCount('episodes')
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.series.index', ['series' => $series, 'search' => $request->search]);
    }

    public function create()
    {
        return view('admin.series.form', [
            'series' => new Series(['is_active' => true, 'seasons' => 1, 'rating' => 0]),
        ]);
    }

    public function store(Request $request)
    {
        $series = Series::create($this->validated($request));

        return redirect()->route('admin.series.edit', $series)->with('status', __('Series created. You can now add episodes.'));
    }

    public function edit(Series $series)
    {
        $series->load('episodes');

        return view('admin.series.form', ['series' => $series]);
    }

    public function update(Request $request, Series $series)
    {
        $series->update($this->validated($request, $series));

        return redirect()->route('admin.series.index')->with('status', __('Series updated.'));
    }

    public function destroy(Series $series)
    {
        $series->delete();

        return redirect()->route('admin.series.index')->with('status', __('Series deleted.'));
    }

    private function validated(Request $request, ?Series $series = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'poster_url' => ['nullable', 'url', 'max:2048'],
            'backdrop_url' => ['nullable', 'url', 'max:2048'],
            'genre' => ['nullable', 'string', 'max:100'],
            'start_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'end_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'seasons' => ['required', 'integer', 'min:1', 'max:100'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
        ]);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');
        $data['rating'] = $data['rating'] ?? 0;
        $data['slug'] = $this->uniqueSlug($data['title'], $series);

        return $data;
    }

    private function uniqueSlug(string $title, ?Series $series): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;
        while (Series::where('slug', $slug)->when($series, fn ($q) => $q->where('id', '!=', $series->id))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
