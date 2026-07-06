@extends('layouts.app')

@section('title', 'Lecture — ' . $title)

@section('content')
    <a href="{{ url()->previous() }}" class="text-sm text-indigo-400 hover:text-indigo-300">&larr; Retour</a>

    <h1 class="text-2xl font-bold mt-3 mb-6">{{ $filename ?? $title }}</h1>

    @if ($error)
        <div class="rounded-md bg-red-950 border border-red-800 text-red-200 px-4 py-3 mb-6 text-sm">
            Lecture impossible : {{ $error }}
        </div>
        <p class="text-slate-400 text-sm">
            Essaie plutôt d'<a href="{{ url()->previous() }}" class="text-indigo-400 underline">obtenir les liens de téléchargement</a>.
        </p>
    @elseif ($token)
        <div class="rounded-lg overflow-hidden bg-black border border-slate-800">
            <video
                controls
                autoplay
                playsinline
                preload="metadata"
                class="w-full max-h-[75vh] bg-black"
                src="{{ route('movies.stream', ['token' => $token]) }}">
                Ton navigateur ne peut pas lire cette vidéo.
            </video>
        </div>

        <div class="mt-4 flex flex-wrap gap-3 text-sm">
            <a href="{{ route('movies.stream', ['token' => $token]) }}" download
               class="rounded-md bg-emerald-600 hover:bg-emerald-500 px-5 py-2 font-semibold">
                ⬇ Télécharger le fichier
            </a>
        </div>

        <p class="text-xs text-slate-500 mt-6 leading-relaxed">
            Lecture en streaming via un relais (proxy) du serveur.
            Les fichiers <strong>.mp4</strong> se lisent dans le navigateur ; les formats
            <strong>.mkv/.avi</strong> ne sont pas supportés nativement — utilise alors le
            bouton de téléchargement puis un lecteur comme VLC.
        </p>
    @endif
@endsection
