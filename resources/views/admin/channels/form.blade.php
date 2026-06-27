@extends('layouts.admin')

@section('title', $channel->exists ? 'Edit Channel' : 'New Channel')

@section('content')
@php($isEdit = $channel->exists)
<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.channels.index') }}" class="text-slate-400 hover:text-white">←</a>
    <h1 class="text-2xl font-bold text-white">{{ $isEdit ? 'Edit Channel' : 'New Channel' }}</h1>
</div>

<form method="POST" action="{{ $isEdit ? route('admin.channels.update', $channel) : route('admin.channels.store') }}"
      class="max-w-2xl space-y-5 rounded-2xl border border-white/10 bg-slate-900/40 p-6">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Name</label>
            <input name="name" value="{{ old('name', $channel->name) }}" required
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Category</label>
            <select name="category_id"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
                <option value="">— Uncategorised —</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" {{ (string) old('category_id', $channel->category_id) === (string) $category->id ? 'selected' : '' }}>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Quality</label>
            <select name="quality"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
                @foreach (['SD', 'HD', 'FHD', '4K'] as $q)
                    <option value="{{ $q }}" {{ old('quality', $channel->quality) === $q ? 'selected' : '' }}>{{ $q }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Country</label>
            <input name="country" value="{{ old('country', $channel->country) }}" placeholder="USA"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">Logo URL</label>
        <input name="logo_url" type="url" value="{{ old('logo_url', $channel->logo_url) }}" placeholder="https://…"
            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">Stream URL</label>
        <input name="stream_url" value="{{ old('stream_url', $channel->stream_url) }}" placeholder="http://…/stream.m3u8"
            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white font-mono text-sm focus:border-brand-500 outline-none">
    </div>

    <label class="flex items-center gap-2 text-sm text-slate-300">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $channel->is_active) ? 'checked' : '' }}
            class="rounded border-white/20 bg-slate-950 text-brand-600 focus:ring-brand-500/40">
        Active (included in playlists)
    </label>

    <div class="flex gap-3 pt-2">
        <button class="rounded-lg bg-brand-600 hover:bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white">{{ $isEdit ? 'Save changes' : 'Create channel' }}</button>
        <a href="{{ route('admin.channels.index') }}" class="rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 px-5 py-2.5 text-sm text-white">Cancel</a>
    </div>
</form>
@endsection
