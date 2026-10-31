@php
    $custom = is_array($event?->customization) ? $event->customization : [];
@endphp

<x-layouts::dedicated :organization="$organization" :event="$event">
    @include('tenants.partials.hero')
    @include('tenants.partials.marquee')
    @include('tenants.partials.about')

    @if ((bool) ($custom['show_speakers'] ?? true))
        @include('tenants.partials.speakers')
    @endif

    @include('tenants.partials.packages')

    @if ((bool) ($custom['show_schedule'] ?? true))
        @include('tenants.partials.schedule')
    @endif

    @if ((bool) ($custom['show_faqs'] ?? true))
        @include('tenants.partials.faqs')
    @endif

    @include('tenants.partials.gallery')

    @include('tenants.partials.contact-cta')
</x-layouts::dedicated>
