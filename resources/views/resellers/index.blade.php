@extends('layouts.app')

@section('title', __('Reseller Program'))

@php
    $waNumber = preg_replace('/\D/', '', config('streamwave.whatsapp'));
    $waLink = 'https://wa.me/' . $waNumber . '?text=' . rawurlencode(__('Hello! I would like to become a StreamWave reseller.'));
@endphp

@section('content')
<section class="relative overflow-hidden hero-grid">
    <div class="absolute inset-0 bg-cover bg-center opacity-20" style="background-image:url('{{ config('streamwave.images.cta') }}')"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-brand-600/20 via-slate-950/60 to-slate-950"></div>
    <div class="relative max-w-7xl mx-auto px-4 py-20 text-center" data-animate>
        <span class="inline-block rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-sm text-slate-300">{{ __('Reseller Program') }}</span>
        <h1 class="mt-5 text-4xl md:text-6xl font-black text-white">{{ __('Become a reseller') }}</h1>
        <p class="mt-5 max-w-2xl mx-auto text-lg text-slate-300">{{ __('Buy credits in bulk, create subscriptions for your own clients and grow your business with the best margins.') }}</p>
        <a href="{{ $waLink }}" target="_blank" rel="noopener" class="mt-8 inline-flex items-center gap-2 rounded-lg bg-[#25D366] hover:bg-[#1ebe5d] px-8 py-3.5 font-semibold text-white">
            {{ __('Become a reseller on WhatsApp') }}
        </a>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 py-16">
    <div class="grid md:grid-cols-3 gap-6 mb-16" data-animate>
        @php($benefits = [
            ['💰', __('Wholesale pricing'), __('The more credits you buy, the lower your cost per line.')],
            ['⚡', __('Instant panel'), __('Create, renew and manage your clients from a dedicated reseller panel.')],
            ['🛟', __('Priority support'), __('A dedicated line on WhatsApp for resellers, 24/7.')],
        ])
        @foreach ($benefits as [$icon, $title, $desc])
            <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-6">
                <div class="text-3xl">{{ $icon }}</div>
                <h3 class="mt-4 text-lg font-semibold text-white">{{ $title }}</h3>
                <p class="mt-2 text-sm text-slate-400">{{ $desc }}</p>
            </div>
        @endforeach
    </div>

    <div class="text-center max-w-2xl mx-auto mb-10" data-animate>
        <h2 class="text-3xl font-bold text-white">{{ __('Reseller credit packs') }}</h2>
        <p class="mt-3 text-slate-400">{{ __('1 credit = 1 month of access for one client. Credits never expire.') }}</p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6" data-animate>
        @foreach ($tiers as $tier)
            <div class="relative rounded-2xl border {{ ($tier['popular'] ?? false) ? 'border-brand-500 bg-gradient-to-b from-brand-600/15 to-slate-900/40' : 'border-white/10 bg-slate-900/40' }} p-6 text-center">
                @if ($tier['popular'] ?? false)
                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-brand-500 text-white text-xs font-bold px-3 py-1 rounded-full uppercase">{{ __('Best value') }}</span>
                @endif
                <p class="text-4xl font-black text-white">{{ $tier['credits'] }}</p>
                <p class="text-sm text-slate-400">{{ __('credits') }}</p>
                <p class="mt-4 text-2xl font-bold text-brand-400">{{ fcfa($tier['price']) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ __(':price / credit', ['price' => fcfa($tier['unit'])]) }}</p>
                <a href="{{ $waLink }}" target="_blank" rel="noopener" class="mt-6 block rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 px-4 py-2.5 font-semibold text-white">{{ __('Order now') }}</a>
            </div>
        @endforeach
    </div>
</section>
@endsection
