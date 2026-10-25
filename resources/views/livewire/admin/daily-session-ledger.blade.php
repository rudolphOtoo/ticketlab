<div class="flex flex-col gap-8">
    <!-- Page Header & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex flex-col gap-1">
            <span class="text-xs font-semibold tracking-wider uppercase text-[var(--text-tertiary)]">Operations Ledger</span>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-[var(--text-primary)]">Financial Closeout & Attendance</h1>
            <p class="text-xs sm:text-sm text-[var(--text-secondary)]">Live reconciliation of omni-channel gate collections, Mobile Money routing, and terminal turnout.</p>
        </div>

        <div class="flex items-center gap-3">
            <span class="text-xs text-[var(--text-tertiary)] hidden md:inline">Refreshed just now</span>
            <button type="button" class="cursor-pointer inline-flex items-center gap-2 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3.5 py-2 text-xs font-medium text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] shadow-xs transition">
                <svg class="size-3.5 text-[var(--text-secondary)]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Sync Gate Feed
            </button>
        </div>
    </div>

    <!-- 4 Top KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Gross Revenue -->
        <div class="flex flex-col justify-between rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 shadow-xs">
            <div class="flex items-center justify-between text-xs text-[var(--text-secondary)]">
                <span>Gross Revenue Today</span>
                <svg class="size-4 text-[var(--text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v12.75m0-12.75h19.5m0 0v12.75M3.75 4.5h16.5M3.75 4.5A2.25 2.25 0 0 0 1.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25h16.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25" />
                </svg>
            </div>
            <div class="mt-4 flex flex-col gap-1.5">
                <span class="text-2xl font-bold tracking-tight text-[var(--text-primary)] font-mono">GHS {{ number_format($grossRevenue, 2) }}</span>
                <div class="flex items-center gap-1.5 text-xs">
                    <span class="inline-flex items-center rounded-md bg-[var(--status-success-bg)] px-1.5 py-0.5 text-[11px] font-medium text-[var(--status-success)]">
                        +12% vs Day 1
                    </span>
                    <span class="text-[var(--text-tertiary)]">from all gates</span>
                </div>
            </div>
        </div>

        <!-- Card 2: Gate Turnout -->
        <div class="flex flex-col justify-between rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 shadow-xs">
            <div class="flex items-center justify-between text-xs text-[var(--text-secondary)]">
                <span>Gate Turnout</span>
                <span class="inline-flex items-center rounded-md bg-[var(--status-success-bg)] px-1.5 py-0.5 text-[11px] font-medium text-[var(--status-success)]">
                    {{ $turnoutRate }}% admitted
                </span>
            </div>
            <div class="mt-4 flex flex-col gap-2">
                <div class="flex items-baseline gap-1 font-mono">
                    <span class="text-2xl font-bold tracking-tight text-[var(--text-primary)]">{{ number_format($turnoutCount) }}</span>
                    <span class="text-xs text-[var(--text-tertiary)]">/ {{ number_format($capacityTotal) }}</span>
                </div>
                <!-- Progress Line -->
                <div class="h-1.5 w-full rounded-full bg-[var(--bg-subtle)] overflow-hidden">
                    <div class="h-full rounded-full bg-[var(--status-success)]" style="width: {{ $turnoutRate }}%"></div>
                </div>
                <div class="flex items-center justify-between text-[11px] text-[var(--text-tertiary)]">
                    <span>Verified Check-ins</span>
                    <span>{{ $capacityTotal - $turnoutCount }} remaining</span>
                </div>
            </div>
        </div>

        <!-- Card 3: Online Paystack -->
        <div class="flex flex-col justify-between rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 shadow-xs">
            <div class="flex items-center justify-between text-xs text-[var(--text-secondary)]">
                <span>Online Paystack</span>
                <svg class="size-4 text-[var(--text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                </svg>
            </div>
            <div class="mt-4 flex flex-col gap-1.5">
                <span class="text-2xl font-bold tracking-tight text-[var(--text-primary)] font-mono">GHS {{ number_format($totalOnlinePaystack, 2) }}</span>
                <span class="text-xs text-[var(--text-secondary)]">(82% Mobile Money, 18% Card)</span>
                <span class="text-[11px] text-[var(--text-tertiary)]">Automated settlement cleared</span>
            </div>
        </div>

        <!-- Card 4: Cash Desk Tender -->
        <div class="flex flex-col justify-between rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 shadow-xs">
            <div class="flex items-center justify-between text-xs text-[var(--text-secondary)]">
                <span>Cash Desk Tender</span>
                <svg class="size-4 text-[var(--status-warning)]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <div class="mt-4 flex flex-col gap-1.5">
                <span class="text-2xl font-bold tracking-tight text-[var(--text-primary)] font-mono">GHS {{ number_format($totalGateCash, 2) }}</span>
                <span class="text-xs text-[var(--text-secondary)]">(184 walk-in cash orders)</span>
                <span class="text-[11px] text-[var(--text-tertiary)]">Physical vault drawer locked</span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left: Zone Financials & Gate Throughput (2 Columns) -->
        <div class="lg:col-span-2 flex flex-col gap-4 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex flex-col">
                    <h2 class="text-base font-semibold text-[var(--text-primary)]">Zone Financials & Gate Throughput</h2>
                    <p class="text-xs text-[var(--text-secondary)]">Real-time terminal collections segmented by regional entry perimeter</p>
                </div>

                <div class="flex items-center gap-1.5 rounded-lg bg-[var(--bg-subtle)] p-1 text-xs font-medium">
                    <button type="button" class="rounded-md bg-[var(--bg-surface)] px-2.5 py-1 text-[var(--text-primary)] shadow-xs">All Zones</button>
                    <button type="button" class="rounded-md px-2.5 py-1 text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition">Main Arena</button>
                    <button type="button" class="rounded-md px-2.5 py-1 text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition">North Grounds</button>
                </div>
            </div>

            <!-- Table -->
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-[var(--border-hairline)] text-[var(--text-tertiary)] uppercase text-[10px] tracking-wider">
                            <th class="pb-3 font-semibold">Zone</th>
                            <th class="pb-3 text-right font-semibold">Online Momo</th>
                            <th class="pb-3 text-right font-semibold">Cash Collected</th>
                            <th class="pb-3 text-center font-semibold">Turnout Rate</th>
                            <th class="pb-3 text-right font-semibold">Net Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--border-hairline)]">
                        <tr class="hover:bg-[var(--bg-subtle)] transition">
                            <td class="py-3.5">
                                <div class="flex items-center gap-2">
                                    <span class="size-2 rounded-full bg-[var(--status-success)]"></span>
                                    <div class="flex flex-col">
                                        <span class="font-medium text-[var(--text-primary)]">Accra Central</span>
                                        <span class="text-[10px] text-[var(--text-tertiary)]">Gates 1, 2 & Main Concourse</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 text-right font-mono text-[var(--text-secondary)]">GHS 29,400.00</td>
                            <td class="py-3.5 text-right font-mono text-[var(--text-secondary)]">GHS 7,100.00</td>
                            <td class="py-3.5 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <span class="font-medium text-[var(--status-success)]">92%</span>
                                    <span class="text-[10px] text-[var(--text-tertiary)] font-mono">552 / 600</span>
                                </div>
                            </td>
                            <td class="py-3.5 text-right font-mono font-semibold text-[var(--text-primary)]">GHS 36,500.00</td>
                        </tr>

                        <tr class="hover:bg-[var(--bg-subtle)] transition">
                            <td class="py-3.5">
                                <div class="flex items-center gap-2">
                                    <span class="size-2 rounded-full bg-[var(--status-success)]"></span>
                                    <div class="flex flex-col">
                                        <span class="font-medium text-[var(--text-primary)]">Kumasi South</span>
                                        <span class="text-[10px] text-[var(--text-tertiary)]">Terminal B & VIP Entry</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 text-right font-mono text-[var(--text-secondary)]">GHS 12,650.00</td>
                            <td class="py-3.5 text-right font-mono text-[var(--text-secondary)]">GHS 3,450.00</td>
                            <td class="py-3.5 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <span class="font-medium text-[var(--status-success)]">78%</span>
                                    <span class="text-[10px] text-[var(--text-tertiary)] font-mono">312 / 400</span>
                                </div>
                            </td>
                            <td class="py-3.5 text-right font-mono font-semibold text-[var(--text-primary)]">GHS 16,100.00</td>
                        </tr>

                        <tr class="hover:bg-[var(--bg-subtle)] transition">
                            <td class="py-3.5">
                                <div class="flex items-center gap-2">
                                    <span class="size-2 rounded-full bg-[var(--status-warning)]"></span>
                                    <div class="flex flex-col">
                                        <span class="font-medium text-[var(--text-primary)]">Cape Coast North</span>
                                        <span class="text-[10px] text-[var(--text-tertiary)]">Gate C & Staff Accreditation</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 text-right font-mono text-[var(--text-secondary)]">GHS 6,200.00</td>
                            <td class="py-3.5 text-right font-mono text-[var(--text-secondary)]">GHS 1,850.00</td>
                            <td class="py-3.5 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <span class="font-medium text-[var(--status-warning)]">73%</span>
                                    <span class="text-[10px] text-[var(--text-tertiary)] font-mono">256 / 350</span>
                                </div>
                            </td>
                            <td class="py-3.5 text-right font-mono font-semibold text-[var(--text-primary)]">GHS 8,050.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Reconciliation Total -->
            <div class="mt-4 flex flex-col sm:flex-row items-center justify-between border-t border-[var(--border-hairline)] pt-4 text-xs">
                <span class="text-[var(--text-tertiary)] flex items-center gap-1.5">
                    <svg class="size-4 text-[var(--status-success)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Encrypted with audit settlement token
                </span>
                <div class="flex items-baseline gap-2">
                    <span class="text-[var(--text-secondary)]">Combined Reconciled Total:</span>
                    <span class="text-sm font-bold font-mono text-[var(--text-primary)]">GHS {{ number_format($grossRevenue, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Right Rail: Fast Operations & Live Feed (1 Column) -->
        <div class="flex flex-col gap-6">
            <!-- Fast Lane Operations -->
            <div class="flex flex-col gap-4 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-xs">
                <span class="text-xs font-semibold tracking-wider uppercase text-[var(--text-tertiary)]">Fast Lane Operations</span>

                <div class="flex flex-col gap-3">
                    <a href="{{ url('/ops/scanner') }}" class="cursor-pointer flex items-center justify-center gap-2.5 rounded-lg bg-[var(--accent-brand)] py-3 px-4 text-xs font-medium text-[var(--accent-brand-fg)] shadow-xs transition hover:opacity-90 active:scale-[0.98]" wire:navigate>
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" />
                        </svg>
                        [ Open Gate Scanner ]
                    </a>

                    <a href="{{ url('/ops/cash-desk') }}" class="cursor-pointer flex items-center justify-center gap-2.5 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] py-3 px-4 text-xs font-medium text-[var(--text-primary)] hover:bg-[var(--bg-hover)] transition active:scale-[0.98]" wire:navigate>
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v12.75m0-12.75h19.5m0 0v12.75M3.75 4.5h16.5M3.75 4.5A2.25 2.25 0 0 0 1.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25h16.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25" />
                        </svg>
                        [ Cash Desk Register ]
                    </a>
                </div>

                <div class="mt-1 flex items-center justify-between text-[11px] text-[var(--text-tertiary)] border-t border-[var(--border-hairline)] pt-3">
                    <span class="flex items-center gap-1.5">
                        <span class="size-1.5 rounded-full bg-[var(--status-success)]"></span>
                        4 Gate Terminals Active
                    </span>
                    <span>Latency: 14ms</span>
                </div>
            </div>

            <!-- Live Gate Feed -->
            <div class="flex flex-col gap-3 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold tracking-wider uppercase text-[var(--text-tertiary)]">Live Gate Feed</span>
                    <span class="text-[10px] text-[var(--status-success)] flex items-center gap-1">
                        <span class="size-1.5 rounded-full bg-[var(--status-success)] animate-pulse"></span>
                        Real-time
                    </span>
                </div>

                <div class="flex flex-col divide-y divide-[var(--border-hairline)] text-xs">
                    <div class="flex items-center justify-between py-2.5">
                        <div class="flex items-center gap-2.5">
                            <div class="size-7 rounded-full bg-[var(--bg-subtle)] flex items-center justify-center font-bold text-[10px] text-[var(--text-primary)]">AS</div>
                            <div class="flex flex-col">
                                <span class="font-medium text-[var(--text-primary)]">Ama Serwah</span>
                                <span class="text-[10px] text-[var(--text-tertiary)]">Gate A · VIP Gold</span>
                            </div>
                        </div>
                        <span class="text-[10px] text-[var(--text-tertiary)]">2m ago</span>
                    </div>

                    <div class="flex items-center justify-between py-2.5">
                        <div class="flex items-center gap-2.5">
                            <div class="size-7 rounded-full bg-[var(--bg-subtle)] flex items-center justify-center font-bold text-[10px] text-[var(--text-primary)]">KM</div>
                            <div class="flex flex-col">
                                <span class="font-medium text-[var(--text-primary)]">Kofi Mensah</span>
                                <span class="text-[10px] text-[var(--text-tertiary)]">Gate B · Regular</span>
                            </div>
                        </div>
                        <span class="text-[10px] text-[var(--text-tertiary)]">4m ago</span>
                    </div>

                    <div class="flex items-center justify-between py-2.5">
                        <div class="flex items-center gap-2.5">
                            <div class="size-7 rounded-full bg-[var(--bg-subtle)] flex items-center justify-center font-bold text-[10px] text-[var(--text-primary)]">NK</div>
                            <div class="flex flex-col">
                                <span class="font-medium text-[var(--text-primary)]">Nana Kwame</span>
                                <span class="text-[10px] text-[var(--text-tertiary)]">Gate A · VIP Diamond</span>
                            </div>
                        </div>
                        <span class="text-[10px] text-[var(--text-tertiary)]">7m ago</span>
                    </div>

                    <div class="flex items-center justify-between py-2.5">
                        <div class="flex items-center gap-2.5">
                            <div class="size-7 rounded-full bg-[var(--bg-subtle)] flex items-center justify-center font-bold text-[10px] text-[var(--text-primary)]">AO</div>
                            <div class="flex flex-col">
                                <span class="font-medium text-[var(--text-primary)]">Abena Osei</span>
                                <span class="text-[10px] text-[var(--text-tertiary)]">Gate C · Early Bird</span>
                            </div>
                        </div>
                        <span class="text-[10px] text-[var(--text-tertiary)]">11m ago</span>
                    </div>
                </div>

                <a href="{{ url('/admin/attendees') }}" class="text-center text-xs font-medium text-[var(--text-secondary)] hover:text-[var(--text-primary)] pt-2 transition" wire:navigate>
                    Review all 1,120 admissions &rarr;
                </a>
            </div>
        </div>
    </div>
</div>
