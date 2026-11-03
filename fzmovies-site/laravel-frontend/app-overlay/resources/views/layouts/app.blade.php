<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'FZ Movies')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-slate-950 text-slate-100">
    <header class="border-b border-slate-800 bg-slate-900/60 sticky top-0 backdrop-blur z-10">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center gap-6">
            <a href="{{ route('movies.index') }}" class="text-xl font-bold text-indigo-400">
                FZ<span class="text-slate-100">Movies</span>
            </a>
            <nav class="flex items-center gap-4 text-sm text-slate-300">
                <a href="{{ route('movies.index') }}" class="hover:text-white">Recherche</a>
                <a href="{{ route('movies.browse', ['filter' => 'recently_released']) }}" class="hover:text-white">Récents</a>
                <a href="{{ route('movies.browse', ['filter' => 'imdb_top250']) }}" class="hover:text-white">IMDB Top 250</a>
                <a href="{{ route('movies.browse', ['filter' => 'most_downloaded']) }}" class="hover:text-white">Populaires</a>
            </nav>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-8">
        @yield('content')
    </main>

    <footer class="border-t border-slate-800 mt-16">
        <div class="max-w-6xl mx-auto px-4 py-6 text-xs text-slate-500">
            Projet de démonstration &mdash; frontend Laravel + backend FastAPI (fzmovies-api).
            Non affilié à fzmovies.net. Pour usage personnel/éducatif uniquement.
        </div>
    </footer>
</body>
</html>
