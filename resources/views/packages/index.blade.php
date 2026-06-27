@extends('layouts.app')

@section('title', __('Pricing & Plans'))

@section('content')
<section class="relative hero-grid border-b border-white/10 overflow-hidden">
    <div class="absolute inset-0 bg-cover bg-center opacity-20" style="background-image:url('{{ config('streamwave.images.pricing') }}')"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-slate-950/60 to-slate-950"></div>
    <div class="relative max-w-7xl mx-auto px-4 py-16 text-center" data-animate>
        <h1 class="text-4xl md:text-5xl font-black text-white">{{ __('Choose your plan') }}</h1>
        <p class="mt-4 text-lg text-slate-300 max-w-2xl mx-auto">
            {{ __('All plans include HD streaming, our full VOD library and 24/7 support. Upgrade or cancel anytime.') }}
        </p>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 py-16">
    @if ($packages->isEmpty())
        <p class="text-center text-slate-400">{{ __('No plans are available right now. Please check back soon.') }}</p>
    @else
        <div class="grid md:grid-cols-2 lg:grid-cols-{{ min($packages->count(), 4) }} gap-6 max-w-6xl mx-auto" data-animate>
            @foreach ($packages as $package)
                @include('partials.package-card', ['package' => $package])
            @endforeach
        </div>
    @endif

    <div class="mt-16 max-w-3xl mx-auto" data-animate>
        <h2 class="text-2xl font-bold text-white text-center mb-8">{{ __('Frequently asked questions') }}</h2>
        <div class="space-y-4">
            @php
                $faqs = [
                    [__('What devices are supported?'), __('Any device with an IPTV player or web browser — Smart TVs, Android/iOS, Fire Stick, MAG boxes and desktop computers.')],
                    [__('Can I cancel anytime?'), __('Yes. Subscriptions are prepaid for the chosen duration and simply expire — there are no hidden fees.')],
                    [__('How do I start watching after subscribing?'), __('Your dashboard provides a personal M3U playlist URL and login credentials you can load into any compatible player.')],
                    [__('Which payment methods can I use?'), __('We accept card, PayPal, crypto and Orange Money.')],
                ];
            @endphp
            @foreach ($faqs as [$q, $a])
                <details class="group rounded-xl border border-white/10 bg-slate-900/40 p-5">
                    <summary class="flex cursor-pointer items-center justify-between font-medium text-white">
                        {{ $q }}
                        <span class="text-slate-500 group-open:rotate-45 transition">+</span>
                    </summary>
                    <p class="mt-3 text-sm text-slate-400">{{ $a }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
@endsection
