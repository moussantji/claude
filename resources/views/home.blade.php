@extends('layouts.app')

@section('title', __('Stream Everything, Anywhere'))

@section('content')
{{-- Hero --}}
<section class="relative overflow-hidden hero-grid">
    <div class="absolute inset-0 bg-cover bg-center" style="background-image:url('{{ config('streamwave.images.hero') }}')"></div>
    <div class="absolute inset-0 bg-slate-950/80"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-brand-600/20 via-slate-950/40 to-slate-950"></div>
    <div class="relative max-w-7xl mx-auto px-4 py-24 md:py-32 text-center" data-animate>
        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-sm text-slate-300">
            <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
            {{ __(':count+ channels streaming live now', ['count' => number_format($channelCount)]) }}
        </span>
        <h1 class="mt-6 text-4xl md:text-6xl font-black tracking-tight text-white">
            {{ __('Your favorite TV,') }}<br>
            <span class="bg-gradient-to-r from-brand-400 to-fuchsia-400 bg-clip-text text-transparent">{{ __('all in one place.') }}</span>
        </h1>
        <p class="mt-6 max-w-2xl mx-auto text-lg text-slate-300">
            {{ __('Thousands of live channels, sports, movies and series in crystal-clear HD & 4K. Watch on any device, anywhere in the world.') }}
        </p>
        <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="{{ route('packages.index') }}" class="w-full sm:w-auto rounded-lg bg-brand-600 hover:bg-brand-500 px-8 py-3.5 font-semibold text-white shadow-lg shadow-brand-600/30">
                {{ __('View Plans') }}
            </a>
            <a href="{{ route('channels.index') }}" class="w-full sm:w-auto rounded-lg bg-white/10 hover:bg-white/20 border border-white/10 px-8 py-3.5 font-semibold text-white backdrop-blur">
                {{ __('Browse Channels') }}
            </a>
        </div>
    </div>
</section>

{{-- Stats --}}
<section class="border-y border-white/10 bg-slate-900/30">
    <div class="max-w-7xl mx-auto px-4 py-12 grid grid-cols-2 md:grid-cols-4 gap-8 text-center" data-animate>
        <div>
            <div class="text-3xl md:text-4xl font-black text-white">{{ number_format($channelCount) }}+</div>
            <div class="text-sm text-slate-400 mt-1">{{ __('Live Channels') }}</div>
        </div>
        <div>
            <div class="text-3xl md:text-4xl font-black text-white">{{ $categoryCount }}</div>
            <div class="text-sm text-slate-400 mt-1">{{ __('Categories') }}</div>
        </div>
        <div>
            <div class="text-3xl md:text-4xl font-black text-white">4K</div>
            <div class="text-sm text-slate-400 mt-1">{{ __('Ultra HD Quality') }}</div>
        </div>
        <div>
            <div class="text-3xl md:text-4xl font-black text-white">99.9%</div>
            <div class="text-sm text-slate-400 mt-1">{{ __('Uptime') }}</div>
        </div>
    </div>
</section>

{{-- Features --}}
<section class="relative">
    <div class="absolute inset-0 bg-cover bg-center opacity-[0.07]" style="background-image:url('{{ config('streamwave.images.features') }}')"></div>
    <div class="relative max-w-7xl mx-auto px-4 py-20">
        <div class="text-center max-w-2xl mx-auto" data-animate>
            <h2 class="text-3xl md:text-4xl font-bold text-white">{{ __('Why choose :app?', ['app' => config('app.name')]) }}</h2>
            <p class="mt-3 text-slate-400">{{ __('Everything you need for the ultimate streaming experience.') }}</p>
        </div>
        <div class="mt-12 grid md:grid-cols-3 gap-6">
            @php
                $features = [
                    ['📺', __('Multi-Device'), __('Stream on Smart TV, phone, tablet, computer and set-top boxes.')],
                    ['⚡', __('Zero Buffering'), __('Optimized servers deliver smooth playback even in 4K.')],
                    ['🌍', __('Global Content'), __('Channels from every continent, in every language.')],
                    ['🎬', __('VOD Library'), __('Thousands of on-demand movies and series.')],
                    ['🔒', __('Secure & Private'), __('Encrypted connections keep your viewing private.')],
                    ['🛟', __('24/7 Support'), __('Our team is always ready to help you out.')],
                ];
            @endphp
            @foreach ($features as [$icon, $title, $desc])
                <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-6 hover:border-brand-500/40 transition" data-animate>
                    <div class="text-3xl">{{ $icon }}</div>
                    <h3 class="mt-4 text-lg font-semibold text-white">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-slate-400">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Pricing preview --}}
@if ($packages->isNotEmpty())
<section class="max-w-7xl mx-auto px-4 py-12">
    <div class="text-center max-w-2xl mx-auto mb-12" data-animate>
        <h2 class="text-3xl md:text-4xl font-bold text-white">{{ __('Simple, transparent pricing') }}</h2>
        <p class="mt-3 text-slate-400">{{ __('Pick a plan and start watching today.') }}</p>
    </div>
    <div class="grid md:grid-cols-2 lg:grid-cols-{{ min($packages->count(), 4) }} gap-6" data-animate>
        @foreach ($packages as $package)
            @include('partials.package-card', ['package' => $package])
        @endforeach
    </div>
</section>
@endif

{{-- Channels preview --}}
@if ($featuredChannels->isNotEmpty())
<section class="max-w-7xl mx-auto px-4 py-20">
    <div class="flex items-end justify-between mb-8" data-animate>
        <div>
            <h2 class="text-3xl md:text-4xl font-bold text-white">{{ __('Popular channels') }}</h2>
            <p class="mt-2 text-slate-400">{{ __("A small taste of what's included.") }}</p>
        </div>
        <a href="{{ route('channels.index') }}" class="hidden sm:inline-flex text-brand-400 hover:text-brand-300 font-medium">{{ __('View all') }} →</a>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4" data-animate>
        @foreach ($featuredChannels as $channel)
            @include('partials.channel-card', ['channel' => $channel])
        @endforeach
    </div>
</section>
@endif

{{-- Movies preview --}}
@if ($movies->isNotEmpty())
<section class="max-w-7xl mx-auto px-4 py-12">
    <div class="flex items-end justify-between mb-8" data-animate>
        <div>
            <h2 class="text-3xl md:text-4xl font-bold text-white">{{ __('Trending movies') }}</h2>
            <p class="mt-2 text-slate-400">{{ __('Blockbusters and hidden gems on demand.') }}</p>
        </div>
        <a href="{{ route('movies.index') }}" class="hidden sm:inline-flex text-brand-400 hover:text-brand-300 font-medium">{{ __('View all') }} →</a>
    </div>
    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-4" data-animate>
        @foreach ($movies as $movie)
            @include('partials.poster-card', [
                'url' => route('movies.show', $movie),
                'title' => $movie->title,
                'poster' => $movie->poster_url,
                'meta' => $movie->year,
                'rating' => $movie->rating,
                'badge' => $movie->quality,
            ])
        @endforeach
    </div>
</section>
@endif

{{-- Series preview --}}
@if ($series->isNotEmpty())
<section class="max-w-7xl mx-auto px-4 py-12">
    <div class="flex items-end justify-between mb-8" data-animate>
        <div>
            <h2 class="text-3xl md:text-4xl font-bold text-white">{{ __('Binge-worthy series') }}</h2>
            <p class="mt-2 text-slate-400">{{ __('Box sets you will not want to pause.') }}</p>
        </div>
        <a href="{{ route('series.index') }}" class="hidden sm:inline-flex text-brand-400 hover:text-brand-300 font-medium">{{ __('View all') }} →</a>
    </div>
    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-4" data-animate>
        @foreach ($series as $serie)
            @include('partials.poster-card', [
                'url' => route('series.show', $serie),
                'title' => $serie->title,
                'poster' => $serie->poster_url,
                'meta' => $serie->start_year,
                'rating' => $serie->rating,
                'badge' => $serie->seasons . ' ' . __('seasons'),
            ])
        @endforeach
    </div>
</section>
@endif

{{-- CTA --}}
<section class="max-w-7xl mx-auto px-4 pb-24">
    <div class="relative overflow-hidden rounded-3xl px-8 py-14 text-center" data-animate>
        <div class="absolute inset-0 bg-cover bg-center" style="background-image:url('{{ config('streamwave.images.cta') }}')"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-brand-600/90 to-fuchsia-600/90"></div>
        <div class="relative">
            <h2 class="text-3xl md:text-4xl font-black text-white">{{ __('Ready to start streaming?') }}</h2>
            <p class="mt-3 text-white/80 max-w-xl mx-auto">{{ __('Join thousands of happy viewers. Cancel anytime.') }}</p>
            <a href="{{ route('register') }}" class="mt-8 inline-block rounded-lg bg-white text-brand-700 px-8 py-3.5 font-bold hover:bg-slate-100">
                {{ __('Create Free Account') }}
            </a>
        </div>
    </div>
</section>

{{-- Contact / WhatsApp --}}
@php
    $waNumber = preg_replace('/\D/', '', config('streamwave.whatsapp'));
    $waLink = 'https://wa.me/' . $waNumber . '?text=' . rawurlencode(__('Hi! I would like more information about StreamWave IPTV.'));
@endphp
<section id="contact" class="relative">
    <div class="absolute inset-0 bg-cover bg-center opacity-20" style="background-image:url('{{ config('streamwave.images.contact') }}')"></div>
    <div class="absolute inset-0 bg-slate-950/80"></div>
    <div class="relative max-w-3xl mx-auto px-4 py-20 text-center" data-animate>
        <h2 class="text-3xl md:text-4xl font-bold text-white">{{ __('Questions? Talk to us') }}</h2>
        <p class="mt-3 text-slate-300">{{ __('Our team replies fast on WhatsApp — get help choosing a plan or setting up your player.') }}</p>
        <a href="{{ $waLink }}" target="_blank" rel="noopener"
           class="mt-8 inline-flex items-center gap-3 rounded-lg bg-[#25D366] hover:bg-[#1ebe5d] px-8 py-3.5 font-semibold text-white shadow-lg shadow-emerald-900/30">
            <svg viewBox="0 0 32 32" class="h-5 w-5 fill-current"><path d="M16 3.2C8.94 3.2 3.2 8.94 3.2 16c0 2.26.6 4.46 1.73 6.4L3.2 28.8l6.57-1.72A12.74 12.74 0 0 0 16 28.68C23.06 28.68 28.8 22.94 28.8 16S23.06 3.2 16 3.2zm0 23.04a10.6 10.6 0 0 1-5.41-1.48l-.39-.23-3.9 1.02 1.04-3.8-.25-.4A10.58 10.58 0 0 1 5.37 16C5.37 10.14 10.14 5.37 16 5.37S26.63 10.14 26.63 16 21.86 26.24 16 26.24z"/></svg>
            {{ __('Chat on WhatsApp') }}
        </a>
    </div>
</section>
@endsection
