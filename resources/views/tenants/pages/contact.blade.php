<x-layouts::dedicated :organization="$organization" :event="$event" title="Contact">
    @include('tenants.pages.page-header', ['heading' => 'Get in Touch', 'lead' => 'Questions about passes, partnerships or the program? Send us a message and the team will reply by email.'])

    <section class="mx-auto w-full max-w-xl px-4 pb-24 pt-6 sm:px-6">
        <livewire:public.contact-form :organization-id="$organization->id" />
    </section>
</x-layouts::dedicated>
