@php($featured = $package->is_featured)
<div class="relative rounded-2xl border {{ $featured ? 'border-brand-500 bg-gradient-to-b from-brand-600/15 to-slate-900/40' : 'border-white/10 bg-slate-900/40' }} p-6 flex flex-col">
    @if ($featured)
        <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-brand-500 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">{{ __('Most Popular') }}</span>
    @endif

    <h3 class="text-lg font-bold text-white">{{ $package->name }}</h3>
    <p class="text-sm text-slate-400 mt-1 min-h-[2.5rem]">{{ $package->description }}</p>

    <div class="mt-4 flex items-baseline gap-1">
        <span class="text-4xl font-black text-white">${{ number_format($package->price, 2) }}</span>
        <span class="text-slate-400 text-sm">/ {{ __(':days days', ['days' => $package->duration_days]) }}</span>
    </div>

    <ul class="mt-6 space-y-3 text-sm flex-1">
        <li class="flex items-center gap-2 text-slate-300">
            <span class="text-emerald-400">✓</span> {{ __(':count+ live channels', ['count' => number_format($package->channel_count)]) }}
        </li>
        <li class="flex items-center gap-2 text-slate-300">
            <span class="text-emerald-400">✓</span> {{ __(':n simultaneous connections', ['n' => $package->max_connections]) }}
        </li>
        @foreach (($package->features ?? []) as $feature)
            <li class="flex items-center gap-2 text-slate-300">
                <span class="text-emerald-400">✓</span> {{ $feature }}
            </li>
        @endforeach
    </ul>

    <a href="{{ route('subscribe.show', $package) }}"
       class="mt-6 block text-center rounded-lg px-4 py-2.5 font-semibold transition {{ $featured ? 'bg-brand-600 hover:bg-brand-500 text-white shadow-lg shadow-brand-600/30' : 'bg-white/5 hover:bg-white/10 text-white border border-white/10' }}">
        {{ __('Choose :plan', ['plan' => $package->name]) }}
    </a>
</div>
