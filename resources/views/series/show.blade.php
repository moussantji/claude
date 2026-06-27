@extends('layouts.app')

@section('title', $series->title)

@section('content')
@php($canWatch = auth()->check() && auth()->user()->activeSubscription())
<section class="relative">
    <div class="absolute inset-0 bg-cover bg-center opacity-20" style="background-image:url('{{ $series->backdrop_url ?: $series->poster_url }}')"></div>
    <div class="absolute inset-0 bg-slate-950/80"></div>

    <div class="relative max-w-7xl mx-auto px-4 py-10" data-animate>
        <a href="{{ route('series.index') }}" class="text-sm text-slate-400 hover:text-white">← {{ __('Back to series') }}</a>

        <div class="mt-6 grid md:grid-cols-3 gap-8">
            <div class="md:col-span-1">
                <div class="aspect-[2/3] rounded-2xl overflow-hidden bg-slate-900 border border-white/10 max-w-xs">
                    @if ($series->poster_url)
                        <img src="{{ $series->poster_url }}" alt="{{ $series->title }}" class="h-full w-full object-cover">
                    @endif
                </div>
            </div>

            <div class="md:col-span-2">
                <h1 class="text-3xl md:text-4xl font-black text-white">{{ $series->title }}</h1>
                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-slate-300">
                    @if ($series->rating > 0)<span class="rounded bg-amber-500/90 text-black font-bold px-2 py-0.5">★ {{ number_format($series->rating, 1) }}</span>@endif
                    <span>{{ $series->start_year }}@if($series->end_year)–{{ $series->end_year }}@endif</span>
                    <span class="rounded-full bg-white/10 px-3 py-0.5">{{ $series->seasons }} {{ __('seasons') }}</span>
                    @if ($series->genre)<span class="rounded-full bg-white/10 px-3 py-0.5">{{ $series->genre }}</span>@endif
                </div>
                <p class="mt-5 text-slate-300 leading-relaxed">{{ $series->description }}</p>

                @unless ($canWatch)
                    <div class="mt-6 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200 flex items-center justify-between gap-4 flex-wrap">
                        <span>🔒 {{ __('Subscribe to stream all episodes.') }}</span>
                        <a href="{{ route('packages.index') }}" class="rounded-lg bg-brand-600 hover:bg-brand-500 px-4 py-2 font-semibold text-white">{{ __('View Plans') }}</a>
                    </div>
                @endunless
            </div>
        </div>

        {{-- Episodes --}}
        <div class="mt-10">
            <h2 class="text-xl font-bold text-white mb-4">{{ __('Episodes') }}</h2>
            @forelse ($episodesBySeason as $season => $episodes)
                <div class="mb-6">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400 mb-3">{{ __('Season :n', ['n' => $season]) }}</h3>
                    <div class="space-y-2">
                        @foreach ($episodes as $ep)
                            <div class="flex items-center justify-between gap-4 rounded-xl border border-white/10 bg-slate-900/40 px-4 py-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="shrink-0 h-8 w-8 rounded-lg bg-white/5 flex items-center justify-center text-sm font-bold text-slate-300">{{ $ep->episode_number }}</span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-white truncate">{{ $ep->title }}</p>
                                        @if ($ep->duration_minutes)<p class="text-xs text-slate-500">{{ $ep->duration_minutes }} {{ __('min') }}</p>@endif
                                    </div>
                                </div>
                                @if ($canWatch)
                                    <span class="shrink-0 text-brand-400 text-sm font-semibold">▶ {{ __('Play') }}</span>
                                @else
                                    <span class="shrink-0 text-slate-500 text-sm">🔒</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">{{ __('Episodes coming soon.') }}</p>
            @endforelse
        </div>
    </div>
</section>

@if ($related->isNotEmpty())
<section class="max-w-7xl mx-auto px-4 py-12" data-animate>
    <h2 class="text-2xl font-bold text-white mb-6">{{ __('More like this') }}</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        @foreach ($related as $rel)
            @include('partials.poster-card', [
                'url' => route('series.show', $rel),
                'title' => $rel->title,
                'poster' => $rel->poster_url,
                'meta' => $rel->start_year,
                'rating' => $rel->rating,
                'badge' => $rel->seasons . ' ' . __('seasons'),
            ])
        @endforeach
    </div>
</section>
@endif
@endsection
