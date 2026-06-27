@extends('layouts.admin')

@section('title', 'Categories')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-white">Categories</h1>
    <a href="{{ route('admin.categories.create') }}" class="rounded-lg bg-brand-600 hover:bg-brand-500 px-4 py-2 text-sm font-semibold text-white">+ New Category</a>
</div>

<div class="rounded-2xl border border-white/10 bg-slate-900/40 overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-400 border-b border-white/10 bg-white/5">
                <th class="py-3 px-4 font-medium">Icon</th>
                <th class="py-3 px-4 font-medium">Name</th>
                <th class="py-3 px-4 font-medium">Slug</th>
                <th class="py-3 px-4 font-medium">Channels</th>
                <th class="py-3 px-4 font-medium text-right">Actions</th>
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
                        <a href="{{ route('admin.categories.edit', $category) }}" class="text-brand-400 hover:text-brand-300">Edit</a>
                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="inline ml-3" onsubmit="return confirm('Delete this category? Channels will be uncategorised.');">
                            @csrf @method('DELETE')
                            <button class="text-rose-400 hover:text-rose-300">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-8 text-center text-slate-500">No categories yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
