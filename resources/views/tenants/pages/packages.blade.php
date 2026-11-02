<x-layouts::dedicated :organization="$organization" :event="$event" title="Conference Packages">
    @include('tenants.pages.page-header', ['heading' => 'Conference Packages'])
    @include('tenants.partials.packages')
    @if (($event?->ticketTypes ?? collect())->where('is_active', true)->isEmpty())
        <p class="pb-24 text-center text-sm text-[var(--text-secondary)]">Passes go on sale soon.</p>
    @endif
</x-layouts::dedicated>
