{{-- A single movie/series poster card. Expects $item. --}}
<a href="{{ route('portal.detail', array_filter([
        'id' => $item['id'],
        'title' => $item['title'] ?? null,
        'poster' => $item['poster'] ?? null,
        'trailer' => $item['trailer'] ?? null,
        'synopsis' => $item['synopsis'] ?? null,
        'version' => $item['version'] ?? null,
        'quality' => $item['quality'] ?? null,
   ])) }}"
   class="group relative block rounded-lg overflow-hidden bg-neutral-900 border border-neutral-800 hover:border-orange-500 transition">
    <div class="aspect-[2/3] bg-neutral-800 overflow-hidden">
        @if (!empty($item['poster']))
            <img src="{{ $item['poster'] }}" alt="{{ $item['title'] ?? '' }}" loading="lazy"
                 class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                 onerror="this.style.display='none'">
        @endif
    </div>

    <div class="absolute top-2 left-2 flex flex-col gap-1">
        @if (!empty($item['quality']))
            <span class="text-[10px] font-bold bg-orange-600 text-white px-1.5 py-0.5 rounded">{{ $item['quality'] }}</span>
        @endif
        @if (!empty($item['version']))
            <span class="text-[10px] font-semibold bg-black/70 text-neutral-100 px-1.5 py-0.5 rounded">{{ $item['version'] }}</span>
        @endif
    </div>

    @if (!empty($item['episodes']))
        <span class="absolute bottom-14 right-2 text-[10px] font-semibold bg-black/75 text-white px-1.5 py-0.5 rounded">{{ $item['episodes'] }}</span>
    @endif

    <div class="p-2.5">
        <h3 class="text-sm font-semibold leading-tight line-clamp-2">{{ $item['title'] ?? 'Sans titre' }}</h3>
    </div>
</a>
