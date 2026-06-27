@php
    $items = [
        ['route' => 'home', 'pattern' => 'home', 'label' => __('Home'), 'icon' => 'M3 12l9-9 9 9M5 10v10h14V10'],
        ['route' => 'movies.index', 'pattern' => 'movies.*', 'label' => __('Movies'), 'icon' => 'M4 4h16v16H4zM4 9h16M9 4v16'],
        ['route' => 'series.index', 'pattern' => 'series.*', 'label' => __('Series'), 'icon' => 'M4 7h16v11H4zM8 3l4 4 4-4'],
        ['route' => 'channels.index', 'pattern' => 'channels.*', 'label' => __('Channels'), 'icon' => 'M3 8h18v12H3zM7 3l5 5 5-5'],
    ];
    $account = auth()->check()
        ? ['route' => 'dashboard', 'pattern' => 'dashboard', 'label' => __('Account')]
        : ['route' => 'login', 'pattern' => 'login', 'label' => __('Log in')];
@endphp
<nav class="md:hidden fixed bottom-0 inset-x-0 z-50 bg-slate-950/95 backdrop-blur border-t border-white/10 pb-[env(safe-area-inset-bottom)]">
    <div class="grid grid-cols-5">
        @foreach ($items as $item)
            @php($active = request()->routeIs($item['pattern']))
            <a href="{{ route($item['route']) }}" class="flex flex-col items-center gap-1 py-2.5 {{ $active ? 'text-brand-400' : 'text-slate-400' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                <span class="text-[10px] font-medium">{{ $item['label'] }}</span>
            </a>
        @endforeach
        <a href="{{ route($account['route']) }}" class="flex flex-col items-center gap-1 py-2.5 {{ request()->routeIs($account['pattern']) ? 'text-brand-400' : 'text-slate-400' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M5 20a7 7 0 0114 0M12 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
            <span class="text-[10px] font-medium">{{ $account['label'] }}</span>
        </a>
    </div>
</nav>
