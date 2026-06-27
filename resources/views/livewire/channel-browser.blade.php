<div class="space-y-8">
    {{-- Search + quality filter --}}
    <div class="flex flex-col md:flex-row gap-3 md:items-center">
        <div class="relative flex-1">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500">🔍</span>
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('Search channels…') }}"
                   class="w-full rounded-xl bg-slate-900/60 border border-white/10 pl-10 pr-4 py-3 text-white placeholder-slate-500 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 outline-none">
        </div>

        <select wire:model.live="quality"
                class="rounded-xl bg-slate-900/60 border border-white/10 px-4 py-3 text-white focus:border-brand-500 outline-none">
            <option value="">{{ __('All qualities') }}</option>
            @foreach (['SD', 'HD', 'FHD', '4K'] as $q)
                <option value="{{ $q }}">{{ $q }}</option>
            @endforeach
        </select>

        @if ($search || $categoryId || $quality)
            <button wire:click="clearFilters"
                    class="rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 px-4 py-3 text-sm text-slate-300 whitespace-nowrap">
                {{ __('Clear filters') }}
            </button>
        @endif
    </div>

    {{-- Category chips --}}
    <div class="flex flex-wrap gap-2">
        <button wire:click="selectCategory(null)"
                class="rounded-full px-4 py-1.5 text-sm border {{ is_null($categoryId) ? 'bg-brand-600 border-brand-600 text-white' : 'bg-white/5 border-white/10 text-slate-300 hover:bg-white/10' }}">
            {{ __('All') }}
        </button>
        @foreach ($categories as $category)
            <button wire:click="selectCategory({{ $category->id }})"
                    class="rounded-full px-4 py-1.5 text-sm border {{ $categoryId === $category->id ? 'bg-brand-600 border-brand-600 text-white' : 'bg-white/5 border-white/10 text-slate-300 hover:bg-white/10' }}">
                {{ $category->icon }} {{ $category->name }}
                <span class="opacity-60">({{ $category->channels_count }})</span>
            </button>
        @endforeach
    </div>

    {{-- Loading shimmer --}}
    <div wire:loading.delay class="text-center text-sm text-slate-400">
        <span class="inline-flex items-center gap-2">
            <span class="h-3 w-3 rounded-full bg-brand-500 animate-ping"></span>
            {{ __('Loading…') }}
        </span>
    </div>

    {{-- Results --}}
    <div wire:loading.remove>
        @if ($channels->isEmpty())
            <div class="text-center py-16">
                <div class="text-4xl mb-3">🛰️</div>
                <p class="text-slate-300 font-medium">{{ __('No channels match your search.') }}</p>
                <button wire:click="clearFilters" class="mt-4 text-brand-400 hover:text-brand-300 text-sm font-medium">{{ __('Reset filters') }}</button>
            </div>
        @else
            <p class="text-sm text-slate-500 mb-4">{{ __(':count channels found', ['count' => $channels->total()]) }}</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                @foreach ($channels as $channel)
                    @include('partials.channel-card', ['channel' => $channel])
                @endforeach
            </div>

            <div class="mt-8">
                {{ $channels->links() }}
            </div>
        @endif
    </div>
</div>
