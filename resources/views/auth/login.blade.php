@extends('layouts.app')

@section('title', __('Log In'))

@section('content')
<div class="min-h-[80vh] flex items-center justify-center px-4 py-12 hero-grid">
    <div class="w-full max-w-md" data-animate>
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold">{{ __('Welcome back') }}</h1>
            <p class="text-slate-400 mt-2">{{ __('Log in to manage your subscription.') }}</p>
        </div>

        <div class="bg-slate-900/60 border border-white/10 rounded-2xl p-8 shadow-2xl">
            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                        class="w-full rounded-lg bg-slate-950 border border-white/10 px-3.5 py-2.5 text-white placeholder-slate-500 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/40 outline-none"
                        placeholder="you@example.com">
                    @error('email')<p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-300 mb-1.5">{{ __('Password') }}</label>
                    <input id="password" name="password" type="password" required
                        class="w-full rounded-lg bg-slate-950 border border-white/10 px-3.5 py-2.5 text-white placeholder-slate-500 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/40 outline-none"
                        placeholder="••••••••">
                    @error('password')<p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>@enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-400">
                    <input type="checkbox" name="remember" class="rounded border-white/20 bg-slate-950 text-brand-600 focus:ring-brand-500/40">
                    {{ __('Remember me') }}
                </label>

                <button type="submit" class="w-full rounded-lg bg-brand-600 hover:bg-brand-500 text-white font-semibold py-2.5 transition shadow-lg shadow-brand-600/30">
                    {{ __('Log in') }}
                </button>
            </form>
        </div>

        <p class="text-center text-sm text-slate-400 mt-6">
            {{ __("Don't have an account?") }}
            <a href="{{ route('register') }}" class="text-brand-400 hover:text-brand-300 font-medium">{{ __('Create one') }}</a>
        </p>
    </div>
</div>
@endsection
