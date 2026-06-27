@php
    $waNumber = preg_replace('/\D/', '', config('streamwave.whatsapp'));
    $waMessage = rawurlencode(__('Hi! I would like more information about StreamWave IPTV.'));
    $waLink = "https://wa.me/{$waNumber}?text={$waMessage}";
@endphp

<a href="{{ $waLink }}" target="_blank" rel="noopener"
   class="fixed bottom-5 right-5 z-50 group flex items-center gap-3"
   aria-label="{{ __('Contact us on WhatsApp') }}">
    <span class="hidden group-hover:block bg-slate-900 text-white text-sm px-3 py-1.5 rounded-lg border border-white/10 shadow-lg whitespace-nowrap">
        {{ __('Chat on WhatsApp') }}
    </span>
    <span class="float-pulse flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-2xl shadow-emerald-900/40 hover:bg-[#1ebe5d] transition">
        <svg viewBox="0 0 32 32" class="h-7 w-7 fill-current" aria-hidden="true">
            <path d="M16.003 3.2c-7.06 0-12.8 5.74-12.8 12.8 0 2.26.6 4.46 1.73 6.4L3.2 28.8l6.57-1.72a12.74 12.74 0 0 0 6.23 1.6h.01c7.06 0 12.8-5.74 12.8-12.8s-5.75-12.68-12.81-12.68zm0 23.04h-.01a10.6 10.6 0 0 1-5.4-1.48l-.39-.23-3.9 1.02 1.04-3.8-.25-.4a10.58 10.58 0 0 1-1.62-5.64c0-5.86 4.77-10.63 10.64-10.63 2.84 0 5.51 1.11 7.52 3.12a10.56 10.56 0 0 1 3.11 7.52c0 5.87-4.77 10.64-10.64 10.64zm5.84-7.97c-.32-.16-1.89-.93-2.18-1.04-.29-.11-.5-.16-.71.16-.21.32-.82 1.04-1 1.25-.18.21-.37.24-.69.08-.32-.16-1.35-.5-2.57-1.59-.95-.85-1.59-1.9-1.78-2.22-.18-.32-.02-.49.14-.65.14-.14.32-.37.48-.55.16-.18.21-.32.32-.53.11-.21.05-.4-.03-.56-.08-.16-.71-1.72-.98-2.35-.26-.62-.52-.54-.71-.55l-.61-.01c-.21 0-.55.08-.84.4-.29.32-1.1 1.08-1.1 2.64s1.13 3.06 1.29 3.27c.16.21 2.23 3.4 5.4 4.77.75.32 1.34.52 1.8.66.76.24 1.45.21 1.99.13.61-.09 1.89-.77 2.16-1.52.27-.74.27-1.38.19-1.51-.08-.13-.29-.21-.61-.37z"/>
        </svg>
    </span>
</a>
