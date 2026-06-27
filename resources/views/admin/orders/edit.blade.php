@extends('layouts.admin')

@section('title', __('Order :ref', ['ref' => $order->reference]))

@section('content')
<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.orders.index') }}" class="text-slate-400 hover:text-white">←</a>
    <h1 class="text-2xl font-bold text-white">{{ __('Order') }} {{ $order->reference }}</h1>
    <span class="text-xs font-bold uppercase px-2 py-0.5 rounded {{ $order->statusColor() }}">{{ __(ucfirst($order->status)) }}</span>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    {{-- Order info --}}
    <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-6 h-fit">
        <h2 class="text-lg font-semibold text-white mb-4">{{ __('Order details') }}</h2>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Customer') }}</dt><dd class="text-white">{{ $order->user?->name }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Email') }}</dt><dd class="text-white">{{ $order->user?->email }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Package') }}</dt><dd class="text-white">{{ $order->package_name }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Amount') }}</dt><dd class="text-white">{{ fcfa($order->amount) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Payment method') }}</dt><dd class="text-white">{{ $order->payment_method_name }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Payment number') }}</dt><dd class="text-white font-mono">{{ $order->payment_number }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Placed') }}</dt><dd class="text-white">{{ $order->created_at->format('d/m/Y H:i') }}</dd></div>
        </dl>
    </div>

    {{-- Fulfilment --}}
    <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="rounded-2xl border border-white/10 bg-slate-900/40 p-6 space-y-5">
        @csrf @method('PUT')
        <h2 class="text-lg font-semibold text-white">{{ __('Fulfil the order') }}</h2>
        <p class="text-sm text-slate-400">{{ __('Enter the access details. When you set the status to Completed, the customer is emailed and notified automatically.') }}</p>

        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('DNS / Server') }}</label>
            <input name="iptv_dns" value="{{ old('iptv_dns', $order->iptv_dns) }}" placeholder="http://example.com:8080"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white font-mono text-sm focus:border-brand-500 outline-none">
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Username') }}</label>
                <input name="iptv_username" value="{{ old('iptv_username', $order->iptv_username) }}"
                    class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white font-mono text-sm focus:border-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Password') }}</label>
                <input name="iptv_password" value="{{ old('iptv_password', $order->iptv_password) }}"
                    class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white font-mono text-sm focus:border-brand-500 outline-none">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Internal note') }}</label>
            <textarea name="admin_note" rows="2"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">{{ old('admin_note', $order->admin_note) }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Status') }}</label>
            @php($statusLabels = ['pending' => __('Pending'), 'processing' => __('Processing'), 'completed' => __('Completed'), 'rejected' => __('Rejected')])
            <select name="status" class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
                @foreach ($statusLabels as $value => $label)
                    <option value="{{ $value }}" {{ old('status', $order->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <p class="mt-1.5 text-xs text-slate-500">{{ __('Completed requires username, password and DNS.') }}</p>
        </div>

        <button class="w-full rounded-lg bg-brand-600 hover:bg-brand-500 px-5 py-3 text-sm font-semibold text-white">{{ __('Update the order') }}</button>
    </form>
</div>
@endsection
