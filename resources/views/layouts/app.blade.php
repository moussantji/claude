<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Stream Everything')) — {{ config('app.name') }}</title>
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
        /* Scroll reveal animations */
        [data-animate] {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity .7s cubic-bezier(.16,1,.3,1), transform .7s cubic-bezier(.16,1,.3,1);
            will-change: opacity, transform;
        }
        [data-animate].in-view {
            opacity: 1;
            transform: none;
        }
        @media (prefers-reduced-motion: reduce) {
            [data-animate] { opacity: 1; transform: none; transition: none; }
        }
        .float-pulse { animation: floatPulse 2.5s ease-in-out infinite; }
        @keyframes floatPulse {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }
    </style>
    @livewireStyles
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen flex flex-col pb-16 md:pb-0">
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

    @include('partials.footer')

    {{-- Mobile app-style bottom navigation --}}
    @include('partials.mobile-nav')

    {{-- Floating WhatsApp contact button --}}
    @include('partials.whatsapp')

    @livewireScripts

    {{-- Scroll reveal via IntersectionObserver --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const reveal = (els) => {
                const observer = new IntersectionObserver((entries, obs) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('in-view');
                            obs.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
                els.forEach((el, i) => {
                    el.style.transitionDelay = Math.min(i * 60, 240) + 'ms';
                    observer.observe(el);
                });
            };
            reveal(Array.from(document.querySelectorAll('[data-animate]')));
        });
    </script>
</body>
</html>
