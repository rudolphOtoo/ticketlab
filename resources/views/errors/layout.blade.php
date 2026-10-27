<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[var(--bg-canvas)] text-[var(--text-primary)] antialiased flex flex-col justify-between selection:bg-[var(--color-primary)] selection:text-[var(--color-primary-fg)] relative overflow-hidden">
        
        <!-- Ambient Radial Glow -->
        <div class="pointer-events-none absolute inset-0 -z-10 flex items-center justify-center opacity-40 dark:opacity-20">
            <div class="size-[32rem] rounded-full bg-gradient-to-tr from-amber-500/20 via-orange-500/15 to-transparent blur-3xl"></div>
        </div>

        <!-- Top Navigation Bar -->
        <header class="w-full max-w-5xl mx-auto px-6 py-6 flex items-center justify-between">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 group cursor-pointer focus-ring rounded-lg">
                <div class="size-9 rounded-xl bg-[var(--bg-surface)] border border-[var(--border-hairline)] flex items-center justify-center shadow-xs group-hover:border-[var(--border-focused)] transition-colors">
                    <x-app-logo-icon class="size-5 text-[var(--text-primary)]" />
                </div>
                <span class="text-sm font-bold tracking-tight text-[var(--text-primary)]">
                    {{ config('app.name', 'TicketLab') }}
                </span>
            </a>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    onclick="window.toggleTheme ? window.toggleTheme(event) : document.documentElement.classList.toggle('dark')"
                    class="p-2 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors press-scale cursor-pointer"
                    aria-label="Toggle theme"
                >
                    <svg class="size-4.5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    <svg class="size-4.5 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </button>

                @auth
                    <a href="{{ route('dashboard') }}" class="text-xs font-semibold px-3 py-1.5 rounded-md border border-[var(--border-hairline)] bg-[var(--bg-surface)] hover:bg-[var(--bg-subtle)] text-[var(--text-primary)] transition press-scale">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-xs font-semibold px-3 py-1.5 rounded-md border border-[var(--border-hairline)] bg-[var(--bg-surface)] hover:bg-[var(--bg-subtle)] text-[var(--text-primary)] transition press-scale">
                        Sign In
                    </a>
                @endauth
            </div>
        </header>

        <!-- Main Hero Section -->
        <main class="w-full max-w-xl mx-auto px-6 py-12 flex flex-col items-center text-center my-auto">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold tracking-wider uppercase border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-secondary)] shadow-xs mb-6">
                <span class="size-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                @yield('code', 'Notice')
            </div>

            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-[var(--text-primary)] mb-4">
                @yield('title', 'Something went wrong')
            </h1>

            <p class="text-sm sm:text-base text-[var(--text-secondary)] leading-relaxed max-w-md mb-8">
                @yield('message', 'The requested resource could not be found or processed.')
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 w-full sm:w-auto">
                <a
                    href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}"
                    class="w-full sm:w-auto px-5 py-2.5 rounded-field border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-primary)] text-sm font-semibold hover:bg-[var(--bg-subtle)] transition shadow-xs press-scale text-center cursor-pointer"
                >
                    ← Go Back
                </a>

                @auth
                    <a
                        href="{{ route('dashboard') }}"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-field bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-sm font-semibold hover:opacity-90 transition shadow-xs press-scale text-center cursor-pointer"
                    >
                        Operations Hub
                    </a>
                @else
                    <a
                        href="{{ url('/') }}"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-field bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-sm font-semibold hover:opacity-90 transition shadow-xs press-scale text-center cursor-pointer"
                    >
                        TicketLab Home
                    </a>
                @endauth
            </div>
        </main>

        <!-- Footer -->
        <footer class="w-full max-w-5xl mx-auto px-6 py-8 text-center text-xs text-[var(--text-tertiary)] border-t border-[var(--border-hairline)]">
            <p>TicketLab Universal Event & Ticketing Platform · Diagnostic Code: @yield('code', 'ERR')</p>
        </footer>
    </body>
</html>
