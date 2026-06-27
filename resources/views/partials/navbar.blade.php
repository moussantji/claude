@php($locale = app()->getLocale())
<nav class="sticky top-0 z-40 backdrop-blur bg-slate-950/80 border-b border-white/10">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <a href="{{ route('home') }}" class="flex items-center gap-2 group shrink-0">
                <span class="text-brand-400 text-xl sm:text-2xl font-black tracking-tight group-hover:text-brand-300 transition">◈ {{ config('app.name') }}</span>
            </a>

            {{-- Desktop links --}}
            <div class="hidden md:flex items-center gap-1">
                @php($links = [
                    ['home', __('Home'), 'home'],
                    ['movies.index', __('Movies'), 'movies.*'],
                    ['series.index', __('Series'), 'series.*'],
                    ['channels.index', __('Channels'), 'channels.*'],
                    ['packages.index', __('Pricing'), 'packages.*'],
                ])
                @foreach ($links as [$route, $label, $pattern])
                    <a href="{{ route($route) }}"
                       class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($pattern) ? 'text-white bg-white/5' : 'text-slate-300 hover:text-white hover:bg-white/5' }}">{{ $label }}</a>
                @endforeach
            </div>

            <div class="flex items-center gap-2">
                {{-- Language switcher EN / FR --}}
                <div class="flex items-center rounded-lg border border-white/10 bg-white/5 overflow-hidden text-xs font-bold">
                    <a href="{{ route('locale.switch', 'en') }}" class="px-2.5 py-1.5 {{ $locale === 'en' ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-white/10' }}">EN</a>
                    <a href="{{ route('locale.switch', 'fr') }}" class="px-2.5 py-1.5 {{ $locale === 'fr' ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-white/10' }}">FR</a>
                </div>

                @auth
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="hidden sm:inline px-3 py-2 rounded-md text-sm font-medium text-amber-300 hover:bg-white/5">{{ __('Admin') }}</a>
                    @endif
                    <a href="{{ route('dashboard') }}" class="hidden md:inline px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white hover:bg-white/5">{{ __('Dashboard') }}</a>
                    <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                        @csrf
                        <button type="submit" class="px-3 py-2 rounded-lg text-sm font-semibold bg-white/5 hover:bg-white/10 text-white border border-white/10">{{ __('Log out') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="hidden sm:inline px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white">{{ __('Log in') }}</a>
                    <a href="{{ route('register') }}" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-semibold bg-brand-600 hover:bg-brand-500 text-white shadow-lg shadow-brand-600/30">{{ __('Get Started') }}</a>
                @endauth
            </div>
        </div>
    </div>
</nav>
