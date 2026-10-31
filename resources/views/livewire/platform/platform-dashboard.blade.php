<div class="space-y-6">

    <!-- Top Executive Navigation Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-purple-500/10 text-purple-700 dark:text-purple-300 text-[10px] font-mono font-bold uppercase tracking-wider border border-purple-500/20">
                    <span class="size-1.5 rounded-full bg-purple-500 animate-pulse"></span>
                    Super Admin Console
                </span>
                <span class="text-[11px] font-mono text-[var(--text-tertiary)]">/platform</span>
                <span class="text-zinc-400 dark:text-zinc-600">·</span>
                <span class="text-[10px] font-mono text-[var(--text-tertiary)]">PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }} · Laravel {{ app()->version() }}</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black tracking-tight text-[var(--text-primary)] mt-1">
                Platform Engine & Cross-Tenant Oversight
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-0.5">
                Multi-tenant infrastructure health, real-time transaction volume, and deployment personality controls.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-xs font-semibold text-emerald-700 dark:text-emerald-400 shadow-2xs">
                <span class="size-2 rounded-full bg-emerald-500 animate-ping"></span>
                <span>System Operational</span>
            </span>
        </div>
    </div>

    <!-- Executive Metrics Strip (Elevated High-Density Telemetry Cards) -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5">
        
        <!-- Total Tenants -->
        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-2xs space-y-2 hover:translate-y-[-2px] transition-transform duration-150">
            <div class="flex items-center justify-between text-xs font-semibold text-[var(--text-secondary)]">
                <span>Active Tenants</span>
                <div class="p-1.5 rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-black text-[var(--text-primary)] tracking-tight">
                {{ $totalTenants }}
            </div>
            <p class="text-[10px] text-[var(--text-tertiary)] flex items-center gap-1">
                <span class="size-1.5 rounded-full bg-purple-500"></span>
                <span>Isolated databases & schemas</span>
            </p>
        </div>

        <!-- Total Published Events -->
        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-2xs space-y-2 hover:translate-y-[-2px] transition-transform duration-150">
            <div class="flex items-center justify-between text-xs font-semibold text-[var(--text-secondary)]">
                <span>Published Portals</span>
                <div class="p-1.5 rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-black text-[var(--text-primary)] tracking-tight">
                {{ $totalEvents }}
            </div>
            <p class="text-[10px] text-[var(--text-tertiary)] flex items-center gap-1">
                <span class="size-1.5 rounded-full bg-sky-500"></span>
                <span>Live ticket sales active</span>
            </p>
        </div>

        <!-- Platform GMV -->
        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-2xs space-y-2 hover:translate-y-[-2px] transition-transform duration-150">
            <div class="flex items-center justify-between text-xs font-semibold text-[var(--text-secondary)]">
                <span>Platform GMV</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">GHS</span>
            </div>
            <div class="text-2xl font-black text-emerald-700 dark:text-emerald-400 tracking-tight">
                {{ number_format($grossMerchandiseValue, 2) }}
            </div>
            <p class="text-[10px] text-[var(--text-tertiary)] flex items-center gap-1">
                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                <span>{{ $paidTicketsCount }} paid ticket passes</span>
            </p>
        </div>

        <!-- Platform Fee Accumulator -->
        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-2xs space-y-2 hover:translate-y-[-2px] transition-transform duration-150">
            <div class="flex items-center justify-between text-xs font-semibold text-[var(--text-secondary)]">
                <span>Platform Take</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20">GHS</span>
            </div>
            <div class="text-2xl font-black text-amber-700 dark:text-amber-400 tracking-tight">
                {{ number_format($platformFeeAccumulator, 2) }}
            </div>
            <p class="text-[10px] text-[var(--text-tertiary)] flex items-center gap-1">
                <span class="size-1.5 rounded-full bg-amber-500"></span>
                <span>Fixed GHS 3.00 fee / pass</span>
            </p>
        </div>

        <!-- System Telemetry -->
        <div class="col-span-2 lg:col-span-1 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-2xs space-y-2 hover:translate-y-[-2px] transition-transform duration-150">
            <div class="flex items-center justify-between text-xs font-semibold text-[var(--text-secondary)]">
                <span>Infrastructure</span>
                <div class="p-1.5 rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
            </div>
            <div class="text-xs font-bold text-[var(--text-primary)] truncate font-mono">
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

    <!-- Runtime Deployment Engine (Dynamic Dual-Mode Controller) -->
    <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 sm:p-6 shadow-2xs space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <div class="p-1.5 rounded-lg bg-[var(--color-primary)]/10 text-[var(--color-primary)]">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h2 class="text-sm font-bold text-[var(--text-primary)]">Runtime Deployment Personality</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-mono font-semibold {{ $deploymentMode === 'dedicated' ? 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20' : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20' }}">
                        Active: {{ $deploymentMode === 'dedicated' ? 'Dedicated Bespoke (' . ($homeOrgSlug ?? 'None') . ')' : 'Multi-Tenant Platform Hub' }}
                    </span>
                </div>
                <p class="text-xs text-[var(--text-secondary)] mt-1">
                    Toggle system deployment mode dynamically from database without redeploying <code class="px-1.5 py-0.5 rounded bg-[var(--bg-subtle)] font-mono text-[11px]">.env</code>. Changes propagate instantly across all edge requests.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="savePlatformSettings"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold shadow-xs active:scale-[0.97] hover:opacity-95 disabled:opacity-50 transition cursor-pointer"
                >
                    <span wire:loading.remove wire:target="savePlatformSettings">Save Configuration</span>
                    <span wire:loading wire:target="savePlatformSettings">Applying...</span>
                </button>
            </div>
        </div>

        @if ($settingSuccessMessage)
            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3.5 flex items-center justify-between text-xs font-medium text-emerald-800 dark:text-emerald-200">
                <div class="flex items-center gap-2">
                    <svg class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ $settingSuccessMessage }}</span>
                </div>
                <button type="button" wire:click="$set('settingSuccessMessage', null)" class="text-emerald-700 hover:text-emerald-900 cursor-pointer text-sm">✕</button>
            </div>
        @endif

        @error('deploymentMode')
            <p class="text-xs text-rose-500">{{ $message }}</p>
        @enderror
        @error('homeOrgSlug')
            <p class="text-xs text-rose-500">{{ $message }}</p>
        @enderror

        <!-- Mode Selection Cards with Micro-Interactions -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Platform Mode Card -->
            <label class="relative flex flex-col p-4 rounded-2xl border cursor-pointer transition-all duration-200 select-none {{ $deploymentMode === 'platform' ? 'border-[var(--color-primary)] bg-[var(--bg-subtle)]/60 ring-2 ring-[var(--color-primary)]/20 shadow-xs' : 'border-[var(--border-hairline)] bg-[var(--bg-canvas)] hover:border-[var(--border-strong)] hover:translate-y-[-1px]' }}">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-2.5">
                        <input
                            type="radio"
                            wire:model.live="deploymentMode"
                            value="platform"
                            class="size-4 text-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                        />
                        <span class="text-xs font-bold text-[var(--text-primary)]">Multi-Tenant Platform Hub</span>
                    </div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400">Directory Mode</span>
                </div>
                <p class="text-xs text-[var(--text-secondary)] mt-2.5 leading-relaxed">
                    Root <code class="px-1 py-0.5 rounded bg-[var(--bg-surface)] font-mono text-[10px]">/</code> serves the public discovery directory with multi-tenant search, category browsing, and SaaS onboarding. Organizers can list or unlist events freely.
                </p>
            </label>

            <!-- Dedicated Bespoke Mode Card -->
            <label class="relative flex flex-col p-4 rounded-2xl border cursor-pointer transition-all duration-200 select-none {{ $deploymentMode === 'dedicated' ? 'border-amber-500 bg-amber-500/5 ring-2 ring-amber-500/20 shadow-xs' : 'border-[var(--border-hairline)] bg-[var(--bg-canvas)] hover:border-[var(--border-strong)] hover:translate-y-[-1px]' }}">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-2.5">
                        <input
                            type="radio"
                            wire:model.live="deploymentMode"
                            value="dedicated"
                            class="size-4 text-amber-600 focus:ring-amber-500"
                        />
                        <span class="text-xs font-bold text-[var(--text-primary)]">Dedicated Bespoke Client</span>
                    </div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-amber-500/10 text-amber-700 dark:text-amber-400">White-Label Mode</span>
                </div>
                <p class="text-xs text-[var(--text-secondary)] mt-2.5 leading-relaxed">
                    Root <code class="px-1 py-0.5 rounded bg-[var(--bg-surface)] font-mono text-[10px]">/</code> serves the designated flagship client's bespoke portal with zero SaaS branding. All platform marketing is hidden from navigation.
                </p>
            </label>
        </div>

        @if ($deploymentMode === 'dedicated')
            <div class="pt-3 border-t border-[var(--border-hairline)] flex flex-col sm:flex-row sm:items-center gap-3">
                <label for="homeOrgSlugInput" class="text-xs font-semibold text-[var(--text-primary)] min-w-44">
                    Flagship Dedicated Client:
                </label>
                <div class="flex-1 max-w-md">
                    <select
                        id="homeOrgSlugInput"
                        wire:model.live="homeOrgSlug"
                        class="w-full px-3.5 py-2 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden"
                    >
                        <option value="">-- Choose organization as dedicated client --</option>
                        @foreach ($allOrganizations as $org)
                            <option value="{{ $org->slug }}">
                                {{ $org->name }} (slug: {{ $org->slug }})
                            </option>
                        @endforeach
                    </select>
                </div>
                @if ($homeOrgSlug)
                    <div class="text-[11px] text-[var(--text-tertiary)] flex items-center gap-1.5">
                        <span class="size-1.5 rounded-full bg-amber-500"></span>
                        <span>Routing target: <code class="font-mono text-[10px] text-[var(--text-secondary)]">/{{ $homeOrgSlug }}</code></span>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Tenant Roster & Fleet Operations -->
    <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-2xs overflow-hidden">
        
        <!-- Table Search Bar -->
        <div class="p-4 sm:p-5 border-b border-[var(--border-hairline)] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-[var(--text-primary)]">Registered Tenant Fleet</h3>
                <p class="text-xs text-[var(--text-secondary)] mt-0.5">Inspect organization footprints, monitor active portals, and access tenant control panels.</p>
            </div>

            <div class="relative w-full sm:w-72">
                <input
                    type="search"
                    wire:model.live.debounce.250ms="tenantSearch"
                    placeholder="Search tenant by name or slug..."
                    class="w-full pl-9 pr-4 py-2 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] text-xs text-[var(--text-primary)] placeholder:text-[var(--text-tertiary)] focus:border-[var(--color-primary)] focus:outline-hidden transition"
                />
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[var(--text-tertiary)]">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Tenant Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[11px] font-semibold text-[var(--text-secondary)]">
                    <tr>
                        <th class="px-5 py-3">Organization</th>
                        <th class="px-5 py-3">Slug / Domain</th>
                        <th class="px-5 py-3">Portals</th>
                        <th class="px-5 py-3">Onboarded</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-hairline)] text-xs">
                    @forelse ($tenants as $tenant)
                        <tr class="hover:bg-[var(--bg-subtle)]/40 transition">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="size-8 rounded-xl bg-[var(--bg-subtle)] border border-[var(--border-hairline)] flex items-center justify-center font-bold text-xs text-[var(--text-primary)]">
                                        {{ strtoupper(substr($tenant->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-[var(--text-primary)]">{{ $tenant->name }}</div>
                                        <div class="text-[10px] text-[var(--text-tertiary)] font-mono">Org #{{ $tenant->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-[var(--text-secondary)]">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-[var(--bg-subtle)] text-[11px]">
                                    /{{ $tenant->slug }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[var(--bg-subtle)] font-medium text-[11px] text-[var(--text-primary)]">
                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                    <span>{{ $tenant->events_count }} {{ \Illuminate\Support\Str::plural('event', $tenant->events_count) }}</span>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-[var(--text-secondary)]">
                                {{ $tenant->created_at?->format('M j, Y') ?? 'N/A' }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        wire:click="viewTenant({{ $tenant->id }})"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition active:scale-[0.97] cursor-pointer"
                                        title="Inspect Tenant Metrics"
                                    >
                                        <span>Inspect</span>
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="jumpToTenantAdmin({{ $tenant->id }})"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold hover:opacity-95 transition active:scale-[0.97] cursor-pointer"
                                        title="Jump into Tenant Admin Console"
                                    >
                                        <span>Admin</span>
                                        <span>→</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-xs text-[var(--text-secondary)]">
                                No tenants found matching your filter criteria.
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
            <div class="w-full max-w-2xl rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 sm:p-7 shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-150">
                
                <div class="flex items-start justify-between pb-4 border-b border-[var(--border-hairline)]">
                    <div class="flex items-center gap-3">
                        <div class="size-11 rounded-2xl bg-[var(--bg-subtle)] border border-[var(--border-hairline)] flex items-center justify-center font-bold text-sm text-[var(--text-primary)]">
                            {{ strtoupper(substr($selectedTenant->name, 0, 2)) }}
                        </div>
                        <div>
                            <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-[var(--text-tertiary)]">Tenant Inspection</span>
                            <h3 class="text-base sm:text-lg font-bold text-[var(--text-primary)]">{{ $selectedTenant->name }}</h3>
                            <p class="text-xs text-[var(--text-secondary)]">Slug: <span class="font-mono text-[var(--text-primary)]">/{{ $selectedTenant->slug }}</span></p>
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click="closeTenantModal"
                        class="p-1.5 rounded-xl text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition active:scale-[0.96] cursor-pointer"
                        aria-label="Close"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Tenant Quick Metric Pills -->
                @if ($selectedTenantStats)
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] p-3">
                            <span class="text-[10px] text-[var(--text-secondary)] font-medium">Team Members</span>
                            <div class="text-base font-bold text-[var(--text-primary)]">{{ $selectedTenantStats['usersCount'] }}</div>
                        </div>
                        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] p-3">
                            <span class="text-[10px] text-[var(--text-secondary)] font-medium">Paid Orders</span>
                            <div class="text-base font-bold text-[var(--text-primary)]">{{ $selectedTenantStats['ordersCount'] }}</div>
                        </div>
                        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] p-3">
                            <span class="text-[10px] text-[var(--text-secondary)] font-medium">Total Passes</span>
                            <div class="text-base font-bold text-[var(--text-primary)]">{{ $selectedTenantStats['attendeesCount'] }}</div>
                        </div>
                        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] p-3">
                            <span class="text-[10px] text-[var(--text-secondary)] font-medium">Gross Revenue</span>
                            <div class="text-base font-bold text-emerald-600 dark:text-emerald-400">GHS {{ number_format($selectedTenantStats['revenue'], 2) }}</div>
                        </div>
                    </div>
                @endif

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold text-[var(--text-primary)] uppercase tracking-wider">
                            Active Events & Portals ({{ $tenantEvents->count() }})
                        </h4>
                    </div>

                    @if ($tenantEvents->isEmpty())
                        <div class="rounded-2xl border border-dashed border-[var(--border-hairline)] p-8 text-center text-xs text-[var(--text-secondary)]">
                            This tenant has not created any events yet.
                        </div>
                    @else
                        <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                            @foreach ($tenantEvents as $ev)
                                <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] p-3.5 flex items-center justify-between text-xs hover:border-[var(--border-strong)] transition">
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-[var(--text-primary)]">{{ $ev->title }}</span>
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-semibold {{ $ev->status === 'published' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-400' }}">
                                                {{ ucfirst($ev->status) }}
                                            </span>
                                        </div>
                                        <p class="text-[10px] text-[var(--text-tertiary)]">
                                            {{ $ev->start_at ? $ev->start_at->format('M j, Y') : 'Date TBA' }} · {{ $ev->venue ?: 'No venue specified' }}
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <span class="text-[10px] font-mono text-[var(--text-secondary)]">
                                            {{ $ev->ticketTypes->count() }} tiers
                                        </span>
                                        <a
                                            href="{{ route('public.event.show', ['org_slug' => $selectedTenant->slug, 'event_slug' => $ev->slug]) }}"
                                            target="_blank"
                                            class="inline-flex items-center gap-1 text-[10px] font-semibold text-[var(--color-primary)] hover:underline"
                                        >
                                            <span>Visit Page</span>
                                            <span>↗</span>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="pt-4 border-t border-[var(--border-hairline)] flex items-center justify-between">
                    <button
                        type="button"
                        wire:click="jumpToTenantAdmin({{ $selectedTenant->id }})"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold hover:opacity-95 transition active:scale-[0.97] cursor-pointer"
                    >
                        <span>Access Admin Dashboard</span>
                        <span>→</span>
                    </button>

                    <button
                        type="button"
                        wire:click="closeTenantModal"
                        class="px-4 py-2 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-xs font-semibold text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition active:scale-[0.97] cursor-pointer"
                    >
                        Close
                    </button>
                </div>

            </div>
        </div>
    @endif

</div>
