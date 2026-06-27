@php($qualityColors = [
    '4K' => 'bg-fuchsia-500/20 text-fuchsia-300',
    'FHD' => 'bg-emerald-500/20 text-emerald-300',
    'HD' => 'bg-brand-500/20 text-brand-300',
    'SD' => 'bg-slate-500/20 text-slate-300',
])
<div class="group rounded-xl border border-white/10 bg-slate-900/40 p-4 hover:border-brand-500/40 hover:bg-slate-900/70 transition">
    <div class="aspect-square rounded-lg bg-slate-950 flex items-center justify-center overflow-hidden mb-3">
        @if ($channel->logo_url)
            <img src="{{ $channel->logo_url }}" alt="{{ $channel->name }}" class="max-h-full max-w-full object-contain p-2" loading="lazy"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <div class="hidden h-full w-full items-center justify-center text-2xl font-black text-slate-600">{{ Str::substr($channel->name, 0, 2) }}</div>
        @else
            <div class="flex h-full w-full items-center justify-center text-2xl font-black text-slate-600">{{ Str::substr($channel->name, 0, 2) }}</div>
        @endif
    </div>
    <div class="flex items-center justify-between gap-2">
        <p class="text-sm font-medium text-white truncate">{{ $channel->name }}</p>
        <span class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded {{ $qualityColors[$channel->quality] ?? 'bg-slate-500/20 text-slate-300' }}">{{ $channel->quality }}</span>
    </div>
    @if ($channel->category)
        <p class="text-xs text-slate-500 mt-0.5">{{ $channel->category->name }}</p>
    @endif
</div>
