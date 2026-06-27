<?php

namespace App\Http\Controllers;

use App\Models\Series;

class SeriesController extends Controller
{
    public function index()
    {
        return view('series.index', [
            'featured' => Series::active()->where('is_featured', true)->latest()->first(),
        ]);
    }

    public function show(Series $series)
    {
        abort_unless($series->is_active, 404);

        $series->load('episodes');

        return view('series.show', [
            'series' => $series,
            'episodesBySeason' => $series->episodes->groupBy('season'),
            'related' => Series::active()
                ->where('id', '!=', $series->id)
                ->when($series->genre, fn ($q) => $q->where('genre', $series->genre))
                ->take(6)
                ->get(),
        ]);
    }
}
