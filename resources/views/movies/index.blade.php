@extends('layouts.app')

@section('title', __('Movies'))

@section('content')
@if ($featured)
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-cover bg-center" style="background-image:url('{{ $featured->backdrop_url ?: $featured->poster_url }}')"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/80 to-slate-950/30"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 to-transparent"></div>
        <div class="relative max-w-7xl mx-auto px-4 py-20 md:py-28" data-animate>
            <span class="inline-block rounded-full bg-brand-600/90 text-white text-xs font-bold px-3 py-1 uppercase tracking-wide">{{ __('Featured movie') }}</span>
            <h1 class="mt-4 text-4xl md:text-6xl font-black text-white max-w-2xl">{{ $featured->title }}</h1>
            <div class="mt-3 flex items-center gap-3 text-sm text-slate-300">
                @if ($featured->rating > 0)<span class="text-amber-400 font-bold">★ {{ number_format($featured->rating, 1) }}</span>@endif
                @if ($featured->year)<span>{{ $featured->year }}</span>@endif
                @if ($featured->genre)<span>· {{ $featured->genre }}</span>@endif
                @if ($featured->duration_minutes)<span>· {{ $featured->duration_minutes }} {{ __('min') }}</span>@endif
            </div>
            <p class="mt-4 text-slate-300 max-w-xl line-clamp-3">{{ $featured->description }}</p>
            <a href="{{ route('movies.show', $featured) }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-white text-slate-900 px-6 py-3 font-bold hover:bg-slate-100">▶ {{ __('Watch now') }}</a>
        </div>
    </section>
@else
    <section class="hero-grid border-b border-white/10">
        <div class="max-w-7xl mx-auto px-4 py-16 text-center">
            <h1 class="text-4xl md:text-5xl font-black text-white">{{ __('Movies') }}</h1>
        </div>
    </section>
@endif

<section class="max-w-7xl mx-auto px-4 py-12" data-animate>
    <livewire:movie-browser />
</section>
@endsection
