@extends('layouts.admin')

@section('title', $movie->exists ? __('Edit Movie') : __('New Movie'))

@section('content')
@php($isEdit = $movie->exists)
<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.movies.index') }}" class="text-slate-400 hover:text-white">←</a>
    <h1 class="text-2xl font-bold text-white">{{ $isEdit ? __('Edit Movie') : __('New Movie') }}</h1>
</div>

<form method="POST" action="{{ $isEdit ? route('admin.movies.update', $movie) : route('admin.movies.store') }}"
      class="max-w-2xl space-y-5 rounded-2xl border border-white/10 bg-slate-900/40 p-6">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Title') }}</label>
        <input name="title" value="{{ old('title', $movie->title) }}" required
            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Description') }}</label>
        <textarea name="description" rows="3"
            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">{{ old('description', $movie->description) }}</textarea>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Genre') }}</label>
            <input name="genre" value="{{ old('genre', $movie->genre) }}" placeholder="Action"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Quality') }}</label>
            <select name="quality" class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
                @foreach (['SD', 'HD', 'FHD', '4K'] as $q)
                    <option value="{{ $q }}" {{ old('quality', $movie->quality) === $q ? 'selected' : '' }}>{{ $q }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Year') }}</label>
            <input name="year" type="number" value="{{ old('year', $movie->year) }}"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Duration (min)') }}</label>
            <input name="duration_minutes" type="number" value="{{ old('duration_minutes', $movie->duration_minutes) }}"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Rating (0-10)') }}</label>
            <input name="rating" type="number" step="0.1" min="0" max="10" value="{{ old('rating', $movie->rating) }}"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Poster URL') }}</label>
        <input name="poster_url" type="url" value="{{ old('poster_url', $movie->poster_url) }}" placeholder="https://…"
            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Backdrop URL') }}</label>
        <input name="backdrop_url" type="url" value="{{ old('backdrop_url', $movie->backdrop_url) }}" placeholder="https://…"
            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Stream URL') }}</label>
        <input name="stream_url" value="{{ old('stream_url', $movie->stream_url) }}" placeholder="https://…/movie.mp4"
            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white font-mono text-sm focus:border-brand-500 outline-none">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Trailer URL') }}</label>
        <input name="trailer_url" value="{{ old('trailer_url', $movie->trailer_url) }}" placeholder="https://…/trailer.mp4"
            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white font-mono text-sm focus:border-brand-500 outline-none">
    </div>

    <div class="flex gap-6">
        <label class="flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $movie->is_active) ? 'checked' : '' }}
                class="rounded border-white/20 bg-slate-950 text-brand-600 focus:ring-brand-500/40">
            {{ __('Active') }}
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $movie->is_featured) ? 'checked' : '' }}
                class="rounded border-white/20 bg-slate-950 text-brand-600 focus:ring-brand-500/40">
            {{ __('Featured') }}
        </label>
    </div>

    <div class="flex gap-3 pt-2">
        <button class="rounded-lg bg-brand-600 hover:bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white">{{ $isEdit ? __('Save changes') : __('Create') }}</button>
        <a href="{{ route('admin.movies.index') }}" class="rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 px-5 py-2.5 text-sm text-white">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
