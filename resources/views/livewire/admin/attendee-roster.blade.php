<div class="flex flex-col gap-4 sm:gap-5">

    <!-- Page Header & Global Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex flex-col gap-0.5">
            <div class="flex items-center gap-2">
                <span class="text-[10px] sm:text-xs font-semibold tracking-wider uppercase text-[var(--text-tertiary)]">Access Roster · Gate Accreditation</span>
                @if($isUserScoped)
                    <span class="inline-flex items-center gap-1 rounded-full bg-[var(--status-warning-bg)] px-2 py-0.5 text-[10px] font-medium text-[var(--status-warning)]">
                        Scoped: {{ $scopedZoneName }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-[var(--status-success-bg)] px-2 py-0.5 text-[10px] font-medium text-[var(--status-success)]">
                        Cross-Zone Superadmin
                    </span>
                @endif
            </div>
            <h1 class="text-lg sm:text-xl md:text-2xl font-bold tracking-tight text-[var(--text-primary)]">Attendee Roster</h1>
            <p class="text-xs sm:text-sm text-[var(--text-secondary)]">Search, verify gate credentials, and manage attendee admission statuses.</p>
        </div>

        <div class="flex items-center gap-2">
            <!-- Export CSV Action -->
            <button type="button"
                    wire:click="exportCsv"
                    class="cursor-pointer inline-flex items-center gap-1.5 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-1.5 text-xs font-medium text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] shadow-xs transition active:scale-95">
                <svg class="size-3.5 text-[var(--text-secondary)]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Export CSV
            </button>

            <a href="{{ url('/ops/scanner') }}" class="cursor-pointer inline-flex items-center gap-1.5 rounded-lg bg-[var(--accent-brand)] px-3 py-1.5 text-xs font-semibold text-[var(--accent-brand-fg)] hover:opacity-90 transition shadow-xs" wire:navigate>
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" />
                </svg>
                Open Camera Scanner
            </a>
        </div>
    </div>

    <!-- 3 Metric Counter Badges -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3 sm:p-3.5 shadow-xs flex items-center justify-between">
            <div class="flex flex-col">
                <span class="text-[10px] sm:text-[11px] font-semibold text-[var(--text-tertiary)] uppercase tracking-wider">Total Registered</span>
                <span class="text-xl font-bold font-mono text-[var(--text-primary)]">{{ number_format($totalCount) }}</span>
            </div>
            <span class="size-8 rounded-lg bg-[var(--bg-subtle)] flex items-center justify-center text-[var(--text-secondary)]">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
            </span>
        </div>

        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3 sm:p-3.5 shadow-xs flex items-center justify-between">
            <div class="flex flex-col">
                <span class="text-[10px] sm:text-[11px] font-semibold text-[var(--status-success)] uppercase tracking-wider">Admitted & Checked In</span>
                <span class="text-xl font-bold font-mono text-[var(--status-success)]">{{ number_format($checkedInCount) }}</span>
            </div>
            <span class="size-8 rounded-lg bg-[var(--status-success-bg)] flex items-center justify-center text-[var(--status-success)]">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
            </span>
        </div>

        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3 sm:p-3.5 shadow-xs flex items-center justify-between">
            <div class="flex flex-col">
                <span class="text-[10px] sm:text-[11px] font-semibold text-[var(--status-warning)] uppercase tracking-wider">Awaiting Admission</span>
                <span class="text-xl font-bold font-mono text-[var(--status-warning)]">{{ number_format($pendingCount) }}</span>
            </div>
            <span class="size-8 rounded-lg bg-[var(--status-warning-bg)] flex items-center justify-center text-[var(--status-warning)]">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            </span>
        </div>
    </div>

    <!-- Filter Bar & Search Controls -->
    <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3 sm:p-4 shadow-xs flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 text-xs">
        
        <!-- Search Input with icon -->
        <div class="relative flex-1">
            <svg class="size-3.5 text-[var(--text-tertiary)] absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Search attendee name, email, phone, or QR code..."
                   class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] pl-9 pr-8 py-1.5 text-xs text-[var(--text-primary)] placeholder-[var(--text-tertiary)] focus:border-[var(--text-primary)] focus:outline-hidden transition" />
            @if($search !== '')
                <button type="button" wire:click="$set('search', '')" class="cursor-pointer absolute right-2.5 top-1/2 -translate-y-1/2 text-[var(--text-tertiary)] hover:text-[var(--text-primary)]">✕</button>
            @endif
        </div>

        <!-- Filter Dropdowns & Status Pills -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Zone Filter (Only visible if superadmin) -->
            @if(! $isUserScoped)
                <select wire:model.live="zoneFilter" class="cursor-pointer rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-2.5 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--text-primary)] focus:outline-hidden">
                    <option value="">All Zones</option>
                    @foreach($subdivisions as $sub)
                        <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                    @endforeach
                </select>
            @endif

            <!-- Ticket Tier Filter -->
            <select wire:model.live="tierFilter" class="cursor-pointer rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-2.5 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--text-primary)] focus:outline-hidden">
                <option value="">All Tiers</option>
                @foreach($ticketTypes as $tier)
                    <option value="{{ $tier->id }}">{{ $tier->name }}</option>
                @endforeach
            </select>

            <!-- Status Tabs -->
            <div class="flex items-center rounded-lg bg-[var(--bg-subtle)] p-0.5 text-xs font-medium">
                <button type="button"
                        wire:click="$set('statusFilter', 'all')"
                        class="cursor-pointer rounded-md px-2.5 py-1 transition {{ $statusFilter === 'all' ? 'bg-[var(--bg-surface)] text-[var(--text-primary)] shadow-xs font-semibold' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]' }}">
                    All
                </button>
                <button type="button"
                        wire:click="$set('statusFilter', 'checked_in')"
                        class="cursor-pointer rounded-md px-2.5 py-1 transition {{ $statusFilter === 'checked_in' ? 'bg-[var(--bg-surface)] text-[var(--status-success)] shadow-xs font-semibold' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]' }}">
                    Admitted
                </button>
                <button type="button"
                        wire:click="$set('statusFilter', 'pending')"
                        class="cursor-pointer rounded-md px-2.5 py-1 transition {{ $statusFilter === 'pending' ? 'bg-[var(--bg-surface)] text-[var(--status-warning)] shadow-xs font-semibold' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]' }}">
                    Pending
                </button>
            </div>
        </div>

    </div>

    <!-- Attendees Table -->
    <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[var(--text-tertiary)] uppercase text-[10px] tracking-wider">
                        <th class="py-2.5 px-4 font-semibold">Attendee</th>
                        <th class="py-2.5 px-4 font-semibold">Zone</th>
                        <th class="py-2.5 px-4 font-semibold">Ticket Tier</th>
                        <th class="py-2.5 px-4 font-semibold">QR Pass Hash</th>
                        <th class="py-2.5 px-4 font-semibold text-center">Status</th>
                        <th class="py-2.5 px-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-hairline)]">
                    @forelse($attendees as $att)
                        <tr class="hover:bg-[var(--bg-subtle)] transition">
                            <!-- Attendee Profile -->
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="size-7 rounded-full bg-[var(--bg-subtle)] border border-[var(--border-hairline)] flex items-center justify-center font-bold text-[10px] text-[var(--text-primary)] shrink-0">
                                        {{ strtoupper(substr($att->name, 0, 2)) }}
                                    </div>
                                    <div class="flex flex-col min-w-0">
                                        <span class="font-medium text-[var(--text-primary)] truncate">{{ $att->name }}</span>
                                        <span class="text-[10px] text-[var(--text-tertiary)] truncate">{{ $att->email }} · {{ $att->phone ?? 'No phone' }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Zone / Subdivision -->
                            <td class="py-3 px-4 text-[var(--text-secondary)]">
                                <span class="inline-flex items-center gap-1 rounded-md bg-[var(--bg-subtle)] border border-[var(--border-hairline)] px-2 py-0.5 text-[10px]">
                                    {{ $att->subdivision ? $att->subdivision->name : 'All Zones' }}
                                </span>
                            </td>

                            <!-- Ticket Tier -->
                            <td class="py-3 px-4">
                                <span class="font-medium text-[var(--text-primary)]">{{ $att->ticketType ? $att->ticketType->name : 'Gate Pass' }}</span>
                            </td>

                            <!-- QR Code snippet -->
                            <td class="py-3 px-4 font-mono text-[10px] text-[var(--text-secondary)]">
                                <span class="rounded bg-[var(--bg-subtle)] px-1.5 py-0.5 border border-[var(--border-hairline)]">
                                    {{ substr($att->qr_code_hash, 0, 10) }}...
                                </span>
                            </td>

                            <!-- Check-In Status -->
                            <td class="py-3 px-4 text-center">
                                @if($att->isCheckedIn())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-[var(--status-success-bg)] px-2 py-0.5 text-[10px] font-semibold text-[var(--status-success)]">
                                        <span class="size-1.5 rounded-full bg-[var(--status-success)]"></span>
                                        Checked In
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-[var(--status-warning-bg)] px-2 py-0.5 text-[10px] font-semibold text-[var(--status-warning)]">
                                        <span class="size-1.5 rounded-full bg-[var(--status-warning)]"></span>
                                        Pending
                                    </span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="py-3 px-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <button type="button"
                                            wire:click="toggleCheckIn({{ $att->id }})"
                                            class="cursor-pointer rounded-md border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-2 py-1 text-[11px] font-medium transition hover:bg-[var(--bg-subtle)] {{ $att->isCheckedIn() ? 'text-[var(--status-danger)] hover:border-[var(--status-danger)]' : 'text-[var(--status-success)] hover:border-[var(--status-success)]' }}">
                                        {{ $att->isCheckedIn() ? 'Revert' : 'Admit' }}
                                    </button>

                                    <button type="button"
                                            wire:click="selectAttendee({{ $att->id }})"
                                            class="cursor-pointer rounded-md border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-2 py-1 text-[11px] font-medium text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-hover)] transition">
                                        Inspect
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-xs text-[var(--text-tertiary)]">
                                No attendees match the specified criteria or search query.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        @if($attendees->hasPages())
            <div class="p-3 border-t border-[var(--border-hairline)] bg-[var(--bg-surface)]">
                {{ $attendees->links() }}
            </div>
        @endif
    </div>

    <!-- Attendee Inspect Drawer Popover -->
    @if($selectedAttendee)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-in fade-in duration-200">
            <div class="relative w-full max-w-md rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 shadow-2xl flex flex-col gap-4 text-xs">
                
                <div class="flex items-center justify-between border-b border-[var(--border-hairline)] pb-3">
                    <div class="flex items-center gap-2">
                        <span class="flex size-7 items-center justify-center rounded-lg bg-[var(--bg-subtle)] font-bold text-xs text-[var(--text-primary)]">
                            {{ strtoupper(substr($selectedAttendee->name, 0, 2)) }}
                        </span>
                        <div class="flex flex-col">
                            <span class="font-bold text-sm text-[var(--text-primary)]">{{ $selectedAttendee->name }}</span>
                            <span class="text-[10px] text-[var(--text-tertiary)]">Attendee Record #{{ $selectedAttendee->id }}</span>
                        </div>
                    </div>
                    <button type="button" wire:click="closeDetailModal" class="cursor-pointer text-[var(--text-tertiary)] hover:text-[var(--text-primary)] p-1 rounded-md">✕</button>
                </div>

                <div class="grid grid-cols-2 gap-3 py-1">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-[10px] uppercase font-semibold text-[var(--text-tertiary)]">Email Address</span>
                        <span class="font-medium text-[var(--text-primary)] truncate">{{ $selectedAttendee->email }}</span>
                    </div>

                    <div class="flex flex-col gap-0.5">
                        <span class="text-[10px] uppercase font-semibold text-[var(--text-tertiary)]">Mobile Phone</span>
                        <span class="font-medium text-[var(--text-primary)]">{{ $selectedAttendee->phone ?? 'N/A' }}</span>
                    </div>

                    <div class="flex flex-col gap-0.5">
                        <span class="text-[10px] uppercase font-semibold text-[var(--text-tertiary)]">Zone Perimeter</span>
                        <span class="font-medium text-[var(--text-primary)]">{{ $selectedAttendee->subdivision ? $selectedAttendee->subdivision->name : 'All Zones' }}</span>
                    </div>

                    <div class="flex flex-col gap-0.5">
                        <span class="text-[10px] uppercase font-semibold text-[var(--text-tertiary)]">Ticket Tier</span>
                        <span class="font-medium text-[var(--text-primary)]">{{ $selectedAttendee->ticketType ? $selectedAttendee->ticketType->name : 'N/A' }}</span>
                    </div>

                    <div class="flex flex-col gap-0.5 col-span-2">
                        <span class="text-[10px] uppercase font-semibold text-[var(--text-tertiary)]">QR Digital Pass Hash</span>
                        <span class="font-mono text-[11px] p-2 rounded bg-[var(--bg-subtle)] border border-[var(--border-hairline)] text-[var(--text-secondary)] break-all">
                            {{ $selectedAttendee->qr_code_hash }}
                        </span>
                    </div>

                    <div class="flex flex-col gap-0.5 col-span-2">
                        <span class="text-[10px] uppercase font-semibold text-[var(--text-tertiary)]">Check-in Audit Telemetry</span>
                        <div class="p-2 rounded bg-[var(--bg-subtle)] border border-[var(--border-hairline)] text-[11px]">
                            @if($selectedAttendee->isCheckedIn())
                                <span class="text-[var(--status-success)] font-semibold">✓ Admitted on {{ $selectedAttendee->checked_in_at->format('M d, Y · H:i:s') }}</span>
                            @else
                                <span class="text-[var(--status-warning)] font-semibold">⏳ Not yet admitted at gate.</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-2 border-t border-[var(--border-hairline)]">
                    <button type="button"
                            wire:click="toggleCheckIn({{ $selectedAttendee->id }})"
                            class="cursor-pointer flex-1 flex items-center justify-center gap-1.5 rounded-xl py-2 text-xs font-semibold transition {{ $selectedAttendee->isCheckedIn() ? 'bg-[var(--status-danger-bg)] text-[var(--status-danger)] border border-[var(--status-danger)] hover:bg-[var(--status-danger)] hover:text-white' : 'bg-[var(--status-success)] text-white hover:opacity-90' }}">
                        {{ $selectedAttendee->isCheckedIn() ? 'Revert Check-In' : 'Admit Attendee Now' }}
                    </button>
                    <button type="button"
                            wire:click="closeDetailModal"
                            class="cursor-pointer flex-1 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] py-2 text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-hover)] transition">
                        Close
                    </button>
                </div>

            </div>
        </div>
    @endif

</div>
