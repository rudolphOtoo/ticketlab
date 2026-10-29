@php
    $turnout = $stats['sold'] > 0 ? (int) round(($stats['checked_in'] / $stats['sold']) * 100) : 0;
    $quickLinks = [
        ['route' => 'admin.events', 'title' => 'Event Lifecycle', 'body' => 'Create events, upload banners, publish and set visibility.'],
        ['route' => 'admin.tickets', 'title' => 'Tickets & Pricing', 'body' => 'Configure tiers, capacities, perks and sales windows.'],
        ['route' => 'admin.attendees', 'title' => 'Attendee Roster', 'body' => 'Search passes, admit manually and stream CSV exports.'],
        ['route' => 'admin.broadcasts', 'title' => 'Broadcast Hub', 'body' => 'Queue batched reminder emails to ticket holders.'],
        ['route' => 'admin.sessions', 'title' => 'Financial Ledger', 'body' => 'Reconcile cash and online takings and lock daily sessions.'],
        ['route' => 'admin.studio', 'title' => 'Customizer Studio', 'body' => 'Brand the landing page, speakers, schedule and FAQs.'],
    ];
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
                    {{ $event?->title ?? 'No event yet' }} · {{ $user?->subdivision ? 'Scoped to ' . $user->subdivision->name : 'All zones' }}
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
