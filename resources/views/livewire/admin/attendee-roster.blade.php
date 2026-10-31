<div class="flex flex-col gap-4 sm:gap-5">

    <!-- Flash Alert -->
    @if ($flashMessage)
        <div class="flex items-center justify-between rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-xs font-medium text-emerald-800 dark:text-emerald-300 animate-in fade-in duration-150">
            <div class="flex items-center gap-2">
                <svg class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ $flashMessage }}</span>
            </div>
            <button type="button" wire:click="$set('flashMessage', null)" class="text-emerald-700 hover:text-emerald-900 dark:hover:text-emerald-100 cursor-pointer">
                &times;
            </button>
        </div>
    @endif

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
                        Full Access Admin
                    </span>
                @endif
            </div>
            <h1 class="text-lg sm:text-xl md:text-2xl font-bold tracking-tight text-[var(--text-primary)]">Attendee Roster</h1>
            <p class="text-xs sm:text-sm text-[var(--text-secondary)]">Search, verify gate credentials, issue complimentary passes, and manage accreditation.</p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <!-- Issue Pass Action -->
            <button
                type="button"
                wire:click="openManualPassModal"
                class="cursor-pointer inline-flex items-center gap-1.5 rounded-xl bg-[var(--color-primary)] px-3.5 py-1.5 text-xs font-semibold text-[var(--color-primary-fg)] hover:opacity-95 shadow-xs transition active:scale-95"
            >
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Issue Pass</span>
            </button>

            <!-- Export CSV Action -->
            <button
                type="button"
                wire:click="exportCsv"
                class="cursor-pointer inline-flex items-center gap-1.5 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-1.5 text-xs font-medium text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] shadow-xs transition active:scale-95"
            >
                <svg class="size-3.5 text-[var(--text-secondary)]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                <span>Export CSV</span>
            </button>

            <a href="{{ url('/ops/scanner') }}" class="cursor-pointer inline-flex items-center gap-1.5 rounded-xl bg-[var(--bg-subtle)] border border-[var(--border-hairline)] px-3 py-1.5 text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--border-hairline)] transition shadow-xs active:scale-95" wire:navigate>
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" />
                </svg>
                <span>Door Scanner</span>
            </a>
        </div>
    </div>

    <!-- 3 Metric Counter Badges -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3.5 sm:p-4 shadow-xs flex items-center justify-between hover:translate-y-[-1px] transition-transform">
            <div class="flex flex-col">
                <span class="text-[10px] sm:text-[11px] font-semibold text-[var(--text-tertiary)] uppercase tracking-wider">Total Registered</span>
                <span class="text-xl font-bold font-mono text-[var(--text-primary)]">{{ number_format($totalCount) }}</span>
            </div>
            <span class="size-9 rounded-xl bg-[var(--bg-subtle)] flex items-center justify-center text-[var(--text-secondary)]">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
            </span>
        </div>

        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3.5 sm:p-4 shadow-xs flex items-center justify-between hover:translate-y-[-1px] transition-transform">
            <div class="flex flex-col">
                <span class="text-[10px] sm:text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Admitted & Checked In</span>
                <span class="text-xl font-bold font-mono text-emerald-600 dark:text-emerald-400">{{ number_format($checkedInCount) }}</span>
            </div>
            <span class="size-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
            </span>
        </div>

        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3.5 sm:p-4 shadow-xs flex items-center justify-between hover:translate-y-[-1px] transition-transform">
            <div class="flex flex-col">
                <span class="text-[10px] sm:text-[11px] font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Pending At Gate</span>
                <span class="text-xl font-bold font-mono text-amber-600 dark:text-amber-400">{{ number_format($pendingCount) }}</span>
            </div>
            <span class="size-9 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            </span>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3.5 shadow-xs flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
        <div class="relative flex-1">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="size-4 text-[var(--text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
            </div>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Search by attendee name, email, phone, or QR hash prefix..."
                   class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] pl-9 pr-3 py-2 text-xs text-[var(--text-primary)] placeholder-[var(--text-tertiary)] focus:border-[var(--color-primary)] focus:outline-hidden transition" />
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- Admission Status Filter -->
            <select wire:model.live="statusFilter"
                    class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)] focus:outline-hidden cursor-pointer">
                <option value="all">All Gate Statuses</option>
                <option value="checked_in">Checked In Only</option>
                <option value="pending">Pending Only</option>
            </select>

            <!-- Ticket Tier Filter -->
            <select wire:model.live="tierFilter"
                    class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)] focus:outline-hidden cursor-pointer">
                <option value="">All Ticket Categories</option>
                @foreach($ticketTypes as $tier)
                    <option value="{{ $tier->id }}">{{ $tier->name }}</option>
                @endforeach
            </select>

            <!-- Subdivision Zone Filter (Only for un-scoped admins) -->
            @if(!$isUserScoped && $subdivisions->count() > 0)
                <select wire:model.live="zoneFilter"
                        class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)] focus:outline-hidden cursor-pointer">
                    <option value="">All Subdivisions</option>
                    @foreach($subdivisions as $sub)
                        <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>
    </div>

    <!-- Attendee Table View -->
    <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[10px] font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                    <tr>
                        <th class="py-3 px-4">Attendee Credentials</th>
                        <th class="py-3 px-4">Subdivision</th>
                        <th class="py-3 px-4">Pass Tier</th>
                        <th class="py-3 px-4">Digital Pass Hash</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-hairline)]">
                    @forelse($attendees as $att)
                        @php
                            $custom = (array) ($att->custom_fields ?? []);
                            $tags = (array) ($custom['tags'] ?? []);
                        @endphp
                        <tr class="hover:bg-[var(--bg-subtle)]/40 transition">
                            <!-- Attendee Profile & Tags -->
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="size-8 rounded-xl bg-[var(--bg-subtle)] border border-[var(--border-hairline)] flex items-center justify-center font-bold text-xs text-[var(--text-primary)]">
                                        {{ strtoupper(substr($att->name, 0, 2)) }}
                                    </div>
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-bold text-[var(--text-primary)]">{{ $att->name }}</span>
                                            @foreach ($tags as $tag)
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-semibold bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-500/20">
                                                    {{ $tag }}
                                                </span>
                                            @endforeach
                                        </div>
                                        <span class="text-[11px] text-[var(--text-secondary)]">{{ $att->email }} · {{ $att->phone ?: 'No phone' }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Subdivision -->
                            <td class="py-3 px-4 text-[var(--text-secondary)]">
                                <span class="inline-flex items-center gap-1 rounded-md bg-[var(--bg-subtle)] border border-[var(--border-hairline)] px-2 py-0.5 text-[10px]">
                                    {{ $att->subdivision ? $att->subdivision->name : 'All Subdivisions' }}
                                </span>
                            </td>

                            <!-- Ticket Tier -->
                            <td class="py-3 px-4">
                                <span class="font-medium text-[var(--text-primary)]">{{ $att->ticketType ? $att->ticketType->name : 'Gate Pass' }}</span>
                            </td>

                            <!-- QR Code snippet -->
                            <td class="py-3 px-4 font-mono text-[10px] text-[var(--text-secondary)]">
                                <span class="rounded-md bg-[var(--bg-subtle)] px-2 py-0.5 border border-[var(--border-hairline)]">
                                    {{ substr($att->qr_code_hash, 0, 10) }}...
                                </span>
                            </td>

                            <!-- Check-In Status -->
                            <td class="py-3 px-4 text-center">
                                @if($att->isCheckedIn())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:text-emerald-400">
                                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                        Checked In
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-400">
                                        <span class="size-1.5 rounded-full bg-amber-500"></span>
                                        Pending
                                    </span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="py-3 px-4 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button
                                        type="button"
                                        wire:click="toggleCheckIn({{ $att->id }})"
                                        class="cursor-pointer rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-2.5 py-1 text-[11px] font-semibold transition active:scale-95 {{ $att->isCheckedIn() ? 'text-rose-600 hover:border-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/20' : 'text-emerald-600 hover:border-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/20' }}"
                                    >
                                        {{ $att->isCheckedIn() ? 'Revert' : 'Admit' }}
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="openEditAttendeeModal({{ $att->id }})"
                                        class="cursor-pointer rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-1 text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition active:scale-95"
                                        title="Edit Attendee"
                                    >
                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="selectAttendee({{ $att->id }})"
                                        class="cursor-pointer rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-2 py-1 text-[11px] font-medium text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition active:scale-95"
                                        title="View Full Credentials"
                                    >
                                        Inspect
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="resendPassEmail({{ $att->id }})"
                                        class="cursor-pointer rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-1 text-[var(--text-secondary)] hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-950/20 transition active:scale-95"
                                        title="Resend Ticket Email"
                                    >
                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-xs text-[var(--text-tertiary)]">
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

    <!-- Manual Pass Issuance Modal (with Tag Chip System) -->
    @if($isManualPassModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-in fade-in duration-150" @keydown.window.escape="$wire.closeManualPassModal()">
            <div class="w-full max-w-lg rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[var(--border-hairline)]">
                    <div>
                        <h3 class="text-base font-bold text-[var(--text-primary)]">Issue Complimentary / VIP Pass</h3>
                        <p class="text-xs text-[var(--text-secondary)]">Create an authorized gate credential directly without payment checkout.</p>
                    </div>
                    <button type="button" wire:click="closeManualPassModal" class="cursor-pointer text-[var(--text-tertiary)] hover:text-[var(--text-primary)] p-1 text-base">✕</button>
                </div>

                <form wire:submit="issueManualPass" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-[var(--text-primary)] mb-1">Full Name *</label>
                            <input type="text" wire:model="manual_name" placeholder="Guest / VIP name" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" />
                            @error('manual_name') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[var(--text-primary)] mb-1">Email Address *</label>
                            <input type="email" wire:model="manual_email" placeholder="guest@example.com" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" />
                            @error('manual_email') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[var(--text-primary)] mb-1">Phone Number</label>
                            <input type="text" wire:model="manual_phone" placeholder="+233 24 000 0000" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[var(--text-primary)] mb-1">Ticket Category *</label>
                            <select wire:model="manual_ticket_type_id" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden cursor-pointer">
                                @foreach($ticketTypes as $tier)
                                    <option value="{{ $tier->id }}">{{ $tier->name }} ({{ $tier->available_quantity }} left)</option>
                                @endforeach
                            </select>
                            @error('manual_ticket_type_id') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[var(--text-primary)] mb-1">Subdivision (Optional)</label>
                            <select wire:model="manual_subdivision_id" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden cursor-pointer">
                                <option value="">General / All Zones</option>
                                @foreach($subdivisions as $sub)
                                    <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Custom Tag Chip System for Attendee -->
                    <div class="space-y-2 pt-2 border-t border-[var(--border-hairline)]">
                        <label class="block text-xs font-semibold text-[var(--text-primary)]">Badges & Category Tags</label>
                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                wire:model="newManualTagInput"
                                wire:keydown.enter.prevent="addManualTag"
                                placeholder="Add badge (VIP, Speaker, Press, Staff) and press Enter..."
                                class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden"
                            />
                            <button
                                type="button"
                                wire:click="addManualTag"
                                class="shrink-0 px-3 py-2 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--border-hairline)] transition active:scale-95 cursor-pointer"
                            >
                                + Add
                            </button>
                        </div>

                        @if(!empty($manual_tags))
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                @foreach($manual_tags as $idx => $tag)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[var(--bg-subtle)] border border-[var(--border-hairline)] text-xs font-semibold text-[var(--text-primary)]">
                                        <span>{{ $tag }}</span>
                                        <button type="button" wire:click="removeManualTag({{ $idx }})" class="text-[var(--text-tertiary)] hover:text-rose-500 cursor-pointer">&times;</button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-[var(--border-hairline)]">
                        <button type="button" wire:click="closeManualPassModal" class="px-4 py-2 rounded-xl border border-[var(--border-hairline)] text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] transition cursor-pointer">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-bold hover:opacity-95 shadow-xs transition active:scale-95 cursor-pointer">Issue Gate Pass</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Edit Attendee Modal (with Tag Chip System) -->
    @if($isEditModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-in fade-in duration-150" @keydown.window.escape="$wire.closeEditAttendeeModal()">
            <div class="w-full max-w-lg rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[var(--border-hairline)]">
                    <div>
                        <h3 class="text-base font-bold text-[var(--text-primary)]">Edit Attendee Record</h3>
                        <p class="text-xs text-[var(--text-secondary)]">Update registration details and badges.</p>
                    </div>
                    <button type="button" wire:click="closeEditAttendeeModal" class="cursor-pointer text-[var(--text-tertiary)] hover:text-[var(--text-primary)] p-1 text-base">✕</button>
                </div>

                <form wire:submit="updateAttendee" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-primary)] mb-1">Full Name *</label>
                        <input type="text" wire:model="edit_name" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" />
                        @error('edit_name') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-[var(--text-primary)] mb-1">Email Address *</label>
                            <input type="email" wire:model="edit_email" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" />
                            @error('edit_email') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[var(--text-primary)] mb-1">Phone Number</label>
                            <input type="text" wire:model="edit_phone" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" />
                        </div>
                    </div>

                    <!-- Custom Tag Chip System for Editing Attendee -->
                    <div class="space-y-2 pt-2 border-t border-[var(--border-hairline)]">
                        <label class="block text-xs font-semibold text-[var(--text-primary)]">Badges & Category Tags</label>
                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                wire:model="newEditTagInput"
                                wire:keydown.enter.prevent="addEditTag"
                                placeholder="Add badge and press Enter..."
                                class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden"
                            />
                            <button
                                type="button"
                                wire:click="addEditTag"
                                class="shrink-0 px-3 py-2 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--border-hairline)] transition active:scale-95 cursor-pointer"
                            >
                                + Add
                            </button>
                        </div>

                        @if(!empty($edit_tags))
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                @foreach($edit_tags as $idx => $tag)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[var(--bg-subtle)] border border-[var(--border-hairline)] text-xs font-semibold text-[var(--text-primary)]">
                                        <span>{{ $tag }}</span>
                                        <button type="button" wire:click="removeEditTag({{ $idx }})" class="text-[var(--text-tertiary)] hover:text-rose-500 cursor-pointer">&times;</button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-[var(--border-hairline)]">
                        <button type="button" wire:click="closeEditAttendeeModal" class="px-4 py-2 rounded-xl border border-[var(--border-hairline)] text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] transition cursor-pointer">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-bold hover:opacity-95 shadow-xs transition active:scale-95 cursor-pointer">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Attendee Inspect Drawer Popover -->
    @if($selectedAttendee)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-in fade-in duration-200">
            <div class="relative w-full max-w-md rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-2xl flex flex-col gap-4 text-xs animate-in zoom-in-95 duration-150">
                
                <div class="flex items-center justify-between border-b border-[var(--border-hairline)] pb-3">
                    <div class="flex items-center gap-2.5">
                        <span class="flex size-8 items-center justify-center rounded-xl bg-[var(--bg-subtle)] font-bold text-xs text-[var(--text-primary)] border border-[var(--border-hairline)]">
                            {{ strtoupper(substr($selectedAttendee->name, 0, 2)) }}
                        </span>
                        <div class="flex flex-col">
                            <span class="font-bold text-sm text-[var(--text-primary)]">{{ $selectedAttendee->name }}</span>
                            <span class="text-[10px] text-[var(--text-tertiary)] font-mono">Pass Record #{{ $selectedAttendee->id }}</span>
                        </div>
                    </div>
                    <button type="button" wire:click="closeDetailModal" class="cursor-pointer text-[var(--text-tertiary)] hover:text-[var(--text-primary)] p-1 text-base">✕</button>
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
                        <span class="text-[10px] uppercase font-semibold text-[var(--text-tertiary)]">Subdivision</span>
                        <span class="font-medium text-[var(--text-primary)]">{{ $selectedAttendee->subdivision ? $selectedAttendee->subdivision->name : 'All Subdivisions' }}</span>
                    </div>

                    <div class="flex flex-col gap-0.5">
                        <span class="text-[10px] uppercase font-semibold text-[var(--text-tertiary)]">Ticket Tier</span>
                        <span class="font-medium text-[var(--text-primary)]">{{ $selectedAttendee->ticketType ? $selectedAttendee->ticketType->name : 'N/A' }}</span>
                    </div>

                    <div class="flex flex-col gap-0.5 col-span-2">
                        <span class="text-[10px] uppercase font-semibold text-[var(--text-tertiary)]">QR Digital Pass Hash</span>
                        <span class="font-mono text-[10px] p-2.5 rounded-xl bg-[var(--bg-subtle)] border border-[var(--border-hairline)] text-[var(--text-secondary)] break-all">
                            {{ $selectedAttendee->qr_code_hash }}
                        </span>
                    </div>

                    <div class="flex flex-col gap-0.5 col-span-2">
                        <span class="text-[10px] uppercase font-semibold text-[var(--text-tertiary)]">Check-in Audit Telemetry</span>
                        <div class="p-2.5 rounded-xl bg-[var(--bg-subtle)] border border-[var(--border-hairline)] text-[11px]">
                            @if($selectedAttendee->isCheckedIn())
                                <span class="text-emerald-600 dark:text-emerald-400 font-semibold">✓ Admitted on {{ $selectedAttendee->checked_in_at->format('M d, Y · H:i:s') }}</span>
                            @else
                                <span class="text-amber-600 dark:text-amber-400 font-semibold">⏳ Not yet admitted at gate.</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-3 border-t border-[var(--border-hairline)]">
                    <button
                        type="button"
                        wire:click="resendPassEmail({{ $selectedAttendee->id }})"
                        class="cursor-pointer flex-1 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] py-2 text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--border-hairline)] transition active:scale-95"
                    >
                        Resend Pass
                    </button>

                    <button
                        type="button"
                        wire:click="voidPass({{ $selectedAttendee->id }})"
                        wire:confirm="Permanently void this pass and release the capacity seat?"
                        class="cursor-pointer rounded-xl border border-rose-500/20 text-rose-600 dark:text-rose-400 hover:bg-rose-500/10 py-2 px-3 text-xs font-semibold transition active:scale-95"
                    >
                        Void Pass
                    </button>

                    <button
                        type="button"
                        wire:click="closeDetailModal"
                        class="cursor-pointer flex-1 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] py-2 text-xs font-bold hover:opacity-95 transition active:scale-95"
                    >
                        Done
                    </button>
                </div>

            </div>
        </div>
    @endif

</div>
