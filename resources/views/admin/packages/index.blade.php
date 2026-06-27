@extends('layouts.admin')

@section('title', __('Packages'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-white">{{ __('Packages') }}</h1>
    <a href="{{ route('admin.packages.create') }}" class="rounded-lg bg-brand-600 hover:bg-brand-500 px-4 py-2 text-sm font-semibold text-white">+ {{ __('New Package') }}</a>
</div>

<div class="rounded-2xl border border-white/10 bg-slate-900/40 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-400 border-b border-white/10 bg-white/5">
                    <th class="py-3 px-4 font-medium">{{ __('Name') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Price') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Duration') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Channels') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Orders') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Status') }}</th>
                    <th class="py-3 px-4 font-medium text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($packages as $package)
                    <tr class="border-b border-white/5">
                        <td class="py-3 px-4 text-white font-medium">
                            {{ $package->name }}
                            @if ($package->is_featured)<span class="ml-1 text-[10px] uppercase font-bold text-brand-400">★ {{ __('Featured') }}</span>@endif
                        </td>
                        <td class="py-3 px-4 text-slate-300">{{ fcfa($package->price) }}</td>
                        <td class="py-3 px-4 text-slate-300">{{ $package->duration_days }}{{ __('d') }}</td>
                        <td class="py-3 px-4 text-slate-300">{{ number_format($package->channel_count) }}</td>
                        <td class="py-3 px-4 text-slate-300">{{ $package->orders_count }}</td>
                        <td class="py-3 px-4">
                            <span class="text-xs font-bold px-2 py-0.5 rounded {{ $package->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-500/20 text-slate-400' }}">
                                {{ $package->is_active ? __('Active') : __('Hidden') }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right whitespace-nowrap">
                            <a href="{{ route('admin.packages.edit', $package) }}" class="text-brand-400 hover:text-brand-300">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.packages.destroy', $package) }}" class="inline ml-3" onsubmit="return confirm('{{ __('Delete this item?') }}');">
                                @csrf @method('DELETE')
                                <button class="text-rose-400 hover:text-rose-300">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">{{ __('No packages yet. Create your first one.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
