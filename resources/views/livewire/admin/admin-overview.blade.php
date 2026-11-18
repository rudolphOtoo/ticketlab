@php
    $zl = auth()->user()?->zoneLabel() ?? 'Zone';
    $zls = auth()->user()?->zonesLabel() ?? 'Zones';
@endphp
@php
    $turnout = $stats['sold'] > 0 ? (int) round(($stats['checked_in'] / $stats['sold']) * 100) : 0;
    $orgWideLinks = ['admin.events', 'admin.tickets', 'admin.studio'];
    $quickLinks = [
        ['route' => 'admin.events', 'title' => 'Events', 'body' => 'Create events, upload banners, and set ticket visibility.'],
        ['route' => 'admin.tickets', 'title' => 'Tickets & Pricing', 'body' => 'Set up ticket tiers, capacities, and prices.'],
        ['route' => 'admin.attendees', 'title' => 'Attendees', 'body' => 'Search passes, check in attendees, and export rosters.'],
        ['route' => 'admin.broadcasts', 'title' => 'Broadcasts', 'body' => 'Send email announcements to ticket holders.'],
        ['route' => 'admin.sessions', 'title' => 'Financial Ledger', 'body' => 'Review cash and mobile money collections.'],
        ['route' => 'admin.studio', 'title' => 'Event Page Settings', 'body' => 'Update event branding, speakers, and schedule.'],
    ];
    if ($user?->subdivision_id !== null) {
        $quickLinks = array_values(array_filter($quickLinks, static fn(array $link): bool => !in_array($link['route'], $orgWideLinks, true)));
    }
@endphp

<div class="flex flex-col gap-5 sm:gap-6">
    <div class="flex flex-col gap-3 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div class="flex items-center gap-3.5">
            <div class="flex size-11 items-center justify-center rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-sm font-bold tracking-tight">
                {{ $user?->initials() }}
            </div>
            <div class="flex flex-col gap-0.5">
                <h1 class="text-base font-bold tracking-tight sm:text-lg md:text-xl">Welcome back, {{ $user?->name }}</h1>
                <p class="text-xs text-[var(--text-secondary)]">
                    {{ $event?->title ?? 'No event yet' }} · {{ $user?->subdivision ? 'Scoped to ' . $user->subdivision->name : 'All ' . strtolower($zls) }}
                </p>
            </div>
        </div>

        @if ($event)
            <x-ui.badge :color="$event->status === 'published' ? 'emerald' : 'amber'" variant="outline" pill>
                {{ ucfirst($event->status) }}{{ $event->is_public ? '' : ' · Unlisted' }}
            </x-ui.badge>
        @endif
    </div>

    @if ($event === null)
        <div class="rounded-3xl border border-dashed border-[var(--border-hairline)] bg-[var(--bg-surface)] p-10 text-center">
            <h2 class="text-sm font-semibold">Start by creating your first event</h2>
            <p class="mx-auto mt-1 max-w-sm text-xs text-[var(--text-secondary)]">Ticket tiers, the roster, broadcasts and the customizer all work against the active event.</p>
            <a href="{{ route('admin.events') }}" wire:navigate class="mt-5 inline-flex items-center gap-1.5 rounded-xl bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-[var(--color-primary-fg)] shadow-xs press-scale">
                + Create Event
            </a>
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ([
                ['label' => 'Passes issued', 'value' => number_format($stats['sold'])],
                ['label' => 'Capacity', 'value' => number_format($stats['capacity'])],
                ['label' => 'Checked in', 'value' => number_format($stats['checked_in']) . ' · ' . $turnout . '%'],
                ['label' => 'Paid revenue', 'value' => 'GHS ' . number_format($stats['revenue'], 2)],
            ] as $stat)
                <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-2xs">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">{{ $stat['label'] }}</span>
                    <div class="mt-1 font-mono text-lg font-bold tabular-nums">{{ $stat['value'] }}</div>
                </div>
            @endforeach
        </div>

        @if ($zoneRows !== [])
            @php
                $totals = [
                    'sold' => array_sum(array_column($zoneRows, 'sold')),
                    'checked_in' => array_sum(array_column($zoneRows, 'checked_in')),
                    'revenue' => array_sum(array_column($zoneRows, 'revenue')),
                ];
            @endphp
            <div class="overflow-x-auto rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-2xs" data-zone-breakdown>
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-[var(--border-hairline)] text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                        <tr>
                            <th class="px-4 py-3 font-semibold">{{ $zl }}</th>
                            <th class="px-4 py-3 text-right font-semibold">Passes</th>
                            <th class="px-4 py-3 text-right font-semibold">Checked in</th>
                            <th class="px-4 py-3 text-right font-semibold">Paid revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--border-hairline)] font-mono tabular-nums">
                        @foreach ($zoneRows as $zoneRow)
                            <tr data-zone-row="{{ $zoneRow['name'] }}">
                                <td class="px-4 py-2.5 font-sans font-medium">{{ $zoneRow['name'] === 'No zone' ? 'No ' . strtolower($zl) : $zoneRow['name'] }}</td>
                                <td class="px-4 py-2.5 text-right">{{ number_format($zoneRow['sold']) }}</td>
                                <td class="px-4 py-2.5 text-right">{{ number_format($zoneRow['checked_in']) }}</td>
                                <td class="px-4 py-2.5 text-right">GHS {{ number_format($zoneRow['revenue'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-[var(--border-hairline)] font-mono font-bold tabular-nums">
                        <tr data-zone-total>
                            <td class="px-4 py-2.5 font-sans">Total</td>
                            <td class="px-4 py-2.5 text-right">{{ number_format($totals['sold']) }}</td>
                            <td class="px-4 py-2.5 text-right">{{ number_format($totals['checked_in']) }}</td>
                            <td class="px-4 py-2.5 text-right">GHS {{ number_format($totals['revenue'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($quickLinks as $link)
            <a href="{{ route($link['route']) }}" wire:navigate class="group flex flex-col gap-1.5 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-xs transition hover:border-[var(--border-focused)] sm:p-5">
                <span class="text-sm font-semibold">{{ $link['title'] }}</span>
                <span class="text-xs leading-relaxed text-[var(--text-secondary)]">{{ $link['body'] }}</span>
                <span class="mt-2 text-xs font-semibold transition group-hover:translate-x-0.5">Open →</span>
            </a>
        @endforeach
    </div>
</div>
