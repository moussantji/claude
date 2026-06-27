@extends('layouts.admin')

@section('title', __('Dashboard'))

@section('content')
<h1 class="text-2xl font-bold text-white mb-6">{{ __('Dashboard') }}</h1>

<div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4 mb-8">
    @php($cards = [
        [__('Users'), number_format($userCount), 'text-brand-400'],
        [__('Movies'), number_format($movieCount), 'text-rose-400'],
        [__('Series'), number_format($seriesCount), 'text-sky-400'],
        [__('Channels'), number_format($channelCount), 'text-emerald-400'],
        [__('Packages'), number_format($packageCount), 'text-amber-400'],
        [__('Pending orders'), number_format($pendingOrders), 'text-fuchsia-400'],
        [__('Revenue'), fcfa($revenue), 'text-green-400'],
    ])
    @foreach ($cards as [$label, $value, $color])
        <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-5">
            <p class="text-sm text-slate-400">{{ $label }}</p>
            <p class="text-2xl font-black {{ $color }} mt-1">{{ $value }}</p>
        </div>
    @endforeach
</div>

<div class="rounded-2xl border border-white/10 bg-slate-900/40 p-6">
    <h2 class="text-lg font-semibold text-white mb-4">{{ __('Recent orders') }}</h2>
    @if ($recentOrders->isEmpty())
        <p class="text-sm text-slate-500">{{ __('No orders yet.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-400 border-b border-white/10">
                        <th class="py-2 pr-4 font-medium">{{ __('Reference') }}</th>
                        <th class="py-2 pr-4 font-medium">{{ __('Customer') }}</th>
                        <th class="py-2 pr-4 font-medium">{{ __('Amount') }}</th>
                        <th class="py-2 pr-4 font-medium">{{ __('Status') }}</th>
                        <th class="py-2 pr-4 font-medium text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentOrders as $order)
                        <tr class="border-b border-white/5">
                            <td class="py-2.5 pr-4 text-white font-mono text-xs">{{ $order->reference }}</td>
                            <td class="py-2.5 pr-4 text-slate-300">{{ $order->user?->name ?? '—' }}</td>
                            <td class="py-2.5 pr-4 text-slate-300">{{ fcfa($order->amount) }}</td>
                            <td class="py-2.5 pr-4"><span class="text-xs uppercase font-bold px-2 py-0.5 rounded {{ $order->statusColor() }}">{{ __(ucfirst($order->status)) }}</span></td>
                            <td class="py-2.5 pr-4 text-right"><a href="{{ route('admin.orders.edit', $order) }}" class="text-brand-400 hover:text-brand-300">{{ __('Edit') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
