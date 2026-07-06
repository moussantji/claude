{{-- Grid of movie cards with infinite scroll.
     Expects $movies (array) and optionally $nextPage (?string). --}}
@if (count($movies) === 0)
    <p class="text-slate-400">Aucun film à afficher.</p>
@else
    <div id="movie-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-5">
        @include('partials.movie-cards', ['movies' => $movies])
    </div>

    {{-- Sentinel observed for infinite scroll --}}
    <div id="scroll-sentinel" data-next="{{ $nextPage ?? '' }}" class="h-12"></div>
    <p id="scroll-status" class="text-center text-sm text-slate-500 mt-2"></p>

    <script>
        (function () {
            const sentinel = document.getElementById('scroll-sentinel');
            const grid = document.getElementById('movie-grid');
            const status = document.getElementById('scroll-status');
            if (!sentinel || !grid) return;

            let nextUrl = sentinel.dataset.next || '';
            let loading = false;

            async function loadMore() {
                if (loading || !nextUrl) return;
                loading = true;
                status.textContent = 'Chargement…';
                try {
                    const res = await fetch(@json(route('movies.more')) + '?next=' + encodeURIComponent(nextUrl), {
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await res.json();
                    if (data.html) grid.insertAdjacentHTML('beforeend', data.html);
                    nextUrl = data.next || '';
                    sentinel.dataset.next = nextUrl;
                    status.textContent = nextUrl ? '' : 'Fin des résultats.';
                    if (!nextUrl) observer.unobserve(sentinel);
                } catch (e) {
                    status.textContent = 'Impossible de charger la suite.';
                } finally {
                    loading = false;
                }
            }

            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) loadMore();
            }, { rootMargin: '400px' });

            if (nextUrl) observer.observe(sentinel);
        })();
    </script>
@endif
