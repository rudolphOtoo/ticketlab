<x-layouts::dedicated :organization="$organization" :event="$event" title="Speakers">
    @include('tenants.pages.page-header', ['heading' => 'Speakers', 'lead' => 'Meet the voices shaping this year’s program. Tap a speaker to read their story.'])
    @include('tenants.partials.speakers')
    @if (($event?->speakers ?? collect())->isEmpty())
        <p class="pb-24 text-center text-sm text-[var(--text-secondary)]">Speakers will be announced soon.</p>
    @endif
</x-layouts::dedicated>
