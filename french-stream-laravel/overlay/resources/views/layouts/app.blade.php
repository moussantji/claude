<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'French Stream — Portail')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-neutral-950 text-neutral-100">
    <header class="border-b border-neutral-800 bg-neutral-900/70 sticky top-0 backdrop-blur z-20">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="{{ route('portal.home') }}" class="text-xl font-black tracking-tight">
                <span class="text-orange-500">FRENCH</span><span>STREAM</span>
            </a>

            <nav class="flex items-center gap-1 text-sm text-neutral-300 overflow-x-auto">
                @foreach (\App\Http\Controllers\PortalController::MENU as $key => $m)
                    <a href="{{ route('portal.browse', ['c' => $key]) }}"
                       class="px-3 py-1.5 rounded hover:bg-neutral-800 hover:text-white whitespace-nowrap {{ ($activeKey ?? '') === $key ? 'text-orange-400' : '' }}">
                        {{ $m['title'] }}
                    </a>
                @endforeach
            </nav>

            <form method="GET" action="{{ route('portal.search') }}" class="ml-auto flex items-center gap-2">
                <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Titre, acteur…"
                       class="rounded-md bg-neutral-800 border border-neutral-700 px-3 py-1.5 text-sm w-44 sm:w-64 focus:outline-none focus:border-orange-500">
                <button class="rounded-md bg-orange-600 hover:bg-orange-500 px-3 py-1.5 text-sm font-semibold">
                    <span class="fa fa-search">OK</span>
                </button>
            </form>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-6">
        @yield('content')
    </main>

    <footer class="border-t border-neutral-800 mt-16">
        <div class="max-w-7xl mx-auto px-4 py-6 text-xs text-neutral-500">
            Portail de démonstration (Laravel + scraping PHP). Non affilié à french-stream.
            Les contenus sont hébergés par des tiers. Usage personnel/éducatif uniquement.
        </div>
    </footer>
</body>
</html>
