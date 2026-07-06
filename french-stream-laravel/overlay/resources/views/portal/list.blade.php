@extends('layouts.app')

@section('title', $title . ' — French Stream')

@section('content')
    <h1 class="text-2xl font-bold mb-6">{{ $title }}</h1>

    @if ($error)
        <div class="rounded-md bg-red-950 border border-red-800 text-red-200 px-4 py-3 mb-6 text-sm">
            {{ $error }}
        </div>
    @endif

    @include('partials.grid', ['items' => $items, 'next' => $next])
@endsection
