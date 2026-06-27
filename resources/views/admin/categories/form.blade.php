@extends('layouts.admin')

@section('title', $category->exists ? __('Edit Category') : __('New Category'))

@section('content')
@php($isEdit = $category->exists)
<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.categories.index') }}" class="text-slate-400 hover:text-white">←</a>
    <h1 class="text-2xl font-bold text-white">{{ $isEdit ? __('Edit Category') : __('New Category') }}</h1>
</div>

<form method="POST" action="{{ $isEdit ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
      class="max-w-lg space-y-5 rounded-2xl border border-white/10 bg-slate-900/40 p-6">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Name') }}</label>
        <input name="name" value="{{ old('name', $category->name) }}" required
            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Icon') }} <span class="text-slate-500">{{ __('(emoji)') }}</span></label>
        <input name="icon" value="{{ old('icon', $category->icon) }}" maxlength="16" placeholder="🎬"
            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
    </div>

    <div class="flex gap-3 pt-2">
        <button class="rounded-lg bg-brand-600 hover:bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white">{{ $isEdit ? __('Save changes') : __('Create category') }}</button>
        <a href="{{ route('admin.categories.index') }}" class="rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 px-5 py-2.5 text-sm text-white">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
