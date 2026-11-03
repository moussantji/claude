@extends('layouts.app')

@section('title', 'Recherche — French Stream')

@section('content')
    <h1 class="text-2xl font-bold mb-6">
        @if ($q !== '') Résultats pour « {{ $q }} » @else Recherche @endif
    </h1>

    @if ($error)
        <div class="rounded-md bg-red-950 border border-red-800 text-red-200 px-4 py-3 mb-6 text-sm">
            {{ $error }}
        </div>
    @endif

    @if ($q !== '')
        @include('partials.grid', ['items' => $items, 'next' => $next])
    @else
        <p class="text-neutral-400">Tape un titre ou un acteur dans la barre de recherche.</p>
    @endif
@endsection
