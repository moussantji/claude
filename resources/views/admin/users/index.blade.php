@extends('layouts.admin')

@section('title', __('Users'))

@section('content')
<div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
    <h1 class="text-2xl font-bold text-white">{{ __('Users') }}</h1>
    <form method="GET" action="{{ route('admin.users.index') }}">
        <input name="search" value="{{ $search }}" placeholder="{{ __('Search name or email…') }}"
            class="rounded-lg bg-slate-950 border border-white/10 px-3 py-2 text-sm text-white focus:border-brand-500 outline-none w-64 max-w-full">
    </form>
</div>

<div class="rounded-2xl border border-white/10 bg-slate-900/40 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-400 border-b border-white/10 bg-white/5">
                    <th class="py-3 px-4 font-medium">{{ __('Name') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Email') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Orders') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Role') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Joined') }}</th>
                    <th class="py-3 px-4 font-medium text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="border-b border-white/5">
                        <td class="py-3 px-4 text-white font-medium">{{ $user->name }}</td>
                        <td class="py-3 px-4 text-slate-300">{{ $user->email }}</td>
                        <td class="py-3 px-4 text-slate-300">{{ $user->orders_count }}</td>
                        <td class="py-3 px-4">
                            <span class="text-xs font-bold px-2 py-0.5 rounded {{ $user->is_admin ? 'bg-amber-500/20 text-amber-300' : 'bg-slate-500/20 text-slate-400' }}">
                                {{ $user->is_admin ? __('Admin') : __('Member') }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-slate-500">{{ $user->created_at->format('M j, Y') }}</td>
                        <td class="py-3 px-4 text-right">
                            <form method="POST" action="{{ route('admin.users.toggle-admin', $user) }}" class="inline">
                                @csrf @method('PATCH')
                                <button class="text-brand-400 hover:text-brand-300">{{ $user->is_admin ? __('Revoke admin') : __('Make admin') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">{{ __('No users found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $users->links() }}</div>
@endsection
