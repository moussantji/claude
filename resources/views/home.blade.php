@extends('layouts.app')

@section('title', 'Stream Everything, Anywhere')

@section('content')
{{-- Hero --}}
<section class="relative overflow-hidden hero-grid">
    <div class="absolute inset-0 bg-gradient-to-b from-brand-600/10 via-transparent to-slate-950"></div>
    <div class="relative max-w-7xl mx-auto px-4 py-24 md:py-32 text-center">
        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-sm text-slate-300">
            <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
            {{ number_format($channelCount) }}+ channels streaming live now
        </span>
        <h1 class="mt-6 text-4xl md:text-6xl font-black tracking-tight text-white">
            Your favorite TV,<br>
            <span class="bg-gradient-to-r from-brand-400 to-fuchsia-400 bg-clip-text text-transparent">all in one place.</span>
        </h1>
        <p class="mt-6 max-w-2xl mx-auto text-lg text-slate-400">
            Thousands of live channels, sports, movies and series in crystal-clear HD &amp; 4K.
            Watch on any device, anywhere in the world.
        </p>
        <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="{{ route('packages.index') }}" class="w-full sm:w-auto rounded-lg bg-brand-600 hover:bg-brand-500 px-8 py-3.5 font-semibold text-white shadow-lg shadow-brand-600/30">
                View Plans
            </a>
            <a href="{{ route('channels.index') }}" class="w-full sm:w-auto rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 px-8 py-3.5 font-semibold text-white">
                Browse Channels
            </a>
        </div>
    </div>
</section>

{{-- Stats --}}
<section class="border-y border-white/10 bg-slate-900/30">
    <div class="max-w-7xl mx-auto px-4 py-12 grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
        <div>
            <div class="text-3xl md:text-4xl font-black text-white">{{ number_format($channelCount) }}+</div>
            <div class="text-sm text-slate-400 mt-1">Live Channels</div>
        </div>
        <div>
            <div class="text-3xl md:text-4xl font-black text-white">{{ $categoryCount }}</div>
            <div class="text-sm text-slate-400 mt-1">Categories</div>
        </div>
        <div>
            <div class="text-3xl md:text-4xl font-black text-white">4K</div>
            <div class="text-sm text-slate-400 mt-1">Ultra HD Quality</div>
        </div>
        <div>
            <div class="text-3xl md:text-4xl font-black text-white">99.9%</div>
            <div class="text-sm text-slate-400 mt-1">Uptime</div>
        </div>
    </div>
</section>

{{-- Features --}}
<section class="max-w-7xl mx-auto px-4 py-20">
    <div class="text-center max-w-2xl mx-auto">
        <h2 class="text-3xl md:text-4xl font-bold text-white">Why choose {{ config('app.name') }}?</h2>
        <p class="mt-3 text-slate-400">Everything you need for the ultimate streaming experience.</p>
    </div>
    <div class="mt-12 grid md:grid-cols-3 gap-6">
        @php($features = [
            ['📺', 'Multi-Device', 'Stream on Smart TV, phone, tablet, computer and set-top boxes.'],
            ['⚡', 'Zero Buffering', 'Optimized servers deliver smooth playback even in 4K.'],
            ['🌍', 'Global Content', 'Channels from every continent, in every language.'],
            ['🎬', 'VOD Library', 'Thousands of on-demand movies and series.'],
            ['🔒', 'Secure & Private', 'Encrypted connections keep your viewing private.'],
            ['🛟', '24/7 Support', 'Our team is always ready to help you out.'],
        ])
        @foreach ($features as [$icon, $title, $desc])
            <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-6 hover:border-brand-500/40 transition">
                <div class="text-3xl">{{ $icon }}</div>
                <h3 class="mt-4 text-lg font-semibold text-white">{{ $title }}</h3>
                <p class="mt-2 text-sm text-slate-400">{{ $desc }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Pricing preview --}}
@if ($packages->isNotEmpty())
<section class="max-w-7xl mx-auto px-4 py-12">
    <div class="text-center max-w-2xl mx-auto mb-12">
        <h2 class="text-3xl md:text-4xl font-bold text-white">Simple, transparent pricing</h2>
        <p class="mt-3 text-slate-400">Pick a plan and start watching today.</p>
    </div>
    <div class="grid md:grid-cols-2 lg:grid-cols-{{ min($packages->count(), 4) }} gap-6">
        @foreach ($packages as $package)
            @include('partials.package-card', ['package' => $package])
        @endforeach
    </div>
</section>
@endif

{{-- Channels preview --}}
@if ($featuredChannels->isNotEmpty())
<section class="max-w-7xl mx-auto px-4 py-20">
    <div class="flex items-end justify-between mb-8">
        <div>
            <h2 class="text-3xl md:text-4xl font-bold text-white">Popular channels</h2>
            <p class="mt-2 text-slate-400">A small taste of what's included.</p>
        </div>
        <a href="{{ route('channels.index') }}" class="hidden sm:inline-flex text-brand-400 hover:text-brand-300 font-medium">View all →</a>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        @foreach ($featuredChannels as $channel)
            @include('partials.channel-card', ['channel' => $channel])
        @endforeach
    </div>
</section>
@endif

{{-- CTA --}}
<section class="max-w-7xl mx-auto px-4 pb-24">
    <div class="rounded-3xl bg-gradient-to-r from-brand-600 to-fuchsia-600 px-8 py-14 text-center">
        <h2 class="text-3xl md:text-4xl font-black text-white">Ready to start streaming?</h2>
        <p class="mt-3 text-white/80 max-w-xl mx-auto">Join thousands of happy viewers. Cancel anytime.</p>
        <a href="{{ route('register') }}" class="mt-8 inline-block rounded-lg bg-white text-brand-700 px-8 py-3.5 font-bold hover:bg-slate-100">
            Create Free Account
        </a>
    </div>
</section>
@endsection
