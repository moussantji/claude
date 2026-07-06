@extends('layouts.app')

@php
    $title = ($detail['title'] ?? null) ?: ($card['title'] ?? 'Fiche');
    $poster = ($detail['poster'] ?? null) ?: ($card['poster'] ?? null);
    $synopsis = ($detail['synopsis'] ?? null) ?: ($card['synopsis'] ?? null);
    $trailer = ($detail['trailer'] ?? null) ?: ($card['trailer'] ?? null);
    $facts = $detail['facts'] ?? [];
    $cast = $detail['cast'] ?? [];
    $meta = $detail['meta'] ?? [];
    $players = $detail['players'] ?? [];
    $jsPlayer = $detail['js_player'] ?? false;
    $sourceUrl = rtrim(config('fstream.base_url'), '/') . '/index.php?newsid=' . $id;
@endphp

@section('title', $title . ' — French Stream')

@section('content')
    <a href="{{ url()->previous() }}" class="text-sm text-orange-400 hover:text-orange-300">&larr; Retour</a>

    <div class="mt-4 grid md:grid-cols-[240px_1fr] gap-6">
        <div>
            @if ($poster)
                <img src="{{ $poster }}" alt="{{ $title }}" class="w-full rounded-lg border border-neutral-800">
            @endif
        </div>

        <div>
            <h1 class="text-2xl font-bold">{{ $title }}</h1>

            <div class="flex flex-wrap gap-2 mt-3 text-xs">
                @if (!empty($card['quality']))
                    <span class="font-bold bg-orange-600 px-2 py-0.5 rounded">{{ $card['quality'] }}</span>
                @endif
                @if (!empty($card['version']) || !empty($detail['episodes']))
                    <span class="font-semibold bg-neutral-800 px-2 py-0.5 rounded">{{ $card['version'] ?? $detail['episodes'] }}</span>
                @endif
                @if (!empty($facts['year']))
                    <span class="bg-neutral-800 px-2 py-0.5 rounded">{{ $facts['year'] }}</span>
                @endif
                @if (!empty($facts['runtime']))
                    <span class="bg-neutral-800 px-2 py-0.5 rounded">{{ $facts['runtime'] }}</span>
                @endif
                @if (!empty($detail['status']))
                    <span class="bg-emerald-700 px-2 py-0.5 rounded">{{ $detail['status'] }}</span>
                @endif
            </div>

            @if (!empty($facts['genres']))
                <p class="text-sm text-orange-300 mt-3">{{ $facts['genres'] }}</p>
            @endif

            @if ($synopsis)
                <p class="text-sm text-neutral-300 mt-4 leading-relaxed">{{ $synopsis }}</p>
            @endif

            @foreach ($meta as $k => $v)
                @if ($v)
                    <p class="text-sm text-neutral-400 mt-2"><span class="text-neutral-500">{{ $k }} :</span> {{ $v }}</p>
                @endif
            @endforeach

            @if (count($cast) > 0)
                <p class="text-sm text-neutral-400 mt-2"><span class="text-neutral-500">Casting :</span> {{ implode(', ', $cast) }}</p>
            @endif
        </div>
    </div>

    {{-- Regarder --}}
    <section class="mt-10">
        <h2 class="text-lg font-bold mb-3">Regarder</h2>

        @if ($error)
            <div class="rounded-md bg-red-950 border border-red-800 text-red-200 px-4 py-3 mb-4 text-sm">{{ $error }}</div>
        @endif

        @if (count($players) > 0)
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach ($players as $i => $p)
                    <button type="button" data-src="{{ $p['url'] }}"
                            class="player-btn rounded-md bg-neutral-800 hover:bg-orange-600 px-4 py-2 text-sm font-semibold">{{ $p['label'] }}</button>
                @endforeach
            </div>
            <div class="rounded-lg overflow-hidden bg-black border border-neutral-800 aspect-video">
                <iframe id="player-frame" src="{{ $players[0]['url'] }}" class="w-full h-full" allowfullscreen referrerpolicy="no-referrer"></iframe>
            </div>
            <script>
                document.querySelectorAll('.player-btn').forEach(function (b) {
                    b.addEventListener('click', function () {
                        document.getElementById('player-frame').src = b.dataset.src;
                    });
                });
            </script>
        @else
            <div class="rounded-lg bg-neutral-900 border border-neutral-800 p-5">
                <p class="text-neutral-300 text-sm leading-relaxed">
                    Les lecteurs de French&nbsp;Stream sont chargés dynamiquement par leur JavaScript
                    (système de jetons «&nbsp;vidzy&nbsp;»). Ils ne figurent pas dans le HTML de la page,
                    donc ils ne peuvent pas être récupérés par le scraping côté serveur.
                </p>
                <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer"
                   class="inline-block mt-4 rounded-md bg-orange-600 hover:bg-orange-500 px-5 py-2.5 text-sm font-semibold">
                    ▶ Ouvrir la page sur French Stream
                </a>
            </div>
        @endif
    </section>

    {{-- Bande-annonce YouTube --}}
    @if ($trailer)
        <section class="mt-10">
            <h2 class="text-lg font-bold mb-3">Bande-annonce</h2>
            <div class="rounded-lg overflow-hidden bg-black border border-neutral-800 aspect-video max-w-3xl">
                <iframe class="w-full h-full" src="https://www.youtube.com/embed/{{ $trailer }}"
                        title="Bande-annonce" allowfullscreen
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
            </div>
        </section>
    @endif
@endsection
