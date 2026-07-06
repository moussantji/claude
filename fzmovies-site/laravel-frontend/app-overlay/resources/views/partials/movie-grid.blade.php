{{-- Reusable grid of movie cards. Expects $movies (array of movie dicts). --}}
@if (count($movies) === 0)
    <p class="text-slate-400">Aucun film à afficher.</p>
@else
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-5">
        @foreach ($movies as $movie)
            <a href="{{ route('movies.show', ['url' => $movie['url'], 'title' => $movie['title'] ?? '', 'year' => $movie['year'] ?? 0]) }}"
               class="group block rounded-lg overflow-hidden bg-slate-900 border border-slate-800 hover:border-indigo-500 transition">
                <div class="aspect-[2/3] bg-slate-800 overflow-hidden">
                    @if (!empty($movie['cover_photo']))
                        <img src="{{ $movie['cover_photo'] }}" alt="{{ $movie['title'] ?? '' }}"
                             loading="lazy"
                             class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                             onerror="this.style.display='none'">
                    @endif
                </div>
                <div class="p-3">
                    <h3 class="text-sm font-semibold leading-tight line-clamp-2">{{ $movie['title'] ?? 'Sans titre' }}</h3>
                    @if (!empty($movie['year']))
                        <p class="text-xs text-slate-400 mt-1">{{ $movie['year'] }}</p>
                    @endif
                </div>
            </a>
        @endforeach
    </div>
@endif
