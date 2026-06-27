@extends('layouts.admin')

@section('title', __('Categories'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-white">{{ __('Categories') }}</h1>
    <a href="{{ route('admin.categories.create') }}" class="rounded-lg bg-brand-600 hover:bg-brand-500 px-4 py-2 text-sm font-semibold text-white">+ {{ __('New Category') }}</a>
</div>

<div class="rounded-2xl border border-white/10 bg-slate-900/40 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-400 border-b border-white/10 bg-white/5">
                    <th class="py-3 px-4 font-medium">{{ __('Icon') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Name') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Slug') }}</th>
                    <th class="py-3 px-4 font-medium">{{ __('Channels') }}</th>
                    <th class="py-3 px-4 font-medium text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr class="border-b border-white/5">
                        <td class="py-3 px-4 text-xl">{{ $category->icon ?: '🗂' }}</td>
                        <td class="py-3 px-4 text-white font-medium">{{ $category->name }}</td>
                        <td class="py-3 px-4 text-slate-500 font-mono text-xs">{{ $category->slug }}</td>
                        <td class="py-3 px-4 text-slate-300">{{ $category->channels_count }}</td>
                        <td class="py-3 px-4 text-right whitespace-nowrap">
                            <a href="{{ route('admin.categories.edit', $category) }}" class="text-brand-400 hover:text-brand-300">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="inline ml-3" onsubmit="return confirm('{{ __('Delete this category? Channels will be uncategorised.') }}');">
                                @csrf @method('DELETE')
                                <button class="text-rose-400 hover:text-rose-300">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">{{ __('No categories yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
