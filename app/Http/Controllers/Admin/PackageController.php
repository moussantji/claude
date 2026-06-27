<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PackageController extends Controller
{
    public function index()
    {
        return view('admin.packages.index', [
            'packages' => Package::withCount('subscriptions')->orderBy('price')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.packages.form', [
            'package' => new Package(['is_active' => true, 'duration_days' => 30, 'max_connections' => 1]),
        ]);
    }

    public function store(Request $request)
    {
        Package::create($this->validated($request));

        return redirect()->route('admin.packages.index')->with('status', 'Package created.');
    }

    public function edit(Package $package)
    {
        return view('admin.packages.form', ['package' => $package]);
    }

    public function update(Request $request, Package $package)
    {
        $package->update($this->validated($request, $package));

        return redirect()->route('admin.packages.index')->with('status', 'Package updated.');
    }

    public function destroy(Package $package)
    {
        $package->delete();

        return redirect()->route('admin.packages.index')->with('status', 'Package deleted.');
    }

    private function validated(Request $request, ?Package $package = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'channel_count' => ['required', 'integer', 'min:0'],
            'max_connections' => ['required', 'integer', 'min:1', 'max:100'],
            'features' => ['nullable', 'string'],
        ]);

        // Features arrive as a newline-separated textarea; store as array.
        $data['features'] = collect(preg_split('/\r\n|\r|\n/', $request->input('features', '')))
            ->map(fn ($f) => trim($f))
            ->filter()
            ->values()
            ->all();

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');

        $base = Str::slug($data['name']);
        $data['slug'] = $base;
        $i = 1;
        while (Package::where('slug', $data['slug'])->when($package, fn ($q) => $q->where('id', '!=', $package->id))->exists()) {
            $data['slug'] = $base . '-' . $i++;
        }

        return $data;
    }
}
