@extends('layouts.admin')

@section('title', $method->exists ? __('Edit method') : __('New method'))

@section('content')
@php($isEdit = $method->exists)
<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.payment-methods.index') }}" class="text-slate-400 hover:text-white">←</a>
    <h1 class="text-2xl font-bold text-white">{{ $isEdit ? __('Edit method') : __('New method') }}</h1>
</div>

<form method="POST" action="{{ $isEdit ? route('admin.payment-methods.update', $method) : route('admin.payment-methods.store') }}"
      enctype="multipart/form-data" class="max-w-2xl space-y-5 rounded-2xl border border-white/10 bg-slate-900/40 p-6">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Name') }}</label>
            <input name="name" value="{{ old('name', $method->name) }}" required placeholder="Orange Money"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Account number') }}</label>
            <input name="account_number" value="{{ old('account_number', $method->account_number) }}" placeholder="07 00 00 00 00"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Instructions / procedure') }}</label>
        <textarea name="instructions" rows="5" placeholder="{{ __('Step-by-step instructions shown to the customer…') }}"
            class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">{{ old('instructions', $method->instructions) }}</textarea>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Sort order') }}</label>
            <input name="sort_order" type="number" min="0" value="{{ old('sort_order', $method->sort_order ?? 0) }}"
                class="w-full rounded-lg bg-slate-950 border border-white/10 px-3 py-2.5 text-white focus:border-brand-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('QR code image') }}</label>
            <input name="qr" type="file" accept="image/*"
                class="w-full text-sm text-slate-300 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-600 file:px-4 file:py-2 file:text-white">
            @if ($isEdit && $method->qrUrl())
                <img src="{{ $method->qrUrl() }}" alt="QR" class="mt-2 h-20 w-20 rounded-lg bg-white p-1 object-contain">
            @endif
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm text-slate-300">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $method->is_active) ? 'checked' : '' }}
            class="rounded border-white/20 bg-slate-950 text-brand-600 focus:ring-brand-500/40">
        {{ __('Active (shown at checkout)') }}
    </label>

    <div class="flex gap-3 pt-2">
        <button class="rounded-lg bg-brand-600 hover:bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white">{{ $isEdit ? __('Save changes') : __('Create') }}</button>
        <a href="{{ route('admin.payment-methods.index') }}" class="rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 px-5 py-2.5 text-sm text-white">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
