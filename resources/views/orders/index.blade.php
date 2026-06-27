@extends('layouts.app')

@section('title', __('My Orders'))

@section('content')
<div class="max-w-3xl mx-auto px-4 py-10" data-animate>
    <h1 class="text-2xl font-bold text-white mb-6">{{ __('My Orders') }}</h1>

    <div class="space-y-3">
        @forelse ($orders as $order)
            <a href="{{ route('orders.show', $order) }}" class="block rounded-2xl border border-white/10 bg-slate-900/40 p-4 hover:border-brand-500/40 transition">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="font-medium text-white">{{ $order->package_name }}</p>
                        <p class="text-xs text-slate-500">{{ $order->reference }} · {{ $order->created_at->format('d/m/Y') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-white font-semibold">{{ fcfa($order->amount) }}</p>
                        <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded {{ $order->statusColor() }}">{{ __(ucfirst($order->status)) }}</span>
                    </div>
                </div>
            </a>
        @empty
            <p class="text-slate-500 text-sm">{{ __('You have no orders yet.') }}</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $orders->links() }}</div>
</div>
@endsection
