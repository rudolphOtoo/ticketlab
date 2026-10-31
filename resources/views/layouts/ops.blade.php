@php
    $opsUser = auth()->user();
    $opsLinks = [
        ['route' => 'ops.scanner', 'label' => 'Door Scanner', 'icon' => 'M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z'],
        ['route' => 'ops.cash-desk', 'label' => 'Cash Desk', 'icon' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v12.75m0-12.75h19.5m0 0v12.75M3.75 4.5h16.5M3.75 4.5A2.25 2.25 0 0 0 1.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25h16.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
    <title>{{ $title ?? 'Gate Terminal' }} — TicketLab Ops</title>
</head>
<body class="min-h-screen antialiased bg-[var(--bg-canvas)] text-[var(--text-primary)]">
    {{-- Ground Ops terminal shell: deliberately limited to the two door tools, with no administrative or ledger links. --}}
    <div class="flex min-h-screen flex-col">
        <header class="flex h-14 items-center justify-between gap-3 border-b border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 sm:px-6">
            <div class="flex min-w-0 items-center gap-2.5">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[11px] font-bold tracking-tight">TL</span>
                <div class="hidden min-w-0 flex-col sm:flex">
                    <span class="truncate text-xs font-semibold">{{ $opsUser?->organization?->name ?? 'TicketLab' }}</span>
                    <span class="truncate text-[11px] text-[var(--text-secondary)]">
                        Gate Terminal{{ $opsUser?->subdivision ? ' · ' . $opsUser->subdivision->name : '' }}
                    </span>
                </div>
            </div>

            <nav aria-label="Gate terminal tools" class="flex items-center gap-1 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] p-1 text-xs">
                @foreach ($opsLinks as $link)
                    <a
                        href="{{ route($link['route']) }}"
                        wire:navigate
                        @class([
                            'flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 font-medium transition',
                            'bg-[var(--bg-surface)] font-semibold text-[var(--text-primary)] shadow-xs' => request()->routeIs($link['route']),
                            'text-[var(--text-secondary)] hover:text-[var(--text-primary)]' => !request()->routeIs($link['route']),
                        ])
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}" />
                        </svg>
                        <span>{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="flex items-center gap-1.5">
                <button type="button"
                        onclick="toggleStudioTheme(event, null, this)"
                        class="cursor-pointer rounded-lg p-1.5 text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)] press-scale"
                        title="Toggle Light / Dark">
                    <svg class="size-4 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                    <svg class="hidden size-4 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                </button>

                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="cursor-pointer rounded-lg px-2 py-1.5 text-[11px] font-medium text-[var(--text-tertiary)] transition hover:text-[var(--status-danger)]">
                            Sign Out
                        </button>
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
