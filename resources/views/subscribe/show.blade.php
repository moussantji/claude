@extends('layouts.app')

@section('title', __('Checkout') . ' — ' . $package->name)

@section('content')
<div class="max-w-5xl mx-auto px-4 py-12" data-animate>
    <a href="{{ route('packages.index') }}" class="text-sm text-slate-400 hover:text-white">← {{ __('Back to plans') }}</a>
    <h1 class="text-3xl font-bold text-white mt-3 mb-8">{{ __('Complete your subscription') }}</h1>

    <div class="grid md:grid-cols-5 gap-6">
        {{-- Order summary --}}
        <div class="md:col-span-2 rounded-2xl border border-white/10 bg-slate-900/40 p-6 h-fit">
            <h2 class="text-lg font-semibold text-white mb-4">{{ __('Order summary') }}</h2>
            <div class="flex items-baseline justify-between">
                <span class="text-slate-300">{{ $package->name }}</span>
                <span class="text-white font-semibold">${{ number_format($package->price, 2) }}</span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ $package->description }}</p>

            <ul class="mt-5 space-y-2 text-sm text-slate-300">
                <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> {{ __(':count+ channels', ['count' => number_format($package->channel_count)]) }}</li>
                <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> {{ __(':days days access', ['days' => $package->duration_days]) }}</li>
                <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> {{ __(':n simultaneous connections', ['n' => $package->max_connections]) }}</li>
                @foreach (($package->features ?? []) as $feature)
                    <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> {{ $feature }}</li>
                @endforeach
            </ul>

            <div class="border-t border-white/10 mt-5 pt-5 flex items-baseline justify-between">
                <span class="text-slate-400">{{ __('Total due today') }}</span>
                <span class="text-2xl font-black text-white">${{ number_format($package->price, 2) }}</span>
            </div>
        </div>

        {{-- Payment form (simulated) — Alpine powers the fluid method switching --}}
        <div class="md:col-span-3 rounded-2xl border border-white/10 bg-slate-900/40 p-6"
             x-data="{ method: '{{ old('payment_method', 'card') }}' }">
            <div class="rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-200 px-4 py-3 text-sm mb-6">
                <strong>{{ __('Demo mode:') }}</strong> {{ __('this is a simulated checkout. No real payment is processed and no card details are stored.') }}
            </div>

            @error('agree')<p class="mb-4 text-sm text-rose-400">{{ $message }}</p>@enderror

            <form method="POST" action="{{ route('subscribe.store', $package) }}" class="space-y-6">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">{{ __('Payment method') }}</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @php
                            $methods = [
                                'card' => '💳 ' . __('Card'),
                                'paypal' => 'PayPal',
                                'crypto' => '₿ ' . __('Crypto'),
                                'orange_money' => '🟠 Orange Money',
                            ];
                        @endphp
                        @foreach ($methods as $value => $label)
                            <label class="cursor-pointer rounded-lg border px-3 py-3 text-center text-sm transition"
                                   :class="method === '{{ $value }}' ? 'border-brand-500 bg-brand-600/10 text-white' : 'border-white/10 bg-slate-950 text-slate-300 hover:border-brand-500'">
                                <input type="radio" name="payment_method" value="{{ $value }}" class="sr-only" x-model="method">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('payment_method')<p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>@enderror
                </div>

                {{-- Card fields --}}
                <div class="grid sm:grid-cols-2 gap-4" x-show="method === 'card'" x-transition x-cloak>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Card number') }}</label>
                        <input type="text" inputmode="numeric" placeholder="4242 4242 4242 4242"
                            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3.5 py-2.5 text-white placeholder-slate-600 focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Expiry') }}</label>
                        <input type="text" placeholder="MM/YY"
                            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3.5 py-2.5 text-white placeholder-slate-600 focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">CVC</label>
                        <input type="text" inputmode="numeric" placeholder="123"
                            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3.5 py-2.5 text-white placeholder-slate-600 focus:border-brand-500 outline-none">
                    </div>
                </div>

                {{-- Orange Money fields --}}
                <div x-show="method === 'orange_money'" x-transition x-cloak class="space-y-4">
                    <div class="rounded-lg border border-orange-500/30 bg-orange-500/10 p-4 text-sm text-orange-200">
                        <p class="font-semibold flex items-center gap-2">🟠 {{ __('Pay with Orange Money') }}</p>
                        <ol class="mt-2 space-y-1 list-decimal list-inside text-orange-200/90">
                            <li>{{ __('Dial :code on your phone.', ['code' => config('streamwave.orange_money.merchant_code')]) }}</li>
                            <li>{{ __('Send :amount to :name.', ['amount' => '$' . number_format($package->price, 2), 'name' => config('streamwave.orange_money.merchant_name')]) }}</li>
                            <li>{{ __('Enter the phone number you paid with below to confirm.') }}</li>
                        </ol>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Orange Money phone number') }}</label>
                        <input type="tel" name="orange_phone" value="{{ old('orange_phone') }}" placeholder="+221 77 000 00 00"
                            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3.5 py-2.5 text-white placeholder-slate-600 focus:border-brand-500 outline-none">
                        @error('orange_phone')<p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- PayPal / Crypto note --}}
                <div x-show="method === 'paypal' || method === 'crypto'" x-transition x-cloak
                     class="rounded-lg border border-white/10 bg-slate-950 p-4 text-sm text-slate-400">
                    {{ __('You will be redirected to complete the payment after confirming.') }}
                </div>

                <label class="flex items-start gap-2 text-sm text-slate-400">
                    <input type="checkbox" name="agree" value="1" class="mt-0.5 rounded border-white/20 bg-slate-950 text-brand-600 focus:ring-brand-500/40">
                    {{ __('I agree to the Terms of Service and acknowledge this is a demonstration purchase.') }}
                </label>

                <button type="submit" class="w-full rounded-lg bg-brand-600 hover:bg-brand-500 text-white font-semibold py-3 transition shadow-lg shadow-brand-600/30">
                    {{ __('Pay :amount', ['amount' => '$' . number_format($package->price, 2)]) }}
                </button>
            </form>
        </div>
    </div>
</div>

<style>[x-cloak]{display:none!important}</style>
@endsection
