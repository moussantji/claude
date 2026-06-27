@extends('layouts.app')

@section('title', __('My Dashboard'))

@section('content')
<div class="max-w-7xl mx-auto px-4 py-10">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8" data-animate>
        <div>
            <h1 class="text-3xl font-bold text-white">{{ __('Welcome, :name', ['name' => $user->name]) }}</h1>
            <p class="text-slate-400 mt-1">{{ __('Manage your subscription and streaming access.') }}</p>
        </div>
        <a href="{{ route('packages.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 hover:bg-brand-500 px-5 py-2.5 font-semibold text-white shadow-lg shadow-brand-600/30">
            {{ $active ? __('Renew / Upgrade') : __('Get a Plan') }}
        </a>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Streaming access --}}
            <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-6" data-animate>
                <h2 class="text-lg font-semibold text-white mb-4">{{ __('Your streaming access') }}</h2>
                @if ($active && $active->hasCredentials())
                    <div class="flex items-center gap-2 mb-4">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                        <span class="text-emerald-400 font-semibold uppercase text-sm tracking-wide">{{ __('Active') }}</span>
                        <span class="text-slate-500 text-sm">· {{ $active->package_name }}</span>
                    </div>
                    @include('partials.credentials', ['order' => $active])
                @else
                    <div class="text-center py-8">
                        <div class="text-4xl mb-3">📭</div>
                        <p class="text-slate-300 font-medium">{{ __('You have no active access yet.') }}</p>
                        @if ($pendingCount > 0)
                            <p class="text-amber-300 text-sm mt-1">{{ __('You have :n order(s) awaiting confirmation.', ['n' => $pendingCount]) }}</p>
                        @else
                            <p class="text-slate-500 text-sm mt-1">{{ __('Choose a plan to get started.') }}</p>
                        @endif
                        <a href="{{ route('packages.index') }}" class="mt-5 inline-block rounded-lg bg-brand-600 hover:bg-brand-500 px-6 py-2.5 font-semibold text-white">{{ __('Browse Plans') }}</a>
                    </div>
                @endif
            </div>
        </div>

        {{-- Orders --}}
        <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-6" data-animate>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-white">{{ __('Recent orders') }}</h2>
                <a href="{{ route('orders.index') }}" class="text-sm text-brand-400 hover:text-brand-300">{{ __('View all') }}</a>
            </div>
            @forelse ($orders as $order)
                <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between py-3 border-b border-white/5 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-white">{{ $order->package_name }}</p>
                        <p class="text-xs text-slate-500">{{ $order->created_at->format('d/m/Y') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-white">{{ fcfa($order->amount) }}</p>
                        <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded {{ $order->statusColor() }}">{{ __(ucfirst($order->status)) }}</span>
                    </div>
                </a>
            @empty
                <p class="text-sm text-slate-500">{{ __('No orders yet.') }}</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
