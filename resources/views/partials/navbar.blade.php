@php($locale = app()->getLocale())
@php($unread = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0)
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
                    ['resellers', __('Resellers'), 'resellers'],
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
                    {{-- Notifications bell --}}
                    <a href="{{ route('notifications.index') }}" class="relative p-2 rounded-lg text-slate-300 hover:text-white hover:bg-white/5" aria-label="{{ __('Notifications') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        @if ($unread > 0)
                            <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center">{{ $unread > 9 ? '9+' : $unread }}</span>
                        @endif
                    </a>

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
