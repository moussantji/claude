<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — {{ config('app.name') }} Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brand: {
            400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca',
        } } } } }
    </script>
</head>
<body class="bg-slate-950 text-slate-100 antialiased">
<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <aside class="hidden md:flex w-64 flex-col border-r border-white/10 bg-slate-900/50 fixed inset-y-0">
        <div class="h-16 flex items-center px-6 border-b border-white/10">
            <a href="{{ route('admin.dashboard') }}" class="text-brand-400 text-xl font-black">◈ {{ config('app.name') }}</a>
        </div>
        <nav class="flex-1 px-3 py-4 space-y-1">
            @php($nav = [
                ['admin.dashboard', '▦ Dashboard', null],
                ['admin.packages.index', '🏷 Packages', 'admin.packages.*'],
                ['admin.channels.index', '📺 Channels', 'admin.channels.*'],
                ['admin.categories.index', '🗂 Categories', 'admin.categories.*'],
                ['admin.subscriptions.index', '🧾 Subscriptions', 'admin.subscriptions.*'],
                ['admin.users.index', '👥 Users', 'admin.users.*'],
            ])
            @foreach ($nav as [$route, $label, $pattern])
                @php($isActive = $pattern ? request()->routeIs($pattern) : request()->routeIs($route))
                <a href="{{ route($route) }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ $isActive ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
        <div class="border-t border-white/10 p-3 space-y-1">
            <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 hover:bg-white/5 hover:text-white">↩ View site</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="w-full text-left flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 hover:bg-white/5 hover:text-white">⎋ Log out</button>
            </form>
        </div>
    </aside>

    {{-- Content --}}
    <div class="flex-1 md:ml-64">
        <header class="md:hidden h-16 flex items-center justify-between px-4 border-b border-white/10 bg-slate-900/50">
            <a href="{{ route('admin.dashboard') }}" class="text-brand-400 font-black">◈ Admin</a>
            <a href="{{ route('home') }}" class="text-sm text-slate-300">View site</a>
        </header>

        <main class="p-4 md:p-8 max-w-6xl">
            @if (session('status'))
                <div class="mb-6 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-6 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 px-4 py-3 text-sm">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-6 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 px-4 py-3 text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
