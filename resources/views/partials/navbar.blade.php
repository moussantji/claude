<nav class="sticky top-0 z-40 backdrop-blur bg-slate-950/80 border-b border-white/10">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <a href="{{ route('home') }}" class="flex items-center gap-2 group">
                <span class="text-brand-400 text-2xl font-black tracking-tight group-hover:text-brand-300 transition">◈ {{ config('app.name') }}</span>
            </a>

            <div class="hidden md:flex items-center gap-1">
                <a href="{{ route('home') }}" class="px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white hover:bg-white/5">Home</a>
                <a href="{{ route('packages.index') }}" class="px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white hover:bg-white/5">Pricing</a>
                <a href="{{ route('channels.index') }}" class="px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white hover:bg-white/5">Channels</a>
            </div>

            <div class="flex items-center gap-2">
                @auth
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="px-3 py-2 rounded-md text-sm font-medium text-amber-300 hover:bg-white/5">Admin</a>
                    @endif
                    <a href="{{ route('dashboard') }}" class="px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white hover:bg-white/5">Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-white/5 hover:bg-white/10 text-white border border-white/10">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white">Log in</a>
                    <a href="{{ route('register') }}" class="px-4 py-2 rounded-lg text-sm font-semibold bg-brand-600 hover:bg-brand-500 text-white shadow-lg shadow-brand-600/30">Get Started</a>
                @endauth
            </div>
        </div>
    </div>
</nav>
