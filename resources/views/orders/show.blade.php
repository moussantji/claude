@extends('layouts.app')

@section('title', __('Order :ref', ['ref' => $order->reference]))

@section('content')
<div class="max-w-2xl mx-auto px-4 py-12" data-animate>
    <a href="{{ route('dashboard') }}" class="text-sm text-slate-400 hover:text-white">← {{ __('Back to dashboard') }}</a>

    <div class="mt-4 rounded-2xl border border-white/10 bg-slate-900/40 p-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h1 class="text-2xl font-bold text-white">{{ __('Order') }} {{ $order->reference }}</h1>
            <span class="text-xs font-bold uppercase px-2.5 py-1 rounded {{ $order->statusColor() }}">{{ __(ucfirst($order->status)) }}</span>
        </div>

        <dl class="mt-5 grid grid-cols-2 gap-4 text-sm">
            <div><dt class="text-slate-500">{{ __('Package') }}</dt><dd class="text-white font-medium">{{ $order->package_name }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Amount') }}</dt><dd class="text-white font-medium">{{ fcfa($order->amount) }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Payment method') }}</dt><dd class="text-white font-medium">{{ $order->payment_method_name }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Payment number') }}</dt><dd class="text-white font-medium">{{ $order->payment_number }}</dd></div>
        </dl>
    </div>

    @if ($order->isCompleted() && $order->hasCredentials())
        <div class="mt-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-6">
            <h2 class="text-lg font-semibold text-white mb-1">✅ {{ __('Your access is active') }}</h2>
            <p class="text-sm text-slate-400 mb-4">{{ __('Load these details into your IPTV player (TiviMate, IPTV Smarters, etc.).') }}</p>
            @include('partials.credentials', ['order' => $order])
        </div>
    @elseif ($order->status === 'rejected')
        <div class="mt-6 rounded-2xl border border-rose-500/30 bg-rose-500/5 p-6 text-sm text-rose-200">
            {{ __('This order was rejected. If you think this is a mistake, please contact us.') }}
            @if ($order->admin_note)<p class="mt-2 text-slate-300">{{ $order->admin_note }}</p>@endif
        </div>
    @else
        <div class="mt-6 rounded-2xl border border-amber-500/30 bg-amber-500/5 p-6 text-sm text-amber-200">
            ⏳ {{ __('We are verifying your payment. Your login details will appear here and will be emailed to you once confirmed.') }}
        </div>
    @endif
</div>
@endsection
