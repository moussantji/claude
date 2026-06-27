@extends('layouts.admin')

@section('title', $series->exists ? __('Edit Series') : __('New Series'))

@section('content')
@php($isEdit = $series->exists)
<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.series.index') }}" class="text-slate-400 hover:text-white">←</a>
    <h1 class="text-2xl font-bold text-white">{{ $isEdit ? __('Edit Series') : __('New Series') }}</h1>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <form method="POST" action="{{ $isEdit ? route('admin.series.update', $series) : route('admin.series.store') }}"
          class="space-y-5 rounded-2xl border border-white/10 bg-slate-900/40 p-6">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Title') }}</label>
            <input name="title" value="{{ old('title', $series->title) }}" required
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Description') }}</label>
            <textarea name="description" rows="3"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">{{ old('description', $series->description) }}</textarea>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Genre') }}</label>
                <input name="genre" value="{{ old('genre', $series->genre) }}"
                    class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Seasons') }}</label>
                <input name="seasons" type="number" min="1" value="{{ old('seasons', $series->seasons) }}" required
                    class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Start year') }}</label>
                <input name="start_year" type="number" value="{{ old('start_year', $series->start_year) }}"
                    class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('End year') }}</label>
                <input name="end_year" type="number" value="{{ old('end_year', $series->end_year) }}"
                    class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Rating (0-10)') }}</label>
                <input name="rating" type="number" step="0.1" min="0" max="10" value="{{ old('rating', $series->rating) }}"
                    class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Poster URL') }}</label>
            <input name="poster_url" type="url" value="{{ old('poster_url', $series->poster_url) }}"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Backdrop URL') }}</label>
            <input name="backdrop_url" type="url" value="{{ old('backdrop_url', $series->backdrop_url) }}"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
        </div>
        <div class="flex gap-6">
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $series->is_active) ? 'checked' : '' }}
                    class="rounded border-white/20 bg-slate-950 text-brand-600 focus:ring-brand-500/40">{{ __('Active') }}
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $series->is_featured) ? 'checked' : '' }}
                    class="rounded border-white/20 bg-slate-950 text-brand-600 focus:ring-brand-500/40">{{ __('Featured') }}
            </label>
        </div>
        <div class="flex gap-3 pt-2">
            <button class="rounded-lg bg-brand-600 hover:bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white">{{ $isEdit ? __('Save changes') : __('Create') }}</button>
            <a href="{{ route('admin.series.index') }}" class="rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 px-5 py-2.5 text-sm text-white">{{ __('Cancel') }}</a>
        </div>
    </form>

    {{-- Episodes management (only after the series exists) --}}
    <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-6">
        <h2 class="text-lg font-semibold text-white mb-4">{{ __('Episodes') }}</h2>
        @if (! $isEdit)
            <p class="text-sm text-slate-500">{{ __('Save the series first to add episodes.') }}</p>
        @else
            <form method="POST" action="{{ route('admin.series.episodes.store', $series) }}" class="grid grid-cols-2 gap-3 mb-6">
                @csrf
                <input name="season" type="number" min="1" value="1" placeholder="{{ __('Season') }}" required
                    class="rounded-lg bg-slate-950 border border-white/10 px-3 py-2 text-sm text-white focus:border-brand-500 outline-none">
                <input name="episode_number" type="number" min="1" value="1" placeholder="{{ __('Episode #') }}" required
                    class="rounded-lg bg-slate-950 border border-white/10 px-3 py-2 text-sm text-white focus:border-brand-500 outline-none">
                <input name="title" placeholder="{{ __('Episode title') }}" required
                    class="col-span-2 rounded-lg bg-slate-950 border border-white/10 px-3 py-2 text-sm text-white focus:border-brand-500 outline-none">
                <input name="stream_url" placeholder="{{ __('Stream URL') }}"
                    class="col-span-2 rounded-lg bg-slate-950 border border-white/10 px-3 py-2 text-sm text-white font-mono focus:border-brand-500 outline-none">
                <input name="duration_minutes" type="number" min="1" placeholder="{{ __('Duration (min)') }}"
                    class="rounded-lg bg-slate-950 border border-white/10 px-3 py-2 text-sm text-white focus:border-brand-500 outline-none">
                <button class="rounded-lg bg-brand-600 hover:bg-brand-500 px-4 py-2 text-sm font-semibold text-white">+ {{ __('Add episode') }}</button>
            </form>

            <div class="space-y-2 max-h-96 overflow-y-auto">
                @forelse ($series->episodes as $ep)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-white/10 bg-slate-950 px-3 py-2">
                        <span class="text-sm text-slate-200 truncate">S{{ $ep->season }}E{{ $ep->episode_number }} — {{ $ep->title }}</span>
                        <form method="POST" action="{{ route('admin.series.episodes.destroy', [$series, $ep]) }}" onsubmit="return confirm('{{ __('Remove this episode?') }}');">
                            @csrf @method('DELETE')
                            <button class="text-rose-400 hover:text-rose-300 text-sm">✕</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">{{ __('No episodes yet.') }}</p>
                @endforelse
            </div>
        @endif
    </div>
</div>
@endsection
