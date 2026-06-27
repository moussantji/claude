@php($m3u = rtrim($order->iptv_dns, '/') . '/get.php?username=' . urlencode($order->iptv_username) . '&password=' . urlencode($order->iptv_password) . '&type=m3u_plus&output=ts')
<div class="space-y-3">
    <div>
        <label class="block text-xs font-medium text-slate-400 mb-1.5">{{ __('DNS / Server') }}</label>
        <input type="text" readonly value="{{ $order->iptv_dns }}"
            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2 text-sm text-slate-200 font-mono">
    </div>
    <div class="grid sm:grid-cols-2 gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1.5">{{ __('Username') }}</label>
            <input type="text" readonly value="{{ $order->iptv_username }}"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2 text-sm text-slate-200 font-mono">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1.5">{{ __('Password') }}</label>
            <input type="text" readonly value="{{ $order->iptv_password }}"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2 text-sm text-slate-200 font-mono">
        </div>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-400 mb-1.5">{{ __('M3U playlist link') }}</label>
        <input id="m3u-{{ $order->id }}" type="text" readonly value="{{ $m3u }}"
            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2 text-xs text-slate-200 font-mono">
    </div>
</div>
