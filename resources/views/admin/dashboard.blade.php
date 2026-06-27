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
        [__('Active Subs'), number_format($activeSubCount), 'text-fuchsia-400'],
        [__('Revenue'), '$' . number_format($revenue, 2), 'text-green-400'],
    ])
    @foreach ($cards as [$label, $value, $color])
        <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-5">
            <p class="text-sm text-slate-400">{{ $label }}</p>
            <p class="text-2xl font-black {{ $color }} mt-1">{{ $value }}</p>
        </div>
    @endforeach
</div>

<div class="rounded-2xl border border-white/10 bg-slate-900/40 p-6">
    <h2 class="text-lg font-semibold text-white mb-4">{{ __('Recent subscriptions') }}</h2>
    @if ($recentSubs->isEmpty())
        <p class="text-sm text-slate-500">{{ __('No subscriptions yet.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-400 border-b border-white/10">
                        <th class="py-2 pr-4 font-medium">{{ __('User') }}</th>
                        <th class="py-2 pr-4 font-medium">{{ __('Package') }}</th>
                        <th class="py-2 pr-4 font-medium">{{ __('Amount') }}</th>
                        <th class="py-2 pr-4 font-medium">{{ __('Status') }}</th>
                        <th class="py-2 pr-4 font-medium">{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentSubs as $sub)
                        <tr class="border-b border-white/5">
                            <td class="py-2.5 pr-4 text-white">{{ $sub->user?->name ?? '—' }}</td>
                            <td class="py-2.5 pr-4 text-slate-300">{{ $sub->package?->name ?? '—' }}</td>
                            <td class="py-2.5 pr-4 text-slate-300">${{ number_format($sub->amount_paid, 2) }}</td>
                            <td class="py-2.5 pr-4"><span class="text-xs uppercase font-bold text-slate-400">{{ $sub->status }}</span></td>
                            <td class="py-2.5 pr-4 text-slate-500">{{ $sub->created_at->format('M j, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
