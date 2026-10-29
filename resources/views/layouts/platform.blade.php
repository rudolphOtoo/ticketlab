<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
    <title>{{ $title ?? 'Platform' }} — TicketLab Platform</title>
</head>
<body class="min-h-screen antialiased bg-[var(--bg-canvas)] text-[var(--text-primary)]">
    {{-- Super admin shell: cross-tenant oversight only, no tenant event tooling. --}}
    <div class="flex min-h-screen flex-col">
        <header class="flex h-14 items-center justify-between gap-3 border-b border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 sm:px-6">
            <a href="{{ route('platform.dashboard') }}" class="flex items-center gap-2.5" wire:navigate>
                <span class="flex size-8 items-center justify-center rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[11px] font-bold">TL</span>
                <span class="flex flex-col">
                    <span class="text-xs font-semibold">TicketLab Platform</span>
                    <span class="text-[11px] text-[var(--text-secondary)]">Super Admin Console</span>
                </span>
            </a>

            <div class="flex items-center gap-1.5">
                <a href="{{ route('admin.queue-monitor') }}" wire:navigate class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-[var(--text-secondary)] transition hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]">Queue Monitor</a>
                <button type="button"
                        onclick="toggleStudioTheme(event, null, this)"
                        class="cursor-pointer rounded-lg p-1.5 text-[var(--text-secondary)] transition-transform duration-100 hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)] active:scale-90"
                        title="Toggle Light / Dark">
                    <svg class="size-4 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                    <svg class="hidden size-4 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                </button>
                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="cursor-pointer rounded-lg px-2 py-1.5 text-[11px] font-medium text-[var(--text-tertiary)] transition hover:text-[var(--status-danger)]">Sign Out</button>
                    </form>
                @endauth
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 p-4 sm:p-5 md:p-6">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
