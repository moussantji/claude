@extends('layouts.admin')

@section('title', __('Movies'))

@section('content')
<div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
    <h1 class="text-2xl font-bold text-white">{{ __('Movies') }}</h1>
    <div class="flex items-center gap-3">
        <form method="GET" action="{{ route('admin.movies.index') }}">
            <input name="search" value="{{ $search }}" placeholder="{{ __('Search…') }}"
                class="rounded-lg bg-slate-950 border border-white/10 px-3 py-2 text-sm text-white focus:border-brand-500 outline-none">
        </form>
        <a href="{{ route('admin.movies.create') }}" class="rounded-lg bg-brand-600 hover:bg-brand-500 px-4 py-2 text-sm font-semibold text-white whitespace-nowrap">+ {{ __('New Movie') }}</a>
    </div>
</div>

<div class="rounded-2xl border border-white/10 bg-slate-900/40 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-400 border-b border-white/10 bg-white/5">
                    <th class="py-3 px-4 font-medium">{{ __('Title') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Genre') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Year') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Rating') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Status') }}</th>
                    <th class="py-3 px-4 font-medium text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movies as $movie)
                    <tr class="border-b border-white/5">
                        <td class="py-3 px-4 text-white font-medium">
                            {{ $movie->title }}
                            @if ($movie->is_featured)<span class="ml-1 text-[10px] uppercase font-bold text-brand-400">★</span>@endif
                        </td>
                        <td class="py-3 px-4 text-slate-300">{{ $movie->genre ?? '—' }}</td>
                        <td class="py-3 px-4 text-slate-300">{{ $movie->year ?? '—' }}</td>
                        <td class="py-3 px-4 text-slate-300">{{ $movie->rating > 0 ? number_format($movie->rating, 1) : '—' }}</td>
                        <td class="py-3 px-4">
                            <span class="text-xs font-bold px-2 py-0.5 rounded {{ $movie->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-500/20 text-slate-400' }}">
                                {{ $movie->is_active ? __('Active') : __('Hidden') }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right whitespace-nowrap">
                            <a href="{{ route('admin.movies.edit', $movie) }}" class="text-brand-400 hover:text-brand-300">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.movies.destroy', $movie) }}" class="inline ml-3" onsubmit="return confirm('{{ __('Delete this item?') }}');">
                                @csrf @method('DELETE')
                                <button class="text-rose-400 hover:text-rose-300">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">{{ __('No movies found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $movies->links() }}</div>
@endsection
