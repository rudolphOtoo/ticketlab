@php
    $adminUser = auth()->user();

    // Branding always comes from the signed-in admin's own organisation, so each tenant only ever sees itself.
    $tenant = $adminUser?->organization;
    $tenantName = $tenant?->name ?? 'TicketLab';
    $tenantInitials = $tenant?->initials() ?: 'TL';
    $tenantLogo = is_string($tenant?->logo_url) && preg_match('#^(https?://|/(?!/))#i', $tenant->logo_url) === 1 ? $tenant->logo_url : null;

    $adminNav = [
        'Command' => [
            ['route' => 'admin.index', 'label' => 'Overview', 'icon' => 'm2.25 12 8.954-8.955a1.126 1.126 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25'],
            ['route' => 'admin.events', 'label' => 'Events', 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5'],
            ['route' => 'admin.studio', 'label' => 'Customizer Studio', 'icon' => 'M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42'],
            ['route' => 'admin.studio.bespoke', 'label' => 'Portal Pages & Nav', 'icon' => 'M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418'],
            ['route' => 'admin.tickets', 'label' => 'Tickets & Pricing', 'icon' => 'M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z'],
            ['route' => 'admin.studio.marketing', 'label' => 'Marketing Studio', 'icon' => 'm2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z'],
        ],
        'Audience' => [
            ['route' => 'admin.attendees', 'label' => 'Attendee Roster', 'icon' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z'],
            ['route' => 'admin.broadcasts', 'label' => 'Broadcast Hub', 'icon' => 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75'],
        ],
        'Finance' => [
            ['route' => 'admin.sessions', 'label' => 'Financial Ledger', 'icon' => 'M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.143 2.143L15.429 8'],
            ...($adminUser?->canManageTeam() && $adminUser->organization_id !== null && $adminUser->subdivision_id === null
                ? [['route' => 'admin.payouts', 'label' => 'Zone Payouts', 'icon' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z']]
                : []),
            ['route' => 'admin.exports', 'label' => 'Data Exports', 'icon' => 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3'],
        ],
        'System' => array_values(array_filter([
            $adminUser?->canManageTeam()
                ? ['route' => 'admin.users', 'label' => 'Team & Staff', 'icon' => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z']
                : null,
            $adminUser?->isAdmin()
                ? ['route' => 'admin.subdivisions', 'label' => 'Subdivisions', 'icon' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z']
                : null,
            ['route' => 'admin.queue-monitor', 'label' => 'Queue Monitor', 'icon' => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z'],
        ])),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => request()->cookie('theme', 'dark') !== 'light'])>
<head>
    @include('partials.head')
    <title>{{ $title ?? 'Admin' }} — TicketLab Admin</title>
    @livewireStyles
</head>
<body class="min-h-screen antialiased bg-[var(--bg-canvas)] text-[var(--text-primary)]" x-data="{
    navOpen: false,
    sidebarCollapsed: localStorage.getItem('admin_sidebar_collapsed') === 'true',
    toggleSidebar() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem('admin_sidebar_collapsed', this.sidebarCollapsed);
    }
}">
    <div class="flex min-h-screen">
        {{-- Mobile drawer backdrop --}}
        <div x-show="navOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/50 backdrop-blur-xs lg:hidden" x-on:click="navOpen = false" style="display: none;"></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex shrink-0 -translate-x-full flex-col justify-between border-r border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3 sm:p-4 transition-all duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] lg:static lg:translate-x-0"
            :class="{
                'translate-x-0': navOpen,
                'w-64 lg:w-60': !sidebarCollapsed,
                'w-64 lg:w-[4.5rem]': sidebarCollapsed
            }"
            aria-label="Administration"
        >
            <div class="flex min-h-0 flex-col gap-6 overflow-y-auto">
                <div class="flex items-center justify-between gap-2 px-1">
                    <a href="{{ route('admin.index') }}" class="flex min-w-0 items-center gap-2.5 overflow-hidden" wire:navigate :title="sidebarCollapsed ? @js($tenantName) : ''">
                        @if ($tenantLogo)
                            <img src="{{ $tenantLogo }}" alt="{{ $tenantName }} logo" class="size-9 shrink-0 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] object-cover" data-tenant-logo>
                        @else
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-xs font-bold tracking-tight" data-tenant-initials>{{ $tenantInitials }}</span>
                        @endif
                        <div class="flex min-w-0 flex-col" x-show="!sidebarCollapsed" x-transition>
                            <span class="truncate text-xs font-semibold tracking-tight" data-tenant-name>{{ $tenantName }}</span>
                            <span class="truncate text-[11px] text-[var(--text-secondary)]">{{ $adminUser?->subdivision ? $adminUser->subdivision->name . ' · Zone admin' : 'Admin Command' }}</span>
                        </div>
                    </a>

                    <button
                        type="button"
                        x-on:click="toggleSidebar()"
                        class="hidden lg:flex size-7 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition press-scale"
                        :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                    >
                        <svg class="size-3.5 transition-transform duration-200" :class="{ 'rotate-180': sidebarCollapsed }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
                        </svg>
                    </button>

                    <button
                        type="button"
                        x-on:click="navOpen = false"
                        class="flex lg:hidden size-8 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition press-scale"
                        aria-label="Close navigation"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <nav class="flex flex-col gap-4 text-xs">
                    @foreach ($adminNav as $group => $links)
                        <div class="flex flex-col gap-0.5">
                            <span class="px-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]" x-show="!sidebarCollapsed" x-transition>{{ $group }}</span>
                            @foreach ($links as $link)
                                <a
                                    href="{{ route($link['route']) }}"
                                    wire:navigate
                                    :title="sidebarCollapsed ? '{{ $link['label'] }}' : ''"
                                    @class([
                                        'flex items-center gap-2.5 rounded-lg px-2.5 py-2 font-medium transition',
                                        'bg-[var(--bg-subtle)] font-semibold text-[var(--text-primary)]' => request()->routeIs($link['route']),
                                        'text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' => !request()->routeIs($link['route']),
                                    ])
                                >
                                    <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}" />
                                    </svg>
                                    <span class="truncate" x-show="!sidebarCollapsed" x-transition>{{ $link['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </nav>
            </div>

            <div class="flex flex-col gap-2.5 border-t border-[var(--border-hairline)] pt-4">
                <a href="{{ route('home') }}" class="flex items-center justify-between rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-2.5 py-2 text-[11px] font-semibold text-[var(--text-primary)] transition hover:border-[var(--border-focused)]" :title="sidebarCollapsed ? 'Go to Public Homepage' : ''">
                    <div class="flex items-center gap-2 overflow-hidden">
                        <svg class="size-4 shrink-0 text-[var(--text-secondary)]" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.126 1.126 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                        <span x-show="!sidebarCollapsed" x-transition class="truncate">Public Homepage</span>
                    </div>
                    <span aria-hidden="true" class="text-[10px] text-[var(--text-tertiary)]" :class="{ 'hidden': sidebarCollapsed }">↗</span>
                </a>

                <a href="{{ route('ops.scanner') }}" class="flex items-center justify-between rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-2.5 py-2 text-[11px] font-semibold transition hover:border-[var(--border-focused)]" :title="sidebarCollapsed ? 'Open Gate Terminal' : ''">
                    <span x-show="!sidebarCollapsed" x-transition class="truncate">Open Gate Terminal</span>
                    <span aria-hidden="true" :class="{ 'mx-auto': sidebarCollapsed }">→</span>
                </a>

                @auth
                    {{-- Account menu: the profile card opens upwards; Escape or a click outside closes it. --}}
                    <div class="relative" x-data="{ accountOpen: false }" x-on:keydown.escape.window="accountOpen = false" x-on:click.outside="accountOpen = false">
                        <button
                            type="button"
                            x-on:click="accountOpen = !accountOpen"
                            :aria-expanded="accountOpen.toString()"
                            aria-haspopup="menu"
                            aria-controls="admin-account-menu"
                            class="flex w-full cursor-pointer items-center gap-2 rounded-lg border border-transparent p-1.5 text-left transition hover:border-[var(--border-hairline)] hover:bg-[var(--bg-subtle)]"
                            :class="{ 'border-[var(--border-hairline)] bg-[var(--bg-subtle)]': accountOpen }"
                            :title="sidebarCollapsed ? @js((string) $adminUser?->name) : ''"
                        >
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[var(--text-primary)] text-[11px] font-semibold text-[var(--bg-canvas)]">{{ $adminUser?->initials() }}</span>
                            <span class="flex min-w-0 flex-1 flex-col" x-show="!sidebarCollapsed" x-transition>
                                <span class="truncate text-xs font-semibold">{{ $adminUser?->name }}</span>
                                <span class="truncate text-[10px] capitalize text-[var(--text-tertiary)]">{{ str_replace('_', ' ', (string) $adminUser?->role) }}{{ $adminUser?->subdivision ? ' · ' . $adminUser->subdivision->name : '' }}</span>
                            </span>
                            <svg x-show="!sidebarCollapsed" class="size-4 shrink-0 text-[var(--text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15 12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                            </svg>
                        </button>

                        <div
                            id="admin-account-menu"
                            x-show="accountOpen"
                            x-transition.origin.bottom.left
                            role="menu"
                            aria-label="Account"
                            class="absolute bottom-full left-0 z-50 mb-2 w-60 overflow-hidden rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-1 shadow-lg"
                            style="display: none;"
                        >
                            <div class="px-2.5 pb-2 pt-1.5">
                                <p class="truncate text-xs font-semibold text-[var(--text-primary)]">{{ $adminUser?->name }}</p>
                                <p class="truncate text-[11px] text-[var(--text-tertiary)]">{{ $adminUser?->email }}</p>
                            </div>

                            <div class="border-t border-[var(--border-hairline)] py-1">
                                <a href="{{ route('profile.edit') }}" role="menuitem" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium text-[var(--text-secondary)] transition hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]">
                                    <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                                    Profile &amp; settings
                                </a>
                                <button type="button" role="menuitem" onclick="toggleStudioTheme(event, null, this)" class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-xs font-medium text-[var(--text-secondary)] transition hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]">
                                    <svg class="size-4 shrink-0 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                                    <svg class="hidden size-4 shrink-0 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                    <span class="dark:hidden">Dark mode</span>
                                    <span class="hidden dark:inline">Light mode</span>
                                </button>
                            </div>

                            {{-- Sign-out is a POST with a CSRF token, so a link or image elsewhere can never sign an admin out. --}}
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-[var(--border-hairline)] pt-1" data-sign-out>
                                @csrf
                                <button type="submit" role="menuitem" class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-xs font-semibold text-[var(--status-danger)] transition hover:bg-(--status-danger)/10">
                                    <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" /></svg>
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
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

                <div class="flex items-center gap-2">
                    <a
                        href="{{ route('home') }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-2 sm:px-2.5 py-1.5 text-[11px] font-medium text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:border-[var(--border-focused)] transition press-scale"
                        title="View Public Event Portal"
                    >
                        <svg class="size-3.5 text-[var(--text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.126 1.126 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                        <span class="hidden sm:inline">View Portal</span>
                        <span aria-hidden="true" class="hidden sm:inline text-[9px] text-[var(--text-tertiary)]">↗</span>
                    </a>

                    <livewire:admin.active-event-switcher />
                </div>
            </header>

            <main class="mx-auto w-full max-w-7xl flex-1 p-4 sm:p-5 md:p-6">
                {{ $slot }}
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
