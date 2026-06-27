@extends('layouts.app')

@section('title', 'Pricing & Plans')

@section('content')
<section class="hero-grid border-b border-white/10">
    <div class="max-w-7xl mx-auto px-4 py-16 text-center">
        <h1 class="text-4xl md:text-5xl font-black text-white">Choose your plan</h1>
        <p class="mt-4 text-lg text-slate-400 max-w-2xl mx-auto">
            All plans include HD streaming, our full VOD library and 24/7 support. Upgrade or cancel anytime.
        </p>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 py-16">
    @if ($packages->isEmpty())
        <p class="text-center text-slate-400">No plans are available right now. Please check back soon.</p>
    @else
        <div class="grid md:grid-cols-2 lg:grid-cols-{{ min($packages->count(), 4) }} gap-6 max-w-6xl mx-auto">
            @foreach ($packages as $package)
                @include('partials.package-card', ['package' => $package])
            @endforeach
        </div>
    @endif

    <div class="mt-16 max-w-3xl mx-auto">
        <h2 class="text-2xl font-bold text-white text-center mb-8">Frequently asked questions</h2>
        <div class="space-y-4">
            @php($faqs = [
                ['What devices are supported?', 'Any device with an IPTV player or web browser — Smart TVs, Android/iOS, Fire Stick, MAG boxes and desktop computers.'],
                ['Can I cancel anytime?', 'Yes. Subscriptions are prepaid for the chosen duration and simply expire — there are no hidden fees.'],
                ['How do I start watching after subscribing?', 'Your dashboard provides a personal M3U playlist URL and login credentials you can load into any compatible player.'],
            ])
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
