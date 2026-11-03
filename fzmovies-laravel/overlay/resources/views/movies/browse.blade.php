@extends('layouts.app')

@section('title', 'Parcourir')

@php
    $filterLabels = [
        'recently_released' => 'Récemment sortis',
        'recently_published' => 'Récemment ajoutés',
        'most_downloaded' => 'Les plus téléchargés',
        'imdb_top250' => 'IMDB Top 250',
        'genre' => 'Par genre',
        'release_year' => 'Par année',
    ];
    $genres = ['Action','Adventure','Animation','Biography','Comedy','Crime','Documentary','Drama','Family','Fantasy','Horror','Music','Mystery','Romance','Sci-Fi','Sport','Thriller','War','Western'];
@endphp

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-bold mb-4">Parcourir</h1>

        <form method="GET" action="{{ route('movies.browse') }}" class="flex flex-wrap gap-3">
            <select name="filter" class="rounded-md bg-slate-900 border border-slate-700 px-3 py-2.5">
                @foreach ($filterLabels as $val => $label)
                    <option value="{{ $val }}" @selected($filter === $val)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="category" class="rounded-md bg-slate-900 border border-slate-700 px-3 py-2.5">
                @foreach (['Hollywood', 'Bollywood'] as $cat)
                    <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
                @endforeach
            </select>

            <select name="genre" class="rounded-md bg-slate-900 border border-slate-700 px-3 py-2.5">
                @foreach ($genres as $g)
                    <option value="{{ $g }}" @selected($genre === $g)>{{ $g }}</option>
                @endforeach
            </select>

            <button type="submit" class="rounded-md bg-indigo-600 hover:bg-indigo-500 px-6 py-2.5 font-semibold">
                Afficher
            </button>
        </form>
    </div>

    @if ($error)
        <div class="rounded-md bg-red-950 border border-red-800 text-red-200 px-4 py-3 mb-6 text-sm">
            {{ $error }}
        </div>
    @endif

    <h2 class="text-lg font-semibold mb-4">{{ $filterLabels[$filter] ?? $filter }}</h2>
    @include('partials.movie-grid', ['movies' => $movies, 'nextPage' => $nextPage ?? null])
@endsection
