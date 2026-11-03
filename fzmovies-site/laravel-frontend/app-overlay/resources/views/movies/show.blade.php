@extends('layouts.app')

@section('title', $title)

@section('content')
    <a href="{{ url()->previous() }}" class="text-sm text-indigo-400 hover:text-indigo-300">&larr; Retour</a>

    <h1 class="text-2xl font-bold mt-3 mb-6">
        {{ $title }}
        @if ($year) <span class="text-slate-400 font-normal">({{ $year }})</span> @endif
    </h1>

    @if ($error)
        <div class="rounded-md bg-red-950 border border-red-800 text-red-200 px-4 py-3 mb-6 text-sm">
            {{ $error }}
        </div>
    @endif

    @if ($trailer)
        <a href="{{ $trailer }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 text-sm text-rose-400 hover:text-rose-300 mb-6">
            ▶ Voir la bande-annonce
        </a>
    @endif

    <section class="mb-10">
        <h2 class="text-lg font-semibold mb-4">Fichiers disponibles</h2>

        @if (count($files) === 0)
            <p class="text-slate-400">Aucun fichier trouvé pour ce film.</p>
        @else
            <div class="space-y-3">
                @foreach ($files as $file)
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-lg bg-slate-900 border border-slate-800 px-4 py-3">
                        <div>
                            <p class="font-medium">{{ $file['title'] ?? 'Fichier' }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Taille : {{ $file['size'] ?? '?' }}
                                @if (!empty($file['hits'])) &middot; {{ $file['hits'] }} téléchargements @endif
                            </p>
                        </div>
                        <a href="{{ route('movies.download', ['url' => $file['url'], 'title' => $file['title'] ?? $title, 'size' => $file['size'] ?? '']) }}"
                           class="shrink-0 rounded-md bg-indigo-600 hover:bg-indigo-500 px-5 py-2 text-sm font-semibold text-center">
                            Obtenir les liens
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    @if (count($recommended) > 0)
        <section>
            <h2 class="text-lg font-semibold mb-4">Recommandations</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-5">
                @foreach ($recommended as $rec)
                    <a href="{{ route('movies.show', ['url' => $rec['url'], 'title' => $rec['title'] ?? '']) }}"
                       class="group block rounded-lg overflow-hidden bg-slate-900 border border-slate-800 hover:border-indigo-500 transition">
                        <div class="aspect-[2/3] bg-slate-800 overflow-hidden">
                            @if (!empty($rec['cover_photo']))
                                <img src="{{ $rec['cover_photo'] }}" alt="{{ $rec['title'] ?? '' }}" loading="lazy"
                                     class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                     onerror="this.style.display='none'">
                            @endif
                        </div>
                        <div class="p-3">
                            <h3 class="text-sm font-semibold leading-tight line-clamp-2">{{ $rec['title'] ?? '' }}</h3>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection
