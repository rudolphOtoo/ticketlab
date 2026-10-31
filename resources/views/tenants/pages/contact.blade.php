@php
    $bespoke = $organization->bespokeSettings();
    $contactConfig = (array) ($bespoke['contact'] ?? []);
    $contactHeading = (string) ($contactConfig['heading'] ?? 'Get in Touch');
    $contactLead = (string) ($contactConfig['lead'] ?? 'Questions about passes, partnerships or the program? Send us a message and our team will reply.');
    $contactEmail = $organization->contactEmail();
    $contactPhone = $contactConfig['phone'] ?? null;
    $contactAddress = $contactConfig['address'] ?? ($event?->venue ?? null);
@endphp

<x-layouts::dedicated :organization="$organization" :event="$event" title="{{ $contactHeading }}">
    @include('tenants.pages.page-header', ['heading' => $contactHeading, 'lead' => $contactLead])

    @if ($contactEmail || $contactPhone || $contactAddress)
        <section class="mx-auto grid w-full max-w-xl grid-cols-1 gap-3 px-4 pt-6 text-center sm:grid-cols-3 sm:px-6">
            @if ($contactEmail)
                <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--text-tertiary)]">Email</span>
                    <p class="mt-1 truncate text-xs font-semibold text-[var(--text-primary)]">{{ $contactEmail }}</p>
                </div>
            @endif
            @if ($contactPhone)
                <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--text-tertiary)]">Phone</span>
                    <p class="mt-1 truncate text-xs font-semibold text-[var(--text-primary)]">{{ $contactPhone }}</p>
                </div>
            @endif
            @if ($contactAddress)
                <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--text-tertiary)]">Venue</span>
                    <p class="mt-1 truncate text-xs font-semibold text-[var(--text-primary)]">{{ $contactAddress }}</p>
                </div>
            @endif
        </section>
    @endif

    <section class="mx-auto w-full max-w-xl px-4 pb-24 pt-6 sm:px-6">
        <livewire:public.contact-form :organization-id="$organization->id" />
    </section>
</x-layouts::dedicated>
