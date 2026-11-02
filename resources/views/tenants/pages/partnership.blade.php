@php
    $bespoke = $organization->bespokeSettings();
    $partnershipConfig = (array) ($bespoke['partnership'] ?? []);
    $partnerHeading = (string) ($partnershipConfig['heading'] ?? 'Partner With Us');
    $partnerLead = (string) ($partnershipConfig['lead'] ?? 'Put your brand in front of a purposeful, engaged audience and help shape the experience.');
    $configuredTiers = array_values(array_filter((array) ($partnershipConfig['tiers'] ?? [])));
    $partnerTiers = !empty($configuredTiers)
        ? $configuredTiers
        : [
            ['name' => 'Community Partner', 'body' => 'Logo on the event site, social shout-outs and two complimentary passes.'],
            ['name' => 'Session Sponsor', 'body' => 'Branded session or workshop, stage mention and a reserved partner table.'],
            ['name' => 'Headline Partner', 'body' => 'Main-stage presence, keynote introduction, exhibition space and VIP passes.'],
        ];
@endphp

<x-layouts::dedicated :organization="$organization" :event="$event" title="{{ $partnerHeading }}">
    @include('tenants.pages.page-header', ['heading' => $partnerHeading, 'lead' => $partnerLead])

    <section class="mx-auto grid w-full max-w-5xl grid-cols-1 gap-5 px-4 py-10 sm:px-6 md:grid-cols-3">
        @foreach ($partnerTiers as $tier)
            <article class="space-y-2 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-2xs">
                <h2 class="text-base font-bold">{{ $tier['name'] }}</h2>
                <p class="text-sm leading-relaxed text-[var(--text-secondary)]">{{ $tier['body'] }}</p>
            </article>
        @endforeach
    </section>

    @include('tenants.partials.contact-cta')
</x-layouts::dedicated>
