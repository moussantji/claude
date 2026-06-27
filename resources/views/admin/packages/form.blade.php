@extends('layouts.admin')

@section('title', $package->exists ? __('Edit Package') : __('New Package'))

@section('content')
@php($isEdit = $package->exists)
<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.packages.index') }}" class="text-slate-400 hover:text-white">←</a>
    <h1 class="text-2xl font-bold text-white">{{ $isEdit ? __('Edit Package') : __('New Package') }}</h1>
</div>

<form method="POST" action="{{ $isEdit ? route('admin.packages.update', $package) : route('admin.packages.store') }}"
      class="max-w-2xl space-y-5 rounded-2xl border border-white/10 bg-slate-900/40 p-6">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Name') }}</label>
        <input name="name" value="{{ old('name', $package->name) }}" required
            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Description') }}</label>
        <textarea name="description" rows="2"
            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">{{ old('description', $package->description) }}</textarea>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Price (USD)') }}</label>
            <input name="price" type="number" step="0.01" min="0" value="{{ old('price', $package->price) }}" required
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Duration (days)') }}</label>
            <input name="duration_days" type="number" min="1" value="{{ old('duration_days', $package->duration_days) }}" required
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Channel count') }}</label>
            <input name="channel_count" type="number" min="0" value="{{ old('channel_count', $package->channel_count) }}" required
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Max connections') }}</label>
            <input name="max_connections" type="number" min="1" max="100" value="{{ old('max_connections', $package->max_connections) }}" required
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Features') }} <span class="text-slate-500">{{ __('(one per line)') }}</span></label>
        <textarea name="features" rows="4" placeholder="4K Ultra HD&#10;VOD Library&#10;EPG Guide"
            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">{{ old('features', collect($package->features ?? [])->implode("\n")) }}</textarea>
    </div>

    <div class="flex gap-6">
        <label class="flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $package->is_active) ? 'checked' : '' }}
                class="rounded border-white/20 bg-slate-950 text-brand-600 focus:ring-brand-500/40">
            {{ __('Active (visible on site)') }}
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $package->is_featured) ? 'checked' : '' }}
                class="rounded border-white/20 bg-slate-950 text-brand-600 focus:ring-brand-500/40">
            {{ __('Featured (most popular)') }}
        </label>
    </div>

    <div class="flex gap-3 pt-2">
        <button class="rounded-lg bg-brand-600 hover:bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white">{{ $isEdit ? __('Save changes') : __('Create package') }}</button>
        <a href="{{ route('admin.packages.index') }}" class="rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 px-5 py-2.5 text-sm text-white">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
