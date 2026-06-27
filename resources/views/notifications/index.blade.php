@extends('layouts.app')

@section('title', __('Notifications'))

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10" data-animate>
    <h1 class="text-2xl font-bold text-white mb-6">{{ __('Notifications') }}</h1>

    <div class="space-y-2">
        @forelse ($notifications as $notification)
            @php($data = $notification->data)
            <a href="{{ route('notifications.read', $notification->id) }}"
               class="block rounded-xl border px-4 py-3 transition {{ $notification->read_at ? 'border-white/10 bg-slate-900/40' : 'border-brand-500/40 bg-brand-600/10' }}">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm text-white">{{ $data['message'] ?? __('Notification') }}</p>
                    @unless ($notification->read_at)<span class="mt-1 h-2 w-2 rounded-full bg-brand-400 shrink-0"></span>@endunless
                </div>
                <p class="text-xs text-slate-500 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
            </a>
        @empty
            <div class="text-center py-16">
                <div class="text-4xl mb-3">🔔</div>
                <p class="text-slate-400">{{ __('No notifications yet.') }}</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $notifications->links() }}</div>
</div>
@endsection
