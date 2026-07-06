@extends('layouts.app')

@php
    $title = $card['title'] ?? ($detail['title'] ?? 'Film');
    $poster = $card['poster'] ?? ($detail['poster'] ?? null);
    $synopsis = $card['synopsis'] ?? ($detail['synopsis'] ?? null);
    $trailer = $card['trailer'] ?? null;
    $players = $detail['players'] ?? [];
@endphp

@section('title', $title . ' — French Stream')

@section('content')
    <a href="{{ url()->previous() }}" class="text-sm text-orange-400 hover:text-orange-300">&larr; Retour</a>

    <div class="mt-4 grid md:grid-cols-[220px_1fr] gap-6">
        <div>
            @if ($poster)
                <img src="{{ $poster }}" alt="{{ $title }}" class="w-full rounded-lg border border-neutral-800">
            @endif
        </div>

        <div>
            <h1 class="text-2xl font-bold">{{ $title }}</h1>
            <div class="flex flex-wrap gap-2 mt-2">
                @if (!empty($card['quality']))
                    <span class="text-xs font-bold bg-orange-600 px-2 py-0.5 rounded">{{ $card['quality'] }}</span>
                @endif
                @if (!empty($card['version']))
                    <span class="text-xs font-semibold bg-neutral-800 px-2 py-0.5 rounded">{{ $card['version'] }}</span>
                @endif
            </div>
            @if ($synopsis)
                <p class="text-sm text-neutral-300 mt-4 leading-relaxed">{{ $synopsis }}</p>
            @endif
        </div>
    </div>

    {{-- Lecteurs / streaming --}}
    <section class="mt-10">
        <h2 class="text-lg font-bold mb-3">Regarder</h2>

        @if ($error)
            <div class="rounded-md bg-red-950 border border-red-800 text-red-200 px-4 py-3 mb-4 text-sm">
                {{ $error }}
            </div>
        @endif

        @if (count($players) > 0)
            <div class="flex flex-wrap gap-2 mb-4" id="player-buttons">
                @foreach ($players as $i => $p)
                    <button type="button"
                            data-src="{{ $p['url'] }}"
                            class="player-btn rounded-md bg-neutral-800 hover:bg-orange-600 px-4 py-2 text-sm font-semibold">
                        {{ $p['label'] }}
                    </button>
                @endforeach
            </div>
            <div class="rounded-lg overflow-hidden bg-black border border-neutral-800 aspect-video">
                <iframe id="player-frame" src="{{ $players[0]['url'] }}"
                        class="w-full h-full" allowfullscreen
                        referrerpolicy="no-referrer"></iframe>
            </div>
            <script>
                document.querySelectorAll('.player-btn').forEach(function (b) {
                    b.addEventListener('click', function () {
                        document.getElementById('player-frame').src = b.dataset.src;
                        document.querySelectorAll('.player-btn').forEach(x => x.classList.remove('bg-orange-600'));
                        b.classList.add('bg-orange-600');
                    });
                });
            </script>
        @else
            <div class="rounded-md bg-neutral-900 border border-neutral-800 px-4 py-4 text-sm text-neutral-400">
                Aucun lecteur détecté sur cette page.
                <br>
                <span class="text-neutral-500">
                    La récupération des lecteurs de streaming dépend du HTML de la page de détail
                    (protégée par Cloudflare et propre à french-stream). Envoie-moi le code source
                    d'une page <code>/index.php?newsid=…</code> et j'ajusterai le parseur.
                </span>
            </div>
        @endif
    </section>

    {{-- Bande-annonce YouTube --}}
    @if ($trailer)
        <section class="mt-10">
            <h2 class="text-lg font-bold mb-3">Bande-annonce</h2>
            <div class="rounded-lg overflow-hidden bg-black border border-neutral-800 aspect-video max-w-3xl">
                <iframe class="w-full h-full"
                        src="https://www.youtube.com/embed/{{ $trailer }}"
                        title="Bande-annonce" allowfullscreen
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
            </div>
        </section>
    @endif
@endsection
