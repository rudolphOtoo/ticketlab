<div class="space-y-6">

    <!-- Header Strip -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 text-[10px] font-mono font-bold uppercase tracking-wider border border-emerald-500/20">
                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                    Zero Vendor Lock-In
                </span>
                <span class="text-[11px] font-mono text-[var(--text-tertiary)]">/admin/exports</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-[var(--text-primary)] mt-1">
                Data Export & Sovereignty Central
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-0.5">
                Download your complete attendee rosters, questionnaires, and full organization backups anytime.
            </p>
        </div>

        @if ($events->count() > 1)
            <div class="flex items-center gap-2">
                <label for="eventSelect" class="text-xs text-[var(--text-secondary)] font-medium">Scope:</label>
                <select
                    id="eventSelect"
                    wire:model.live="selectedEventId"
                    class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-1.5 text-xs font-semibold text-[var(--text-primary)] shadow-2xs focus:outline-hidden [color-scheme:light] dark:[color-scheme:dark] cursor-pointer"
                >
                    @foreach ($events as $ev)
                        <option value="{{ $ev->id }}">{{ $ev->title }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <!-- Export Action Cards Grid (Compact Minimalist Cards) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

        <!-- Card 1: Streaming CSV Attendee Roster -->
        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 sm:p-5 shadow-2xs flex flex-col justify-between space-y-4 subtle-hover-glow transition">
            <div class="space-y-3">
                <div class="flex items-start justify-between">
                    <div class="size-9 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-sm">
                        📊
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-semibold bg-[var(--bg-subtle)] text-[var(--text-secondary)]">
                        CSV / Excel Format
                    </span>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-[var(--text-primary)]">
                        Streaming Attendee Master Roster
                    </h3>
                    <p class="text-xs text-[var(--text-secondary)] mt-1 leading-relaxed">
                        High-speed streaming export ($O(1)$ memory). Automatically inspects custom registration questions and unrolls them into first-class spreadsheet columns (e.g. Bus Route, T-Shirt, Dietary).
                    </p>
                </div>

                <div class="rounded-lg bg-[var(--bg-canvas)] border border-[var(--border-hairline)] p-3 text-[11px] text-[var(--text-secondary)] space-y-1 font-mono">
                    <div class="flex items-center justify-between">
                        <span>Selected Event:</span>
                        <strong class="text-[var(--text-primary)] truncate max-w-[200px]">
                            {{ $selectedEvent?->title ?? 'All Organization Events' }}
                        </strong>
                    </div>
                    <div class="flex items-center justify-between text-[10px] text-[var(--text-tertiary)]">
                        <span>Chunking buffer:</span>
                        <span>500 rows/stream</span>
                    </div>
                </div>
            </div>

            <button
                type="button"
                wire:click="exportAttendeeCsv"
                class="w-full py-2.5 px-4 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold hover:opacity-95 transition shadow-2xs press-scale cursor-pointer flex items-center justify-center gap-2"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span>Download Attendee CSV</span>
            </button>
        </div>

        <!-- Card 2: Complete JSON Tenant Backup -->
        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 sm:p-5 shadow-2xs flex flex-col justify-between space-y-4 subtle-hover-glow transition">
            <div class="space-y-3">
                <div class="flex items-start justify-between">
                    <div class="size-9 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-sm">
                        📦
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-semibold bg-[var(--bg-subtle)] text-[var(--text-secondary)]">
                        Standard JSON Schema
                    </span>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-[var(--text-primary)]">
                        Complete JSON Tenant Database Backup
                    </h3>
                    <p class="text-xs text-[var(--text-secondary)] mt-1 leading-relaxed">
                        Export your organization's entire data footprint. Includes full event configurations, ticket tiers, keynote rosters, multi-day schedules, FAQs, and questionnaire schemas.
                    </p>
                </div>

                <div class="rounded-lg bg-[var(--bg-canvas)] border border-[var(--border-hairline)] p-3 text-[11px] text-[var(--text-secondary)] space-y-1 font-mono">
                    <div class="flex items-center justify-between">
                        <span>Portability:</span>
                        <span class="text-emerald-600 dark:text-emerald-400 font-bold">100% Self-Hostable</span>
                    </div>
                    <div class="flex items-center justify-between text-[10px] text-[var(--text-tertiary)]">
                        <span>Specification:</span>
                        <span>JSON-LD Compatible</span>
                    </div>
                </div>
            </div>

            <button
                type="button"
                wire:click="exportJsonBackup"
                class="w-full py-2.5 px-4 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-primary)] text-xs font-semibold hover:bg-[var(--bg-subtle)] transition shadow-2xs press-scale cursor-pointer flex items-center justify-center gap-2"
            >
                <svg class="size-4 text-[var(--text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Download Complete JSON Backup</span>
            </button>
        </div>

    </div>

    <!-- Data Sovereignty Guarantee Notice Strip -->
    <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 flex items-start gap-3">
        <div class="size-8 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
        </div>
        <div class="space-y-0.5 text-xs">
            <h4 class="font-bold text-[var(--text-primary)]">The TicketLab Data Sovereignty Pledge</h4>
            <p class="text-[var(--text-secondary)] leading-relaxed">
                Your attendees, transaction histories, and event setups belong to your organization. TicketLab guarantees zero vendor lock-in, with instant export capabilities that can be ingested into your own private database or third-party CRM anytime.
            </p>
        </div>
    </div>

</div>
