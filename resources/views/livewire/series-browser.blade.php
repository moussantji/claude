<div class="space-y-8">
    <div class="flex flex-col md:flex-row gap-3 md:items-center">
        <div class="relative flex-1">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500">🔍</span>
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('Search series…') }}"
                   class="w-full rounded-xl bg-slate-900/60 border border-white/10 pl-10 pr-4 py-3 text-white placeholder-slate-500 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 outline-none">
        </div>
        <select wire:model.live="sort"
                class="rounded-xl bg-slate-900/60 border border-white/10 px-4 py-3 text-white focus:border-brand-500 outline-none">
            <option value="latest">{{ __('Latest') }}</option>
            <option value="rating">{{ __('Top rated') }}</option>
            <option value="year">{{ __('Newest year') }}</option>
        </select>
        @if ($search || $genre || $sort !== 'latest')
            <button wire:click="clearFilters" class="rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 px-4 py-3 text-sm text-slate-300 whitespace-nowrap">{{ __('Clear filters') }}</button>
        @endif
    </div>

    @if ($genres->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            @foreach ($genres as $g)
                <button wire:click="setGenre('{{ $g }}')"
                        class="rounded-full px-4 py-1.5 text-sm border {{ $genre === $g ? 'bg-brand-600 border-brand-600 text-white' : 'bg-white/5 border-white/10 text-slate-300 hover:bg-white/10' }}">
                    {{ $g }}
                </button>
            @endforeach
        </div>
    @endif

    <div wire:loading.delay class="text-center text-sm text-slate-400">{{ __('Loading…') }}</div>

    <div wire:loading.remove>
        @if ($series->isEmpty())
            <div class="text-center py-16">
                <div class="text-4xl mb-3">📺</div>
                <p class="text-slate-300 font-medium">{{ __('No series match your search.') }}</p>
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                @foreach ($series as $serie)
                    @include('partials.poster-card', [
                        'url' => route('series.show', $serie),
                        'title' => $serie->title,
                        'poster' => $serie->poster_url,
                        'meta' => trim(($serie->start_year ? $serie->start_year : '') . ($serie->genre ? ' · '.$serie->genre : ''), ' ·'),
                        'rating' => $serie->rating,
                        'badge' => $serie->seasons . ' ' . __('seasons'),
                    ])
                @endforeach
            </div>
            <div class="mt-8">{{ $series->links() }}</div>
        @endif
    </div>
</div>
