@extends('layouts.app')

@section('title', $movie->title)

@section('content')
@php($canWatch = auth()->check() && auth()->user()->activeSubscription())
<section class="relative">
    <div class="absolute inset-0 bg-cover bg-center opacity-20" style="background-image:url('{{ $movie->backdrop_url ?: $movie->poster_url }}')"></div>
    <div class="absolute inset-0 bg-slate-950/80"></div>

    <div class="relative max-w-7xl mx-auto px-4 py-10" data-animate>
        <a href="{{ route('movies.index') }}" class="text-sm text-slate-400 hover:text-white">← {{ __('Back to movies') }}</a>

        <div class="mt-6 grid md:grid-cols-3 gap-8">
            {{-- Poster --}}
            <div class="md:col-span-1">
                <div class="aspect-[2/3] rounded-2xl overflow-hidden bg-slate-900 border border-white/10 max-w-xs">
                    @if ($movie->poster_url)
                        <img src="{{ $movie->poster_url }}" alt="{{ $movie->title }}" class="h-full w-full object-cover">
                    @endif
                </div>
            </div>

            {{-- Details --}}
            <div class="md:col-span-2">
                <h1 class="text-3xl md:text-4xl font-black text-white">{{ $movie->title }}</h1>
                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-slate-300">
                    @if ($movie->rating > 0)<span class="rounded bg-amber-500/90 text-black font-bold px-2 py-0.5">★ {{ number_format($movie->rating, 1) }}</span>@endif
                    @if ($movie->year)<span>{{ $movie->year }}</span>@endif
                    @if ($movie->genre)<span class="rounded-full bg-white/10 px-3 py-0.5">{{ $movie->genre }}</span>@endif
                    @if ($movie->duration_minutes)<span>{{ $movie->duration_minutes }} {{ __('min') }}</span>@endif
                    <span class="rounded bg-brand-600/80 text-white px-2 py-0.5 text-xs font-bold">{{ $movie->quality }}</span>
                </div>
                <p class="mt-5 text-slate-300 leading-relaxed">{{ $movie->description }}</p>

                {{-- Player --}}
                <div class="mt-6 rounded-2xl overflow-hidden border border-white/10 bg-black aspect-video relative">
                    @if ($canWatch)
                        <video controls class="h-full w-full" poster="{{ $movie->backdrop_url ?: $movie->poster_url }}">
                            <source src="{{ $movie->stream_url ?: $movie->trailer_url }}">
                            {{ __('Your browser does not support video playback.') }}
                        </video>
                    @else
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-center p-6"
                             style="background-image:url('{{ $movie->backdrop_url ?: $movie->poster_url }}'); background-size:cover; background-position:center;">
                            <div class="absolute inset-0 bg-slate-950/80"></div>
                            <div class="relative">
                                <div class="text-4xl">🔒</div>
                                <p class="mt-3 text-white font-semibold">{{ __('Subscribe to watch the full movie') }}</p>
                                <a href="{{ route('packages.index') }}" class="mt-4 inline-block rounded-lg bg-brand-600 hover:bg-brand-500 px-6 py-2.5 font-semibold text-white">{{ __('View Plans') }}</a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

@if ($related->isNotEmpty())
<section class="max-w-7xl mx-auto px-4 py-12" data-animate>
    <h2 class="text-2xl font-bold text-white mb-6">{{ __('More like this') }}</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        @foreach ($related as $rel)
            @include('partials.poster-card', [
                'url' => route('movies.show', $rel),
                'title' => $rel->title,
                'poster' => $rel->poster_url,
                'meta' => $rel->year,
                'rating' => $rel->rating,
                'badge' => $rel->quality,
            ])
        @endforeach
    </div>
</section>
@endif
@endsection
