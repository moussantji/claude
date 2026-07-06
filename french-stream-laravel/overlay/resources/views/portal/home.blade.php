@extends('layouts.app')

@section('title', 'French Stream — Accueil')

@section('content')
    @if ($error)
        <div class="rounded-md bg-red-950 border border-red-800 text-red-200 px-4 py-3 mb-6 text-sm">
            {{ $error }}
        </div>
    @endif

    @forelse ($sections as $section)
        <section class="mb-10">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-bold">{{ $section['title'] }}</h2>
                @if (!empty($section['link']))
                    <a href="{{ $section['link'] }}" class="text-xs text-orange-400 hover:text-orange-300">Voir plus →</a>
                @endif
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                @include('partials.cards', ['items' => $section['items']])
            </div>
        </section>
    @empty
        @unless ($error)
            <p class="text-neutral-400">Rien à afficher pour le moment.</p>
        @endunless
    @endforelse
@endsection
