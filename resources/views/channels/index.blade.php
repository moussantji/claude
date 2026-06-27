@extends('layouts.app')

@section('title', 'Channel Lineup')

@section('content')
<section class="hero-grid border-b border-white/10">
    <div class="max-w-7xl mx-auto px-4 py-16 text-center">
        <h1 class="text-4xl md:text-5xl font-black text-white">Channel lineup</h1>
        <p class="mt-4 text-lg text-slate-400 max-w-2xl mx-auto">
            Explore channels across every category. New channels are added every week.
        </p>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 py-12">
    @php($hasChannels = $categories->contains(fn ($c) => $c->channels->isNotEmpty()))

    @if (! $hasChannels)
        <p class="text-center text-slate-400">No channels have been published yet.</p>
    @else
        {{-- Category quick nav --}}
        <div class="flex flex-wrap gap-2 mb-10 justify-center">
            @foreach ($categories as $category)
                @if ($category->channels->isNotEmpty())
                    <a href="#cat-{{ $category->slug }}" class="rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-sm text-slate-300 hover:bg-white/10 hover:text-white">
                        {{ $category->icon }} {{ $category->name }}
                        <span class="text-slate-500">({{ $category->channels->count() }})</span>
                    </a>
                @endif
            @endforeach
        </div>

        @foreach ($categories as $category)
            @if ($category->channels->isNotEmpty())
                <div id="cat-{{ $category->slug }}" class="mb-14 scroll-mt-20">
                    <h2 class="text-2xl font-bold text-white mb-6 flex items-center gap-2">
                        <span>{{ $category->icon }}</span> {{ $category->name }}
                    </h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        @foreach ($category->channels as $channel)
                            @include('partials.channel-card', ['channel' => $channel])
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    @endif
</section>
@endsection
