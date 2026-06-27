<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Stream Everything') — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc',
                            400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca',
                            800: '#3730a3', 900: '#312e81',
                        },
                    },
                },
            },
        }
    </script>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .hero-grid {
            background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0);
            background-size: 28px 28px;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen flex flex-col">
    @include('partials.navbar')

    <main class="flex-1">
        @if (session('status'))
            <div class="max-w-7xl mx-auto px-4 mt-4">
                <div class="rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 text-sm">
                    {{ session('status') }}
                </div>
            </div>
        @endif
        @if (session('error'))
            <div class="max-w-7xl mx-auto px-4 mt-4">
                <div class="rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 px-4 py-3 text-sm">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="border-t border-white/10 bg-slate-950">
        <div class="max-w-7xl mx-auto px-4 py-10 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <span class="text-brand-400 text-xl font-black tracking-tight">◈ {{ config('app.name') }}</span>
            </div>
            <p class="text-sm text-slate-500">&copy; {{ date('Y') }} {{ config('app.name') }}. For demonstration purposes only.</p>
            <div class="flex gap-5 text-sm text-slate-400">
                <a href="{{ route('packages.index') }}" class="hover:text-white">Pricing</a>
                <a href="{{ route('channels.index') }}" class="hover:text-white">Channels</a>
            </div>
        </div>
    </footer>
</body>
</html>
