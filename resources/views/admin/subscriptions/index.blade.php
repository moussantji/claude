@extends('layouts.admin')

@section('title', 'Subscriptions')

@section('content')
<div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
    <h1 class="text-2xl font-bold text-white">Subscriptions</h1>
    <div class="flex gap-2">
        @foreach (['' => 'All', 'active' => 'Active', 'expired' => 'Expired', 'cancelled' => 'Cancelled', 'pending' => 'Pending'] as $value => $label)
            <a href="{{ route('admin.subscriptions.index', array_filter(['status' => $value])) }}"
               class="rounded-lg px-3 py-1.5 text-sm {{ (string) $status === (string) $value ? 'bg-brand-600 text-white' : 'bg-white/5 text-slate-300 hover:bg-white/10' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>

<div class="rounded-2xl border border-white/10 bg-slate-900/40 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-400 border-b border-white/10 bg-white/5">
                    <th class="py-3 px-4 font-medium">User</th>
                    <th class="py-3 px-4 font-medium">Package</th>
                    <th class="py-3 px-4 font-medium">Amount</th>
                    <th class="py-3 px-4 font-medium">Expires</th>
                    <th class="py-3 px-4 font-medium">Status</th>
                    <th class="py-3 px-4 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subscriptions as $sub)
                    <tr class="border-b border-white/5">
                        <td class="py-3 px-4 text-white">
                            {{ $sub->user?->name ?? '—' }}
                            <span class="block text-xs text-slate-500">{{ $sub->user?->email }}</span>
                        </td>
                        <td class="py-3 px-4 text-slate-300">{{ $sub->package?->name ?? '—' }}</td>
                        <td class="py-3 px-4 text-slate-300">${{ number_format($sub->amount_paid, 2) }}</td>
                        <td class="py-3 px-4 text-slate-300">{{ $sub->ends_at?->format('M j, Y') ?? '—' }}</td>
                        <td class="py-3 px-4">
                            <form method="POST" action="{{ route('admin.subscriptions.update', $sub) }}" class="flex items-center gap-2">
                                @csrf @method('PUT')
                                <select name="status" onchange="this.form.submit()"
                                    class="rounded-md bg-slate-950 border border-white/10 px-2 py-1 text-xs text-white focus:border-brand-500 outline-none">
                                    @foreach (['active', 'expired', 'cancelled', 'pending'] as $st)
                                        <option value="{{ $st }}" {{ $sub->status === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <form method="POST" action="{{ route('admin.subscriptions.destroy', $sub) }}" class="inline" onsubmit="return confirm('Delete this subscription record?');">
                                @csrf @method('DELETE')
                                <button class="text-rose-400 hover:text-rose-300">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">No subscriptions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $subscriptions->links() }}</div>
@endsection
