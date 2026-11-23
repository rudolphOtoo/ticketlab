<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Organisations</h1>
            <p class="mt-0.5 text-xs text-[var(--text-secondary)] sm:text-sm">Every church and organisation on TicketLab, and what still blocks their payouts.</p>
        </div>
        <a href="{{ route('platform.organizations.create') }}" wire:navigate class="inline-flex items-center justify-center rounded-xl bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-[var(--color-primary-fg)] hover:opacity-90">+ Onboard organisation</a>
    </div>

    @if ($flashMessage)
        <div class="flex items-center justify-between rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-xs font-medium text-emerald-800 dark:text-emerald-300">
            <span>{{ $flashMessage }}</span>
            <button type="button" wire:click="$set('flashMessage', null)" class="cursor-pointer">&times;</button>
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-4">
        <input type="search" wire:model.live.debounce.250ms="search" placeholder="Search by name or slug…" aria-label="Search organisations" class="w-full max-w-sm rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3.5 py-2 text-xs" />
        <label class="inline-flex items-center gap-2 text-xs text-[var(--text-secondary)]">
            <input type="checkbox" wire:model.live="showArchived" data-show-archived />
            Show archived{{ $archivedCount > 0 ? " ({$archivedCount})" : '' }}
        </label>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)]">
        <table class="w-full min-w-[820px] text-left text-xs">
            <thead class="border-b border-[var(--border-hairline)] text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                <tr>
                    <th class="px-4 py-3">Organisation</th>
                    <th class="px-4 py-3 text-right">Events</th>
                    <th class="px-4 py-3">Zones without a payout account</th>
                    <th class="px-4 py-3 text-right">Paid total</th>
                    <th class="px-4 py-3">Organisation admin</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border-hairline)]">
                @forelse ($organizations as $organization)
                    @php
                        $missing = $missingSubaccounts[$organization->id];
                        $admin = $organization->users->first();
                    @endphp
                    <tr data-organization="{{ $organization->slug }}">
                        <td class="px-4 py-3">
                            <a href="{{ route('platform.organizations.edit', $organization) }}" wire:navigate class="block font-semibold hover:underline">{{ $organization->name }}</a>
                            @unless ($organization->isActive())
                                <x-ui.badge :color="$organization->status === \App\Enums\OrganizationStatus::Suspended ? 'amber' : 'zinc'" pill size="sm">{{ $organization->status->label() }}</x-ui.badge>
                            @endunless
                            <a href="{{ url('/' . $organization->slug) }}" target="_blank" rel="noopener" class="font-mono text-[11px] text-[var(--text-secondary)] underline">/{{ $organization->slug }}</a>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums" data-events-count>{{ $organization->events_count }}</td>
                        <td class="px-4 py-3" data-missing-subaccounts>
                            @if ($organization->subdivisions->isEmpty())
                                <span class="text-[var(--status-danger)]">No {{ strtolower(\Illuminate\Support\Str::plural($organization->subdivision_label)) }} yet</span>
                            @elseif ($missing === [])
                                <span class="text-[var(--status-success)]">All {{ $organization->subdivisions->count() }} ready</span>
                            @else
                                <span class="text-[var(--status-warning)]">{{ count($missing) }} of {{ $organization->subdivisions->count() }}: {{ implode(', ', $missing) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-mono tabular-nums" data-paid-total>GHS {{ number_format((float) $organization->paid_total, 2) }}</td>
                        <td class="px-4 py-3">
                            @if ($admin === null)
                                <span class="text-[var(--status-danger)]">None</span>
                            @else
                                <span class="block">{{ $admin->email }}</span>
                                @if (blank($admin->password))
                                    <span class="text-[11px] text-[var(--status-warning)]">Invitation pending ·</span>
                                    <button type="button" wire:click="resendInvitation({{ $admin->id }})" class="cursor-pointer text-[11px] font-semibold underline">Resend</button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-[var(--text-secondary)]">No organisations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
