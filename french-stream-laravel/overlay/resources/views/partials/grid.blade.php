{{-- Grid with infinite scroll. Expects $items (array) and $next (?string absolute URL). --}}
@if (count($items) === 0)
    <p class="text-neutral-400">Aucun élément à afficher.</p>
@else
    <div id="grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        @include('partials.cards', ['items' => $items])
    </div>

    <div id="sentinel" data-next="{{ $next ?? '' }}" class="h-12"></div>
    <p id="scroll-status" class="text-center text-sm text-neutral-500 mt-2"></p>

    <script>
        (function () {
            const sentinel = document.getElementById('sentinel');
            const grid = document.getElementById('grid');
            const status = document.getElementById('scroll-status');
            if (!sentinel || !grid) return;

            let nextUrl = sentinel.dataset.next || '';
            let loading = false;

            async function loadMore() {
                if (loading || !nextUrl) return;
                loading = true;
                status.textContent = 'Chargement…';
                try {
                    const res = await fetch(@json(route('portal.more')) + '?next=' + encodeURIComponent(nextUrl), {
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await res.json();
                    if (data.html) grid.insertAdjacentHTML('beforeend', data.html);
                    nextUrl = data.next || '';
                    sentinel.dataset.next = nextUrl;
                    status.textContent = nextUrl ? '' : 'Fin de la liste.';
                    if (!nextUrl) observer.unobserve(sentinel);
                } catch (e) {
                    status.textContent = 'Impossible de charger la suite.';
                } finally {
                    loading = false;
                }
            }

            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) loadMore();
            }, { rootMargin: '500px' });

            if (nextUrl) observer.observe(sentinel);
        })();
    </script>
@endif
