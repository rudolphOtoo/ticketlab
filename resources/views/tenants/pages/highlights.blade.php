@php
    $highlightStats = [
        ['value' => ($event?->speakers ?? collect())->count(), 'label' => 'Speakers'],
        ['value' => ($event?->schedules ?? collect())->count(), 'label' => 'Sessions'],
        ['value' => ($event?->schedules ?? collect())->pluck('day_label')->unique()->count(), 'label' => 'Days'],
        ['value' => ($event?->ticketTypes ?? collect())->count(), 'label' => 'Pass Types'],
    ];
@endphp

<x-layouts::dedicated :organization="$organization" :event="$event" title="Highlights">
    @include('tenants.pages.page-header', ['heading' => 'Highlights', 'lead' => 'The program at a glance: the people, the sessions and the moments that make this gathering.'])

    <section class="mx-auto grid w-full max-w-4xl grid-cols-2 gap-4 px-4 py-10 sm:grid-cols-4 sm:px-6">
        @foreach ($highlightStats as $stat)
            <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 text-center shadow-2xs">
                <div class="font-serif text-4xl font-bold text-[var(--dedicated-accent)]">{{ $stat['value'] }}</div>
                <div class="mt-1 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">{{ $stat['label'] }}</div>
            </div>
        @endforeach
    </section>

    @include('tenants.partials.marquee')
    @include('tenants.partials.schedule')
    @include('tenants.partials.speakers')
</x-layouts::dedicated>
