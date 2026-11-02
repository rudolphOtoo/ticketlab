<x-layouts::dedicated :organization="$organization" :event="$event" title="FAQ">
    @include('tenants.pages.page-header', ['heading' => 'Questions & Answers'])
    @include('tenants.partials.faqs')
    @include('tenants.partials.contact-cta')
</x-layouts::dedicated>
