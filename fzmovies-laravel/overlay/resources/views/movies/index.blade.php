@extends('layouts.app')

@section('title', 'Rechercher un film')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-bold mb-1">Rechercher un film</h1>
        <p class="text-slate-400 text-sm">Recherche par nom, réalisateur ou acteur.</p>
    </div>

    <form method="GET" action="{{ route('movies.index') }}" class="flex flex-col sm:flex-row gap-3 mb-8">
        <input type="text" name="q" value="{{ $q }}" placeholder="Ex: Fast X"
               class="flex-1 rounded-md bg-slate-900 border border-slate-700 px-4 py-2.5 focus:outline-none focus:border-indigo-500"
               autofocus>

        <select name="searchby" class="rounded-md bg-slate-900 border border-slate-700 px-3 py-2.5">
            @foreach (['Name' => 'Nom', 'Director' => 'Réalisateur', 'Starcast' => 'Acteur'] as $val => $label)
                <option value="{{ $val }}" @selected($searchby === $val)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="category" class="rounded-md bg-slate-900 border border-slate-700 px-3 py-2.5">
            @foreach (['All', 'Hollywood', 'Bollywood', 'DHollywood'] as $cat)
                <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
            @endforeach
        </select>

        <button type="submit" class="rounded-md bg-indigo-600 hover:bg-indigo-500 px-6 py-2.5 font-semibold">
            Rechercher
        </button>
    </form>

    @if ($error)
        <div class="rounded-md bg-red-950 border border-red-800 text-red-200 px-4 py-3 mb-6 text-sm">
            {{ $error }}
        </div>
    @endif

    @if ($q !== '')
        <h2 class="text-lg font-semibold mb-4">Résultats pour « {{ $q }} »</h2>
        @include('partials.movie-grid', ['movies' => $movies, 'nextPage' => $nextPage ?? null])
    @endif
@endsection
