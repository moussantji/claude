@extends('layouts.app')

@section('title', __('Checkout') . ' — ' . $package->name)

@section('content')
<div class="max-w-5xl mx-auto px-4 py-12" data-animate x-data="{ method: '{{ old('payment_method_id', '') }}' }">
    <a href="{{ route('packages.index') }}" class="text-sm text-slate-400 hover:text-white">← {{ __('Back to plans') }}</a>
    <h1 class="text-3xl font-bold text-white mt-3 mb-8">{{ __('Complete your order') }}</h1>

    @if ($methods->isEmpty())
        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-amber-200">
            {{ __('No payment method is available right now. Please contact us.') }}
        </div>
    @else
    <form method="POST" action="{{ route('orders.store', $package) }}" class="grid md:grid-cols-5 gap-6">
        @csrf

        {{-- Order summary --}}
        <div class="md:col-span-2 rounded-2xl border border-white/10 bg-slate-900/40 p-6 h-fit">
            <h2 class="text-lg font-semibold text-white mb-4">{{ __('Order summary') }}</h2>
            <div class="flex items-baseline justify-between">
                <span class="text-slate-300">{{ $package->name }}</span>
                <span class="text-white font-semibold">{{ fcfa($package->price) }}</span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ $package->description }}</p>
            <ul class="mt-5 space-y-2 text-sm text-slate-300">
                <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> {{ __(':count+ channels', ['count' => number_format($package->channel_count)]) }}</li>
                <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> {{ __(':days days access', ['days' => $package->duration_days]) }}</li>
                <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> {{ __(':n simultaneous connections', ['n' => $package->max_connections]) }}</li>
            </ul>
            <div class="border-t border-white/10 mt-5 pt-5 flex items-baseline justify-between">
                <span class="text-slate-400">{{ __('Total to pay') }}</span>
                <span class="text-2xl font-black text-white">{{ fcfa($package->price) }}</span>
            </div>
        </div>

        {{-- Payment --}}
        <div class="md:col-span-3 space-y-5">
            <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-6">
                <h2 class="text-lg font-semibold text-white mb-4">{{ __('Choose a payment method') }}</h2>
                @error('payment_method_id')<p class="mb-3 text-sm text-rose-400">{{ $message }}</p>@enderror

                <div class="grid grid-cols-3 gap-3">
                    @foreach ($methods as $m)
                        <label class="cursor-pointer rounded-xl border px-3 py-3 text-center text-sm font-medium transition"
                               :class="method === '{{ $m->id }}' ? 'border-brand-500 bg-brand-600/10 text-white' : 'border-white/10 bg-slate-950 text-slate-300 hover:border-brand-500'">
                            <input type="radio" name="payment_method_id" value="{{ $m->id }}" class="sr-only" x-model="method">
                            {{ $m->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Per-method procedure + QR + number --}}
            @foreach ($methods as $m)
                <div x-show="method === '{{ $m->id }}'" x-cloak x-transition class="rounded-2xl border border-white/10 bg-slate-900/40 p-6 space-y-5">
                    <div>
                        <h3 class="text-base font-semibold text-white flex items-center gap-2">📋 {{ __('Payment instructions') }} — {{ $m->name }}</h3>
                        @if ($m->account_number)
                            <p class="mt-2 text-sm text-slate-300">{{ __('Send :amount to:', ['amount' => fcfa($package->price)]) }}
                                <span class="font-bold text-white select-all">{{ $m->account_number }}</span>
                            </p>
                        @endif
                        @if ($m->instructions)
                            <div class="mt-3 text-sm text-slate-300 whitespace-pre-line leading-relaxed">{{ $m->instructions }}</div>
                        @endif
                    </div>

                    @if ($m->qrUrl())
                        <div class="flex flex-col items-center gap-2">
                            <p class="text-sm text-slate-400">{{ __('Or scan this QR code to pay') }}</p>
                            <img src="{{ $m->qrUrl() }}" alt="QR {{ $m->name }}" class="h-44 w-44 rounded-xl bg-white p-2 object-contain">
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- Payment number + submit (shown once a method is selected) --}}
            <div x-show="method !== ''" x-cloak x-transition class="rounded-2xl border border-white/10 bg-slate-900/40 p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Phone number used for payment') }}</label>
                    <input type="tel" name="payment_number" value="{{ old('payment_number') }}" placeholder="07 00 00 00 00"
                        class="w-full rounded-lg bg-slate-950 border border-white/10 px-3.5 py-2.5 text-white placeholder-slate-600 focus:border-brand-500 outline-none">
                    @error('payment_number')<p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>@enderror
                    <p class="mt-2 text-xs text-slate-500">{{ __('After you pay, submit this form. An agent verifies your payment and activates your access.') }}</p>
                </div>
                <button type="submit" class="w-full rounded-lg bg-brand-600 hover:bg-brand-500 text-white font-semibold py-3 transition shadow-lg shadow-brand-600/30">
                    {{ __('I have paid — place my order') }}
                </button>
            </div>
        </div>
    </form>
    @endif
</div>

<style>[x-cloak]{display:none!important}</style>
@endsection
