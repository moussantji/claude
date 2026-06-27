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
            {{ $active ? __('Upgrade / Extend') : __('Get a Plan') }}
        </a>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        {{-- Subscription status --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-6" data-animate>
                <h2 class="text-lg font-semibold text-white mb-4">{{ __('Subscription status') }}</h2>
                @if ($active)
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                                <span class="text-emerald-400 font-semibold uppercase text-sm tracking-wide">{{ __('Active') }}</span>
                            </div>
                            <p class="text-2xl font-bold text-white mt-2">{{ $active->package->name }}</p>
                            <p class="text-slate-400 text-sm mt-1">
                                {{ __('Renews / expires :date', ['date' => $active->ends_at->format('M j, Y')]) }}
                                ({{ $active->ends_at->diffForHumans() }})
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-slate-400">{{ __('Max connections') }}</p>
                            <p class="text-2xl font-bold text-white">{{ $active->package->max_connections }}</p>
                        </div>
                    </div>

                    @php($total = $active->starts_at->diffInDays($active->ends_at) ?: 1)
                    @php($remaining = max(0, now()->diffInDays($active->ends_at, false)))
                    @php($pct = min(100, max(0, ($remaining / $total) * 100)))
                    <div class="mt-5">
                        <div class="h-2 rounded-full bg-white/10 overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-brand-500 to-fuchsia-500" style="width: {{ $pct }}%"></div>
                        </div>
                        <p class="text-xs text-slate-500 mt-2">{{ __(':days days remaining', ['days' => round($remaining)]) }}</p>
                    </div>

                    <form method="POST" action="{{ route('subscribe.cancel', $active) }}" class="mt-5"
                          onsubmit="return confirm('{{ __('Cancel this subscription? Access will be revoked.') }}');">
                        @csrf
                        @method('PATCH')
                        <button class="text-sm text-rose-400 hover:text-rose-300">{{ __('Cancel subscription') }}</button>
                    </form>
                @else
                    <div class="text-center py-8">
                        <div class="text-4xl mb-3">📭</div>
                        <p class="text-slate-300 font-medium">{{ __("You don't have an active subscription.") }}</p>
                        <p class="text-slate-500 text-sm mt-1">{{ __('Choose a plan to start streaming :count+ channels.', ['count' => number_format($channelCount)]) }}</p>
                        <a href="{{ route('packages.index') }}" class="mt-5 inline-block rounded-lg bg-brand-600 hover:bg-brand-500 px-6 py-2.5 font-semibold text-white">{{ __('Browse Plans') }}</a>
                    </div>
                @endif
            </div>

            {{-- Playlist access --}}
            <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-6" data-animate>
                <h2 class="text-lg font-semibold text-white mb-1">{{ __('Your streaming access') }}</h2>
                <p class="text-sm text-slate-400 mb-4">{{ __('Load these into any IPTV player (TiviMate, IPTV Smarters, VLC, etc.).') }}</p>

                @if ($active)
                    @php($playlistUrl = route('playlist.m3u', ['username' => $user->iptv_username, 'password' => $user->iptv_password]))
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">{{ __('M3U Playlist URL') }}</label>
                            <div class="flex gap-2">
                                <input id="playlistUrl" type="text" readonly value="{{ $playlistUrl }}"
                                    class="flex-1 rounded-lg bg-slate-950 border border-white/10 px-3 py-2 text-sm text-slate-200 font-mono">
                                <button onclick="copyVal('playlistUrl', this)" type="button"
                                    class="rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 px-4 py-2 text-sm text-white">{{ __('Copy') }}</button>
                            </div>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1.5">{{ __('Username') }}</label>
                                <input id="iptvUser" type="text" readonly value="{{ $user->iptv_username }}"
                                    class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2 text-sm text-slate-200 font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1.5">{{ __('Password') }}</label>
                                <input id="iptvPass" type="text" readonly value="{{ $user->iptv_password }}"
                                    class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2 text-sm text-slate-200 font-mono">
                            </div>
                        </div>
                        <a href="{{ $playlistUrl }}" class="inline-flex items-center gap-2 text-sm text-brand-400 hover:text-brand-300 font-medium">
                            ⬇ {{ __('Download .m3u file') }}
                        </a>
                    </div>
                @else
                    <div class="rounded-lg bg-white/5 border border-white/10 px-4 py-6 text-center text-sm text-slate-400">
                        {{ __('Your playlist will appear here once you have an active subscription.') }}
                    </div>
                @endif
            </div>
        </div>

        {{-- History --}}
        <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-6" data-animate>
            <h2 class="text-lg font-semibold text-white mb-4">{{ __('Billing history') }}</h2>
            @forelse ($history as $sub)
                <div class="flex items-center justify-between py-3 border-b border-white/5 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-white">{{ $sub->package?->name ?? 'Package' }}</p>
                        <p class="text-xs text-slate-500">{{ $sub->created_at->format('M j, Y') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-white">${{ number_format($sub->amount_paid, 2) }}</p>
                        @php($badge = match($sub->status) {
                            'active' => 'bg-emerald-500/20 text-emerald-300',
                            'expired' => 'bg-slate-500/20 text-slate-300',
                            'cancelled' => 'bg-rose-500/20 text-rose-300',
                            default => 'bg-amber-500/20 text-amber-300',
                        })
                        <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded {{ $badge }}">{{ $sub->status }}</span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">{{ __('No purchases yet.') }}</p>
            @endforelse
        </div>
    </div>
</div>

<script>
    function copyVal(id, btn) {
        const el = document.getElementById(id);
        navigator.clipboard.writeText(el.value).then(() => {
            const original = btn.textContent;
            btn.textContent = '{{ __('Copied!') }}';
            setTimeout(() => btn.textContent = original, 1500);
        });
    }
</script>
@endsection
