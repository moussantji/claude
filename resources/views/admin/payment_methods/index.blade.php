@extends('layouts.admin')

@section('title', __('Payment methods'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-white">{{ __('Payment methods') }}</h1>
    <a href="{{ route('admin.payment-methods.create') }}" class="rounded-lg bg-brand-600 hover:bg-brand-500 px-4 py-2 text-sm font-semibold text-white">+ {{ __('New method') }}</a>
</div>

<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse ($methods as $method)
        <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-white">{{ $method->name }}</h3>
                    <p class="text-xs text-slate-500 font-mono">{{ $method->account_number ?: '—' }}</p>
                </div>
                <span class="text-xs font-bold px-2 py-0.5 rounded {{ $method->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-500/20 text-slate-400' }}">
                    {{ $method->is_active ? __('Active') : __('Hidden') }}
                </span>
            </div>
            @if ($method->qrUrl())
                <img src="{{ $method->qrUrl() }}" alt="QR" class="mt-3 h-24 w-24 rounded-lg bg-white p-1 object-contain">
            @else
                <p class="mt-3 text-xs text-amber-300">{{ __('No QR code uploaded') }}</p>
            @endif
            <div class="mt-4 flex gap-3 text-sm">
                <a href="{{ route('admin.payment-methods.edit', $method) }}" class="text-brand-400 hover:text-brand-300">{{ __('Edit') }}</a>
                <form method="POST" action="{{ route('admin.payment-methods.destroy', $method) }}" onsubmit="return confirm('{{ __('Delete this item?') }}');">
                    @csrf @method('DELETE')
                    <button class="text-rose-400 hover:text-rose-300">{{ __('Delete') }}</button>
                </form>
            </div>
        </div>
    @empty
        <p class="text-slate-500 text-sm">{{ __('No payment methods yet.') }}</p>
    @endforelse
</div>
@endsection
