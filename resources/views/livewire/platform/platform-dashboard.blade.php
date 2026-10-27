<div class="space-y-6">

    <!-- Top Navigation Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-purple-500/10 text-purple-700 dark:text-purple-300 text-[10px] font-mono font-bold uppercase tracking-wider border border-purple-500/20">
                    <span class="size-1.5 rounded-full bg-purple-500 animate-pulse"></span>
                    Super Admin Console
                </span>
                <span class="text-[11px] font-mono text-[var(--text-tertiary)]">/platform</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-[var(--text-primary)] mt-1">
                Platform Engine & Cross-Tenant Oversight
            </h1>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-xs text-[var(--text-secondary)] shadow-2xs">
                <span class="size-2 rounded-full bg-emerald-500"></span>
                <span>System Operational</span>
            </span>
        </div>
    </div>

    <!-- Executive Metrics Strip (Compact Minimalist Cards) -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5">
        
        <!-- Total Tenants -->
        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3.5 shadow-2xs space-y-1">
            <div class="flex items-center justify-between text-[11px] font-medium text-[var(--text-secondary)]">
                <span>Total Tenants</span>
                <span class="text-xs">🏢</span>
            </div>
            <div class="text-xl font-black text-[var(--text-primary)] tracking-tight">
                {{ $totalTenants }}
            </div>
            <p class="text-[10px] text-[var(--text-tertiary)]">Isolated organizations</p>
        </div>

        <!-- Total Published Events -->
        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3.5 shadow-2xs space-y-1">
            <div class="flex items-center justify-between text-[11px] font-medium text-[var(--text-secondary)]">
                <span>Published Events</span>
                <span class="text-xs">🎪</span>
            </div>
            <div class="text-xl font-black text-[var(--text-primary)] tracking-tight">
                {{ $totalEvents }}
            </div>
            <p class="text-[10px] text-[var(--text-tertiary)]">Active public portals</p>
        </div>

        <!-- Platform GMV -->
        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3.5 shadow-2xs space-y-1">
            <div class="flex items-center justify-between text-[11px] font-medium text-[var(--text-secondary)]">
                <span>Platform GMV</span>
                <span class="text-xs font-mono text-emerald-600">GHS</span>
            </div>
            <div class="text-xl font-black text-[var(--text-primary)] tracking-tight">
                {{ number_format($grossMerchandiseValue, 2) }}
            </div>
            <p class="text-[10px] text-[var(--text-tertiary)]">Paid ticket transactions</p>
        </div>

        <!-- Platform Fee Accumulator -->
        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3.5 shadow-2xs space-y-1">
            <div class="flex items-center justify-between text-[11px] font-medium text-[var(--text-secondary)]">
                <span>Platform Fees</span>
                <span class="text-xs font-mono text-amber-600">GHS</span>
            </div>
            <div class="text-xl font-black text-amber-700 dark:text-amber-400 tracking-tight">
                {{ number_format($platformFeeAccumulator, 2) }}
            </div>
            <p class="text-[10px] text-[var(--text-tertiary)]">@ GHS 3.00 / paid ticket</p>
        </div>

        <!-- System Telemetry -->
        <div class="col-span-2 lg:col-span-1 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3.5 shadow-2xs space-y-1">
            <div class="flex items-center justify-between text-[11px] font-medium text-[var(--text-secondary)]">
                <span>Infrastructure</span>
                <span class="text-xs">⚡</span>
            </div>
            <div class="text-xs font-bold text-[var(--text-primary)] truncate">
                {{ $health['database'] }}
            </div>
            <div class="flex items-center gap-2 text-[10px] text-[var(--text-tertiary)]">
                <span>Queue: {{ $health['queue'] }}</span>
                <span>·</span>
                <span class="{{ $health['failed_jobs'] > 0 ? 'text-rose-500 font-bold' : '' }}">
                    {{ $health['failed_jobs'] }} failed
                </span>
            </div>
        </div>

    </div>

    <!-- Tenant Roster & Drilldown Section -->
    <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-2xs overflow-hidden">
        
        <!-- Table Search Bar -->
        <div class="p-4 border-b border-[var(--border-hairline)] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-[var(--text-primary)]">Registered Tenant Roster</h3>
                <p class="text-[11px] text-[var(--text-secondary)]">Inspect individual organization footprints and drill down into event metrics.</p>
            </div>

            <div class="relative w-full sm:w-64">
                <input
                    type="search"
                    wire:model.live.debounce.250ms="tenantSearch"
                    placeholder="Search tenant name or slug..."
                    class="w-full pl-3 pr-8 py-1.5 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-canvas)] text-xs text-[var(--text-primary)] placeholder:text-[var(--text-tertiary)] focus:border-[var(--text-primary)] focus:outline-hidden"
                />
            </div>
        </div>

        <!-- Tenant Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[11px] font-semibold text-[var(--text-secondary)]">
                    <tr>
                        <th class="px-4 py-2.5">Organization</th>
                        <th class="px-4 py-2.5">Slug</th>
                        <th class="px-4 py-2.5">Events Count</th>
                        <th class="px-4 py-2.5">Onboarded</th>
                        <th class="px-4 py-2.5 text-right">Drilldown</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-hairline)] text-[11px]">
                    @forelse ($tenants as $tenant)
                        <tr class="hover:bg-[var(--bg-subtle)]/50 transition">
                            <td class="px-4 py-3">
                                <div class="font-bold text-[var(--text-primary)]">{{ $tenant->name }}</div>
                                <div class="text-[10px] text-[var(--text-tertiary)]">ID: #{{ $tenant->id }}</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-[var(--text-secondary)]">
                                {{ $tenant->slug }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-[var(--bg-subtle)] font-medium text-[10px]">
                                    {{ $tenant->events_count }} {{ \Illuminate\Support\Str::plural('event', $tenant->events_count) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-[var(--text-secondary)]">
                                {{ $tenant->created_at?->format('M j, Y') ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    wire:click="viewTenant({{ $tenant->id }})"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-canvas)] text-[10px] font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition press-scale cursor-pointer"
                                >
                                    <span>Inspect</span>
                                    <span>→</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-xs text-[var(--text-secondary)]">
                                No tenants found matching your filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

    <!-- Tenant Event Drilldown Modal -->
    @if ($selectedTenant)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
            @keydown.window.escape="$wire.closeTenantModal()"
        >
            <div class="w-full max-w-2xl rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-xl space-y-4">
                
                <div class="flex items-start justify-between pb-3 border-b border-[var(--border-hairline)]">
                    <div>
                        <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-[var(--text-tertiary)]">Tenant Inspection</span>
                        <h3 class="text-lg font-bold text-[var(--text-primary)]">{{ $selectedTenant->name }}</h3>
                        <p class="text-xs text-[var(--text-secondary)]">Slug: <span class="font-mono">{{ $selectedTenant->slug }}</span></p>
                    </div>

                    <button
                        type="button"
                        wire:click="closeTenantModal"
                        class="p-1 rounded-lg text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition cursor-pointer text-base"
                    >
                        &times;
                    </button>
                </div>

                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-[var(--text-primary)] uppercase tracking-wider">
                        Events Footprint ({{ $tenantEvents->count() }})
                    </h4>

                    @if ($tenantEvents->isEmpty())
                        <div class="rounded-xl border border-dashed border-[var(--border-hairline)] p-6 text-center text-xs text-[var(--text-secondary)]">
                            This tenant has not yet created any events.
                        </div>
                    @else
                        <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                            @foreach ($tenantEvents as $ev)
                                <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] p-3 flex items-center justify-between text-xs">
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-[var(--text-primary)]">{{ $ev->title }}</span>
                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-semibold {{ $ev->status === 'published' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-zinc-500/10 text-zinc-600' }}">
                                                {{ ucfirst($ev->status) }}
                                            </span>
                                        </div>
                                        <p class="text-[10px] text-[var(--text-tertiary)]">
                                            {{ $ev->start_at ? $ev->start_at->format('M j, Y') : 'Date TBA' }} · {{ $ev->venue ?: 'No venue specified' }}
                                        </p>
                                    </div>

                                    <div class="text-right">
                                        <span class="block text-[11px] font-mono font-bold text-[var(--text-primary)]">
                                            {{ $ev->ticketTypes->count() }} tiers
                                        </span>
                                        <a
                                            href="{{ route('public.event.show', ['org_slug' => $selectedTenant->slug, 'event_slug' => $ev->slug]) }}"
                                            target="_blank"
                                            class="text-[10px] text-[var(--color-primary)] hover:underline inline-flex items-center gap-0.5"
                                        >
                                            <span>View Page</span>
                                            <span>↗</span>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="pt-3 border-t border-[var(--border-hairline)] flex justify-end">
                    <button
                        type="button"
                        wire:click="closeTenantModal"
                        class="px-4 py-2 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-canvas)] transition press-scale cursor-pointer"
                    >
                        Close
                    </button>
                </div>

            </div>
        </div>
    @endif

</div>
