@php
    $adminUser = auth()->user();
    $adminNav = [
        'Command' => [
            ['route' => 'admin.index', 'label' => 'Overview', 'icon' => 'm2.25 12 8.954-8.955a1.126 1.126 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25'],
            ['route' => 'admin.events', 'label' => 'Events', 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5'],
            ['route' => 'admin.studio', 'label' => 'Customizer Studio', 'icon' => 'M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42'],
            ['route' => 'admin.tickets', 'label' => 'Tickets & Pricing', 'icon' => 'M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z'],
            ['route' => 'admin.studio.marketing', 'label' => 'Marketing Studio', 'icon' => 'm2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z'],
        ],
        'Audience' => [
            ['route' => 'admin.attendees', 'label' => 'Attendee Roster', 'icon' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z'],
            ['route' => 'admin.broadcasts', 'label' => 'Broadcast Hub', 'icon' => 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75'],
        ],
        'Finance' => [
            ['route' => 'admin.sessions', 'label' => 'Financial Ledger', 'icon' => 'M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.143 2.143L15.429 8'],
            ['route' => 'admin.exports', 'label' => 'Data Exports', 'icon' => 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3'],
        ],
        'System' => array_values(array_filter([
            $adminUser?->canManageTeam()
                ? ['route' => 'admin.users', 'label' => 'Staff & Ushers', 'icon' => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z']
                : null,
            ['route' => 'admin.queue-monitor', 'label' => 'Queue Monitor', 'icon' => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z'],
        ])),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
    <title>{{ $title ?? 'Admin' }} — TicketLab Admin</title>
</head>
<body class="min-h-screen antialiased bg-[var(--bg-canvas)] text-[var(--text-primary)]" x-data="{ navOpen: false }">
    <div class="flex min-h-screen">
        {{-- Mobile drawer backdrop --}}
        <div x-show="navOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/50 backdrop-blur-xs lg:hidden" x-on:click="navOpen = false" style="display: none;"></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 -translate-x-full flex-col justify-between border-r border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 transition-transform duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] lg:static lg:w-60 lg:translate-x-0"
            :class="navOpen && 'translate-x-0'"
            aria-label="Administration"
        >
            <div class="flex min-h-0 flex-col gap-6 overflow-y-auto">
                <a href="{{ route('admin.index') }}" class="flex items-center gap-2.5 px-1" wire:navigate>
                    <span class="flex size-9 items-center justify-center rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-xs font-bold tracking-tight">TL</span>
                    <div class="flex min-w-0 flex-col">
                        <span class="truncate text-xs font-semibold tracking-tight">{{ $adminUser?->organization?->name ?? 'TicketLab' }}</span>
                        <span class="truncate text-[11px] text-[var(--text-secondary)]">Admin Command</span>
                    </div>
                </a>

                <nav class="flex flex-col gap-4 text-xs">
                    @foreach ($adminNav as $group => $links)
                        <div class="flex flex-col gap-0.5">
                            <span class="px-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">{{ $group }}</span>
                            @foreach ($links as $link)
                                <a
                                    href="{{ route($link['route']) }}"
                                    wire:navigate
                                    @class([
                                        'flex items-center gap-2.5 rounded-lg px-2.5 py-2 font-medium transition',
                                        'bg-[var(--bg-subtle)] font-semibold text-[var(--text-primary)]' => request()->routeIs($link['route']),
                                        'text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' => !request()->routeIs($link['route']),
                                    ])
                                >
                                    <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}" />
                                    </svg>
                                    {{ $link['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </nav>
            </div>

            <div class="flex flex-col gap-2.5 border-t border-[var(--border-hairline)] pt-4">
                <a href="{{ route('ops.scanner') }}" class="flex items-center justify-between rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-2.5 py-2 text-[11px] font-semibold transition hover:border-[var(--border-focused)]">
                    <span>Open Gate Terminal</span>
                    <span aria-hidden="true">→</span>
                </a>

                <div class="flex items-center justify-between">
                    <a href="{{ route('profile.edit') }}" class="flex min-w-0 items-center gap-2">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-[var(--bg-subtle)] text-[11px] font-semibold">{{ $adminUser?->initials() }}</span>
                        <span class="flex min-w-0 flex-col">
                            <span class="truncate text-xs font-medium">{{ $adminUser?->name }}</span>
                            <span class="truncate text-[10px] capitalize text-[var(--text-tertiary)]">{{ str_replace('_', ' ', (string) $adminUser?->role) }}{{ $adminUser?->subdivision ? ' · ' . $adminUser->subdivision->name : '' }}</span>
                        </span>
                    </a>

                    <button type="button"
                            onclick="toggleStudioTheme(event, null, this)"
                            class="cursor-pointer rounded-lg p-1.5 text-[var(--text-secondary)] transition-transform duration-100 hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)] active:scale-90"
                            title="Toggle Light / Dark">
                        <svg class="size-3.5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                        <svg class="hidden size-3.5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    </button>
                </div>

                @auth
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <button type="submit" class="w-full cursor-pointer text-left text-[11px] text-[var(--text-tertiary)] transition hover:text-[var(--status-danger)]">
                            Sign Out
                        </button>
                    </form>
                @endauth
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-14 items-center justify-between gap-3 border-b border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 sm:px-6">
                <div class="flex min-w-0 items-center gap-2">
                    <button type="button" class="cursor-pointer rounded-lg p-1.5 text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] lg:hidden" x-on:click="navOpen = true" aria-label="Open navigation">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                    </button>
                    <span class="truncate text-xs font-medium text-[var(--text-primary)]">{{ $title ?? 'Admin' }}</span>
                </div>

                <livewire:admin.active-event-switcher />
            </header>

            <main class="mx-auto w-full max-w-7xl flex-1 p-4 sm:p-5 md:p-6">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
