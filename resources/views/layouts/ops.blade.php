<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
    <title>{{ $title ?? 'Operations' }} — TicketLab Studio</title>
</head>
<body class="min-h-screen antialiased bg-[var(--bg-canvas)] text-[var(--text-primary)]">
    <div class="flex min-h-screen">
        <!-- Desktop Sidebar -->
        <aside class="hidden lg:flex w-56 xl:w-60 flex-col justify-between border-r border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shrink-0">
            <div class="flex flex-col gap-6">
                <!-- Org / Event Header -->
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 px-1" wire:navigate>
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--bg-subtle)] border border-[var(--border-hairline)] text-xs font-bold tracking-tight">
                        TL
                    </span>
                    <div class="flex flex-col min-w-0">
                        <span class="text-xs font-semibold tracking-tight text-[var(--text-primary)] truncate">Summit 2026</span>
                        <span class="text-[11px] text-[var(--text-secondary)] truncate">Gate Operations</span>
                    </div>
                </a>

                <!-- Nav Links -->
                <nav class="flex flex-col gap-1 text-xs">
                    <span class="px-2 pb-1.5 text-[10px] font-semibold tracking-wider text-[var(--text-tertiary)] uppercase">Operations</span>

                    <a href="{{ url('/admin/sessions') }}"
                       class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 font-medium transition {{ request()->is('admin/sessions*') ? 'bg-[var(--bg-subtle)] text-[var(--text-primary)] font-semibold' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' }}"
                       wire:navigate>
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.143 2.143L15.429 8" />
                        </svg>
                        Financial Ledger
                    </a>

                    <a href="{{ url('/ops/scanner') }}"
                       class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 font-medium transition {{ request()->is('ops/scanner*') ? 'bg-[var(--bg-subtle)] text-[var(--text-primary)] font-semibold' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' }}"
                       wire:navigate>
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" />
                        </svg>
                        Door Scanner
                    </a>

                    <a href="{{ url('/ops/cash-desk') }}"
                       class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 font-medium transition {{ request()->is('ops/cash-desk*') ? 'bg-[var(--bg-subtle)] text-[var(--text-primary)] font-semibold' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' }}"
                       wire:navigate>
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v12.75m0-12.75h19.5m0 0v12.75M3.75 4.5h16.5M3.75 4.5A2.25 2.25 0 0 0 1.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25h16.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25" />
                        </svg>
                        Cash Desk
                    </a>

                    <a href="{{ url('/admin/attendees') }}"
                       class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 font-medium transition {{ request()->is('admin/attendees*') ? 'bg-[var(--bg-subtle)] text-[var(--text-primary)] font-semibold' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' }}"
                       wire:navigate>
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                        Attendee Roster
                    </a>
                </nav>
            </div>

            <!-- Bottom User & Theme Strip -->
            <div class="flex flex-col gap-2.5 pt-4 border-t border-[var(--border-hairline)]">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="flex size-7 items-center justify-center rounded-lg bg-[var(--bg-subtle)] text-[11px] font-semibold">
                            {{ auth()->check() ? substr(auth()->user()->name, 0, 2) : 'OP' }}
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs font-medium truncate max-w-[100px]">{{ auth()->check() ? auth()->user()->name : 'Gate Steward' }}</span>
                            <span class="text-[10px] text-[var(--text-tertiary)]">Zone 03 Kumasi</span>
                        </div>
                    </div>

                    <!-- Theme Switcher Button -->
                    <button type="button"
                            onclick="toggleStudioTheme(event, null, this)"
                            class="cursor-pointer rounded-lg p-1.5 text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)] active:scale-90 transition-transform duration-100"
                            title="Toggle Light / Dark">
                        <svg class="size-3.5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                        <svg class="size-3.5 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    </button>
                </div>

                @auth
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <button type="submit" class="w-full text-left text-[11px] text-[var(--text-tertiary)] hover:text-[var(--status-danger)] transition">
                            Sign Out
                        </button>
                    </form>
                @endauth
            </div>
        </aside>

        <!-- Main Wrapper -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top App Bar -->
            <header class="flex h-13 sm:h-14 items-center justify-between border-b border-[var(--border-hairline)] bg-[var(--bg-surface)] px-4 sm:px-6">
                <!-- Breadcrumbs -->
                <div class="flex items-center gap-1.5 text-[11px] sm:text-xs text-[var(--text-secondary)]">
                    <span class="font-medium text-[var(--text-primary)]">TicketLab Studio</span>
                    <span>/</span>
                    <span>Events</span>
                    <span>/</span>
                    <span>Summit 2026</span>
                    <span>/</span>
                    <span class="text-[var(--text-primary)] font-medium truncate">{{ $heading ?? 'Financial Closeout & Attendance' }}</span>
                </div>

                <!-- Live Operational Telemetry -->
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-[var(--border-hairline)] bg-[var(--status-success-bg)] px-2 py-0.5 text-[10px] sm:text-[11px] font-medium text-[var(--status-success)]">
                        <span class="size-1.5 rounded-full bg-[var(--status-success)] animate-pulse"></span>
                        Live Sync
                    </span>

                    <!-- Theme Toggle Button (Mobile & Desktop Header) -->
                    <button type="button"
                            onclick="toggleStudioTheme(event, null, this)"
                            class="cursor-pointer rounded-lg p-1.5 text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)] active:scale-90 transition-transform duration-100"
                            title="Toggle Light / Dark">
                        <svg class="size-3.5 sm:size-4 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                        <svg class="size-3.5 sm:size-4 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    </button>

                    <a href="{{ url('/ops/scanner') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-2.5 py-1 text-xs font-medium hover:bg-[var(--bg-hover)] active:scale-95 transition" wire:navigate>
                        Scan Pass
                    </a>
                </div>
            </header>

            <!-- Main Page Content Slot -->
            <main class="flex-1 p-4 sm:p-5 md:p-6 max-w-7xl w-full mx-auto">
                {{ $slot }}
            </main>
        </div>
    </div>

    <!-- Fluid Theme View Transition Script -->
    <script>
        function toggleStudioTheme(event, targetTheme = null, triggerEl = null) {
            // Exact physical button center from explicit trigger element, event target, or currentTarget
            const btn = triggerEl || event?.target?.closest('button') || event?.currentTarget;
            const rect = btn?.getBoundingClientRect();
            const x = rect ? Math.round(rect.left + rect.width / 2) : Math.round(event?.clientX ?? window.innerWidth / 2);
            const y = rect ? Math.round(rect.top + rect.height / 2) : Math.round(event?.clientY ?? window.innerHeight / 2);

            const isDark = document.documentElement.classList.contains('dark');
            const nextTheme = targetTheme ?? (isDark ? 'light' : 'dark');

            if (targetTheme && ((targetTheme === 'dark' && isDark) || (targetTheme === 'light' && !isDark))) {
                return;
            }

            const isGoingDark = nextTheme === 'dark';
            document.documentElement.setAttribute('data-theme-transition', isGoingDark ? 'to-dark' : 'to-light');

            const commitThemeChange = () => {
                if (isGoingDark) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
                localStorage.setItem('theme', nextTheme);
                document.dispatchEvent(new CustomEvent('theme-changed', {
                    detail: { theme: nextTheme }
                }));
            };

            if (!document.startViewTransition || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                commitThemeChange();
                document.documentElement.removeAttribute('data-theme-transition');
                return;
            }

            // Furthest distance to any viewport corner with 5% over-bleed buffer
            const endRadius = Math.ceil(
                Math.hypot(
                    Math.max(x, window.innerWidth - x),
                    Math.max(y, window.innerHeight - y)
                ) * 1.05
            );

            const transition = document.startViewTransition(() => {
                commitThemeChange();
            });

            transition.ready.then(() => {
                if (isGoingDark) {
                    // Outward wave: Dark wave expands smoothly outward from the button
                    document.documentElement.animate(
                        [
                            { clipPath: `circle(0px at ${x}px ${y}px)` },
                            { clipPath: `circle(${endRadius}px at ${x}px ${y}px)` }
                        ],
                        {
                            duration: 460,
                            easing: 'cubic-bezier(0.25, 1, 0.5, 1)',
                            pseudoElement: '::view-transition-new(root)'
                        }
                    );
                } else {
                    // Reverse animation: Dark sheet smoothly contracts back down into the button
                    document.documentElement.animate(
                        [
                            { clipPath: `circle(${endRadius}px at ${x}px ${y}px)` },
                            { clipPath: `circle(0px at ${x}px ${y}px)` }
                        ],
                        {
                            duration: 420,
                            easing: 'cubic-bezier(0.5, 0, 0.75, 1)',
                            pseudoElement: '::view-transition-old(root)'
                        }
                    );
                }
            });

            transition.finished.finally(() => {
                document.documentElement.removeAttribute('data-theme-transition');
            });
        }
        window.toggleStudioTheme = toggleStudioTheme;
    </script>
</body>
</html>
