<x-layouts::dedicated :organization="$organization" :event="$event" title="About Us">
    @include('tenants.pages.page-header', ['heading' => 'About ' . ($event?->title ?? $organization->name)])
    @include('tenants.partials.about')
    @include('tenants.partials.marquee')
    @include('tenants.partials.contact-cta')
</x-layouts::dedicated>
