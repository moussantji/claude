@extends('layouts.app')

@section('title', __('Channel Lineup'))

@section('content')
<section class="relative hero-grid border-b border-white/10 overflow-hidden">
    <div class="absolute inset-0 bg-cover bg-center opacity-20" style="background-image:url('{{ config('streamwave.images.channels') }}')"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-slate-950/60 to-slate-950"></div>
    <div class="relative max-w-7xl mx-auto px-4 py-16 text-center" data-animate>
        <h1 class="text-4xl md:text-5xl font-black text-white">{{ __('Channel lineup') }}</h1>
        <p class="mt-4 text-lg text-slate-400 max-w-2xl mx-auto">
            {{ __('Explore channels across every category. New channels are added every week.') }}
        </p>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 py-12" data-animate>
    <livewire:channel-browser />
</section>
@endsection
