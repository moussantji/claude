<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Series;
use Illuminate\Http\Request;

class EpisodeController extends Controller
{
    public function store(Request $request, Series $series)
    {
        $data = $request->validate([
            'season' => ['required', 'integer', 'min:1', 'max:100'],
            'episode_number' => ['required', 'integer', 'min:1', 'max:1000'],
            'title' => ['required', 'string', 'max:255'],
            'stream_url' => ['nullable', 'string', 'max:2048'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $series->episodes()->create($data);

        return back()->with('status', __('Episode added.'));
    }

    public function destroy(Series $series, Episode $episode)
    {
        abort_unless($episode->series_id === $series->id, 404);

        $episode->delete();

        return back()->with('status', __('Episode removed.'));
    }
}
