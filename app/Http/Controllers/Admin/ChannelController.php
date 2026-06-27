<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Channel;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index(Request $request)
    {
        $channels = Channel::with('category')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.channels.index', [
            'channels' => $channels,
            'search' => $request->search,
        ]);
    }

    public function create()
    {
        return view('admin.channels.form', [
            'channel' => new Channel(['is_active' => true, 'quality' => 'HD']),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Channel::create($this->validated($request));

        return redirect()->route('admin.channels.index')->with('status', 'Channel created.');
    }

    public function edit(Channel $channel)
    {
        return view('admin.channels.form', [
            'channel' => $channel,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Channel $channel)
    {
        $channel->update($this->validated($request));

        return redirect()->route('admin.channels.index')->with('status', 'Channel updated.');
    }

    public function destroy(Channel $channel)
    {
        $channel->delete();

        return redirect()->route('admin.channels.index')->with('status', 'Channel deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'stream_url' => ['nullable', 'string', 'max:2048'],
            'country' => ['nullable', 'string', 'max:100'],
            'quality' => ['required', 'in:SD,HD,FHD,4K'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
