@extends('layouts.app')

@section('title', 'Checkout — ' . $package->name)

@section('content')
<div class="max-w-5xl mx-auto px-4 py-12">
    <a href="{{ route('packages.index') }}" class="text-sm text-slate-400 hover:text-white">← Back to plans</a>
    <h1 class="text-3xl font-bold text-white mt-3 mb-8">Complete your subscription</h1>

    <div class="grid md:grid-cols-5 gap-6">
        {{-- Order summary --}}
        <div class="md:col-span-2 rounded-2xl border border-white/10 bg-slate-900/40 p-6 h-fit">
            <h2 class="text-lg font-semibold text-white mb-4">Order summary</h2>
            <div class="flex items-baseline justify-between">
                <span class="text-slate-300">{{ $package->name }}</span>
                <span class="text-white font-semibold">${{ number_format($package->price, 2) }}</span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ $package->description }}</p>

            <ul class="mt-5 space-y-2 text-sm text-slate-300">
                <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> {{ number_format($package->channel_count) }}+ channels</li>
                <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> {{ $package->duration_days }} days access</li>
                <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> {{ $package->max_connections }} simultaneous connections</li>
                @foreach (($package->features ?? []) as $feature)
                    <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> {{ $feature }}</li>
                @endforeach
            </ul>

            <div class="border-t border-white/10 mt-5 pt-5 flex items-baseline justify-between">
                <span class="text-slate-400">Total due today</span>
                <span class="text-2xl font-black text-white">${{ number_format($package->price, 2) }}</span>
            </div>
        </div>

        {{-- Payment form (simulated) --}}
        <div class="md:col-span-3 rounded-2xl border border-white/10 bg-slate-900/40 p-6">
            <div class="rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-200 px-4 py-3 text-sm mb-6">
                <strong>Demo mode:</strong> this is a simulated checkout. No real payment is processed and no card details are stored.
            </div>

            @error('agree')<p class="mb-4 text-sm text-rose-400">{{ $message }}</p>@enderror

            <form method="POST" action="{{ route('subscribe.store', $package) }}" class="space-y-6">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Payment method</label>
                    <div class="grid grid-cols-3 gap-3">
                        @foreach (['card' => '💳 Card', 'paypal' => 'PayPal', 'crypto' => '₿ Crypto'] as $value => $label)
                            <label class="cursor-pointer rounded-lg border border-white/10 bg-slate-950 px-3 py-3 text-center text-sm text-slate-300 hover:border-brand-500 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-600/10 has-[:checked]:text-white">
                                <input type="radio" name="payment_method" value="{{ $value }}" class="sr-only" {{ $loop->first ? 'checked' : '' }}>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('payment_method')<p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div class="grid sm:grid-cols-2 gap-4 opacity-90">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Card number</label>
                        <input type="text" inputmode="numeric" placeholder="4242 4242 4242 4242"
                            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3.5 py-2.5 text-white placeholder-slate-600 focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Expiry</label>
                        <input type="text" placeholder="MM/YY"
                            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3.5 py-2.5 text-white placeholder-slate-600 focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">CVC</label>
                        <input type="text" inputmode="numeric" placeholder="123"
                            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3.5 py-2.5 text-white placeholder-slate-600 focus:border-brand-500 outline-none">
                    </div>
                </div>

                <label class="flex items-start gap-2 text-sm text-slate-400">
                    <input type="checkbox" name="agree" value="1" class="mt-0.5 rounded border-white/20 bg-slate-950 text-brand-600 focus:ring-brand-500/40">
                    I agree to the Terms of Service and acknowledge this is a demonstration purchase.
                </label>

                <button type="submit" class="w-full rounded-lg bg-brand-600 hover:bg-brand-500 text-white font-semibold py-3 transition shadow-lg shadow-brand-600/30">
                    Pay ${{ number_format($package->price, 2) }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
