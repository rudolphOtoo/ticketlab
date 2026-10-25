<x-layouts::ops :title="__('Operations Hub')">
    <div class="flex flex-col gap-5 sm:gap-6">
        
        <!-- Welcome Hero Banner -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 sm:p-6 shadow-xs">
            <div class="flex items-center gap-3.5">
                <div class="flex size-11 sm:size-12 items-center justify-center rounded-xl bg-[var(--bg-subtle)] border border-[var(--border-hairline)] text-sm font-bold tracking-tight text-[var(--text-primary)]">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </div>
                <div class="flex flex-col gap-0.5">
                    <div class="flex items-center gap-2">
                        <h1 class="text-base sm:text-lg md:text-xl font-bold tracking-tight text-[var(--text-primary)]">
                            Welcome back, {{ auth()->user()->name }}
                        </h1>
                        @if(auth()->user()->subdivision)
                            <x-ui.badge variant="warning" size="sm">
                                Scoped: {{ auth()->user()->subdivision->name }}
                            </x-ui.badge>
                        @else
                            <x-ui.badge variant="success" size="sm">
                                Cross-Zone Superadmin
                            </x-ui.badge>
                        @endif
                    </div>
                    <p class="text-xs text-[var(--text-secondary)]">
                        Kingdom Summit 2026 · Gate Operations Command Center
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-[var(--border-hairline)] bg-[var(--status-success-bg)] px-2.5 py-1 text-[11px] font-medium text-[var(--status-success)]">
                    <span class="size-1.5 rounded-full bg-[var(--status-success)] animate-pulse"></span>
                    Terminal Cluster Online
                </span>
            </div>
        </div>

        <!-- 4 Primary Operation Command Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            <!-- Card 1: Financial Ledger -->
            <div class="flex flex-col justify-between rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 sm:p-5 shadow-xs hover:border-[var(--border-focused)] transition group">
                <div class="flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <span class="size-8 rounded-lg bg-[var(--bg-subtle)] flex items-center justify-center text-[var(--text-primary)]">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.143 2.143L15.429 8" />
                            </svg>
                        </span>
                        <x-ui.badge variant="neutral" size="sm">Admin</x-ui.badge>
                    </div>
                    <h2 class="text-sm font-semibold text-[var(--text-primary)]">Financial Closeout & Attendance</h2>
                    <p class="text-xs text-[var(--text-secondary)] leading-relaxed">
                        Reconciliation of gate cash drawers, Mobile Money settlements, and regional zone turnout rates.
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-[var(--border-hairline)]">
                    <a href="{{ url('/admin/sessions') }}" class="cursor-pointer inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--text-primary)] hover:underline" wire:navigate>
                        Open Financial Ledger →
                    </a>
                </div>
            </div>

            <!-- Card 2: Door Scanner -->
            <div class="flex flex-col justify-between rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 sm:p-5 shadow-xs hover:border-[var(--border-focused)] transition group">
                <div class="flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <span class="size-8 rounded-lg bg-[var(--bg-subtle)] flex items-center justify-center text-[var(--text-primary)]">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" />
                            </svg>
                        </span>
                        <x-ui.badge variant="success" size="sm">Camera Ready</x-ui.badge>
                    </div>
                    <h2 class="text-sm font-semibold text-[var(--text-primary)]">Door Scanner Terminal</h2>
                    <p class="text-xs text-[var(--text-secondary)] leading-relaxed">
                        High-contrast gate scanner with harmonic audio validation, duplicate pass alerts, and native haptics.
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-[var(--border-hairline)]">
                    <a href="{{ url('/ops/scanner') }}" class="cursor-pointer inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--text-primary)] hover:underline" wire:navigate>
                        Launch Camera Scanner →
                    </a>
                </div>
            </div>

            <!-- Card 3: Cash Desk Register -->
            <div class="flex flex-col justify-between rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 sm:p-5 shadow-xs hover:border-[var(--border-focused)] transition group">
                <div class="flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <span class="size-8 rounded-lg bg-[var(--bg-subtle)] flex items-center justify-center text-[var(--text-primary)]">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v12.75m0-12.75h19.5m0 0v12.75M3.75 4.5h16.5M3.75 4.5A2.25 2.25 0 0 0 1.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25h16.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25" />
                            </svg>
                        </span>
                        <x-ui.badge variant="warning" size="sm">Point of Sale</x-ui.badge>
                    </div>
                    <h2 class="text-sm font-semibold text-[var(--text-primary)]">Walk-In Cash Desk Register</h2>
                    <p class="text-xs text-[var(--text-secondary)] leading-relaxed">
                        On-site walk-in ticketing, Ghanaian banknote preset calculations, change math, and printable passes.
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-[var(--border-hairline)]">
                    <a href="{{ url('/ops/cash-desk') }}" class="cursor-pointer inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--text-primary)] hover:underline" wire:navigate>
                        Open Cash Desk Register →
                    </a>
                </div>
            </div>

            <!-- Card 4: Attendee Roster -->
            <div class="flex flex-col justify-between rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 sm:p-5 shadow-xs hover:border-[var(--border-focused)] transition group">
                <div class="flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <span class="size-8 rounded-lg bg-[var(--bg-subtle)] flex items-center justify-center text-[var(--text-primary)]">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                        </span>
                        <x-ui.badge variant="neutral" size="sm">Roster</x-ui.badge>
                    </div>
                    <h2 class="text-sm font-semibold text-[var(--text-primary)]">Attendee Roster & Accreditation</h2>
                    <p class="text-xs text-[var(--text-secondary)] leading-relaxed">
                        Search-as-you-type attendee registry, zone-scoped credentials, manual admissions, and CSV exports.
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-[var(--border-hairline)]">
                    <a href="{{ url('/admin/attendees') }}" class="cursor-pointer inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--text-primary)] hover:underline" wire:navigate>
                        Browse Attendee Roster →
                    </a>
                </div>
            </div>

        </div>

    </div>
</x-layouts.ops>
