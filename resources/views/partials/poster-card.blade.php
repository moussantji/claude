@php
    // Expects: $url (detail link), $title, $poster, $meta (e.g. year), $rating, $badge (e.g. quality/seasons)
@endphp
<a href="{{ $url }}" class="group block">
    <div class="relative aspect-[2/3] rounded-xl overflow-hidden bg-slate-900 border border-white/10 group-hover:border-brand-500/60 transition">
        @if (!empty($poster))
            <img src="{{ $poster }}" alt="{{ $title }}" loading="lazy"
                 class="h-full w-full object-cover group-hover:scale-105 transition duration-500"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <div class="hidden h-full w-full items-center justify-center text-center px-2 text-sm font-bold text-slate-600">{{ $title }}</div>
        @else
            <div class="flex h-full w-full items-center justify-center text-center px-2 text-sm font-bold text-slate-600">{{ $title }}</div>
        @endif

        @if (!empty($badge))
            <span class="absolute top-2 right-2 text-[10px] font-bold px-1.5 py-0.5 rounded bg-black/60 backdrop-blur text-white">{{ $badge }}</span>
        @endif
        @if (!empty($rating) && $rating > 0)
            <span class="absolute top-2 left-2 text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-500/90 text-black">★ {{ number_format($rating, 1) }}</span>
        @endif

        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 to-transparent p-3 pt-8 opacity-0 group-hover:opacity-100 transition">
            <span class="inline-flex items-center gap-1 text-xs font-semibold text-white">▶ {{ __('Watch now') }}</span>
        </div>
    </div>
    <p class="mt-2 text-sm font-medium text-white truncate">{{ $title }}</p>
    @if (!empty($meta))
        <p class="text-xs text-slate-500">{{ $meta }}</p>
    @endif
</a>
