@php
    $waNumber = preg_replace('/\D/', '', config('streamwave.whatsapp'));
    $waLink = "https://wa.me/{$waNumber}";
@endphp
<footer class="border-t border-white/10 bg-slate-950">
    <div class="max-w-7xl mx-auto px-4 py-12 grid md:grid-cols-3 gap-8">
        <div>
            <span class="text-brand-400 text-xl font-black tracking-tight">◈ {{ config('app.name') }}</span>
            <p class="text-sm text-slate-500 mt-3 max-w-xs">{{ __('Premium live TV, sports, movies and series streamed in HD & 4K to any device.') }}</p>
        </div>

        <div>
            <h4 class="text-sm font-semibold text-white uppercase tracking-wide">{{ __('Explore') }}</h4>
            <ul class="mt-3 space-y-2 text-sm text-slate-400">
                <li><a href="{{ route('packages.index') }}" class="hover:text-white">{{ __('Pricing') }}</a></li>
                <li><a href="{{ route('channels.index') }}" class="hover:text-white">{{ __('Channels') }}</a></li>
                <li><a href="{{ route('register') }}" class="hover:text-white">{{ __('Get Started') }}</a></li>
            </ul>
        </div>

        <div>
            <h4 class="text-sm font-semibold text-white uppercase tracking-wide">{{ __('Contact') }}</h4>
            <ul class="mt-3 space-y-2 text-sm text-slate-400">
                <li>
                    <a href="{{ $waLink }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 hover:text-white">
                        <span class="text-[#25D366]">●</span> WhatsApp: +{{ $waNumber }}
                    </a>
                </li>
                <li><a href="mailto:{{ config('streamwave.support_email') }}" class="hover:text-white">{{ config('streamwave.support_email') }}</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="max-w-7xl mx-auto px-4 py-5 text-center text-sm text-slate-500">
            &copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('For demonstration purposes only.') }}
        </div>
    </div>
</footer>
