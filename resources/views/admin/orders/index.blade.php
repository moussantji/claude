@extends('layouts.admin')

@section('title', __('Orders'))

@section('content')
<div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
    <h1 class="text-2xl font-bold text-white">{{ __('Orders') }}</h1>
    <form method="GET" action="{{ route('admin.orders.index') }}">
        <input name="search" value="{{ $search }}" placeholder="{{ __('Search ref, name, email…') }}"
            class="rounded-lg bg-slate-950 border border-white/10 px-3 py-2 text-sm text-white focus:border-brand-500 outline-none w-64 max-w-full">
    </form>
</div>

<div class="flex gap-2 flex-wrap mb-4">
    @php($filters = ['' => __('All'), 'pending' => __('Pending'), 'processing' => __('Processing'), 'completed' => __('Completed'), 'rejected' => __('Rejected')])
    @foreach ($filters as $value => $label)
        <a href="{{ route('admin.orders.index', array_filter(['status' => $value, 'search' => $search])) }}"
           class="rounded-lg px-3 py-1.5 text-sm {{ (string) $status === (string) $value ? 'bg-brand-600 text-white' : 'bg-white/5 text-slate-300 hover:bg-white/10' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<div class="rounded-2xl border border-white/10 bg-slate-900/40 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-400 border-b border-white/10 bg-white/5">
                    <th class="py-3 px-4 font-medium">{{ __('Reference') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Customer') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Package') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Method') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Amount') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Status') }}</th>
                    <th class="py-3 px-4 font-medium text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr class="border-b border-white/5">
                        <td class="py-3 px-4 text-white font-mono text-xs">{{ $order->reference }}</td>
                        <td class="py-3 px-4 text-slate-300">
                            {{ $order->user?->name }}
                            <span class="block text-xs text-slate-500">{{ $order->payment_number }}</span>
                        </td>
                        <td class="py-3 px-4 text-slate-300">{{ $order->package_name }}</td>
                        <td class="py-3 px-4 text-slate-400 text-xs">{{ $order->payment_method_name }}</td>
                        <td class="py-3 px-4 text-slate-300">{{ fcfa($order->amount) }}</td>
                        <td class="py-3 px-4"><span class="text-xs font-bold uppercase px-2 py-0.5 rounded {{ $order->statusColor() }}">{{ __(ucfirst($order->status)) }}</span></td>
                        <td class="py-3 px-4 text-right">
                            <a href="{{ route('admin.orders.edit', $order) }}" class="text-brand-400 hover:text-brand-300 font-medium">{{ __('Edit the order') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">{{ __('No orders found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $orders->links() }}</div>
@endsection
