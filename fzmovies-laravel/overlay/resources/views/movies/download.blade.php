@extends('layouts.app')

@section('title', 'Liens de téléchargement')

@section('content')
    <a href="{{ url()->previous() }}" class="text-sm text-indigo-400 hover:text-indigo-300">&larr; Retour</a>

    <h1 class="text-2xl font-bold mt-3 mb-6">Liens de téléchargement</h1>

    @if ($error)
        <div class="rounded-md bg-red-950 border border-red-800 text-red-200 px-4 py-3 mb-6 text-sm">
            {{ $error }}
        </div>
    @endif

    @if ($download)
        <div class="rounded-lg bg-slate-900 border border-slate-800 p-5 mb-6">
            <p class="text-lg font-semibold break-words">{{ $download['filename'] ?? $title }}</p>
            <p class="text-sm text-slate-400 mt-1">Taille : {{ $download['size'] ?? '?' }}</p>
            @if (!empty($download['info']))
                <p class="text-xs text-slate-500 mt-2">{{ $download['info'] }}</p>
            @endif
        </div>

        <h2 class="text-lg font-semibold mb-3">Miroirs</h2>
        @if (empty($download['links']))
            <p class="text-slate-400">Aucun lien disponible.</p>
        @else
            <div class="space-y-3">
                @foreach ($download['links'] as $i => $link)
                    <a href="{{ $link['url'] }}" target="_blank" rel="noopener"
                       class="flex items-center justify-between rounded-lg bg-slate-900 border border-slate-800 hover:border-emerald-500 px-4 py-3 transition">
                        <span class="font-medium">Miroir #{{ $i + 1 }}</span>
                        <span class="text-xs text-slate-400">{{ $link['connections'] ?? '?' }} connexions &middot; Télécharger &rarr;</span>
                    </a>
                @endforeach
            </div>
        @endif

        <p class="text-xs text-slate-500 mt-6">
            Les liens ouvrent la source d'origine dans un nouvel onglet. Ce site ne stocke aucun fichier.
        </p>
    @endif
@endsection
