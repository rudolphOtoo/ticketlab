@php
    $tiers = ($event?->ticketTypes ?? collect())->filter(static fn($tier) => $tier->is_active)->sortBy('price')->values();
    $checkoutUrl = $event !== null ? route('public.event.show', ['org_slug' => $organization->slug, 'event_slug' => $event->slug]) : null;
@endphp

@if ($tiers->isNotEmpty())
    <section id="packages" class="mx-auto w-full max-w-6xl space-y-10 px-4 py-20 sm:px-6">
        @include('tenants.partials.section-heading', ['eyebrow' => 'Admission & Access', 'heading' => 'Conference Packages', 'lead' => 'Choose the pass that fits how you want to experience the gathering.'])

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($tiers as $tier)
                @php
                    $soldOut = $tier->available_quantity <= 0;
                    $featured = $loop->count > 1 && $loop->index === intdiv($loop->count, 2);
                    $perks = is_array($tier->perks) ? $tier->perks : [];
                @endphp
                <article @class([
                    'relative flex flex-col gap-5 rounded-2xl border bg-[var(--bg-surface)] p-6 shadow-2xs transition hover:-translate-y-0.5 hover:shadow-lg',
                    'border-[var(--dedicated-accent)] ring-1 ring-[var(--dedicated-accent)]' => $featured,
                    'border-[var(--border-hairline)]' => !$featured,
                ])>
                    @if ($featured)
                        <span class="absolute -top-3 left-6 rounded-full bg-[var(--dedicated-accent)] px-3 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white">Most popular</span>
                    @endif

                    <div class="space-y-2">
                        <h3 class="text-lg font-bold">{{ $tier->name }}</h3>
                        <div class="text-3xl font-black">
                            @if ((float) $tier->price <= 0)
                                <span class="text-emerald-600">Free</span>
                            @else
                                <span class="text-sm font-semibold text-[var(--text-tertiary)]">GHS</span> {{ number_format((float) $tier->price, 2) }}
                            @endif
                        </div>
                        <p class="text-xs text-[var(--text-secondary)]">{{ $tier->description ?? 'Full access to all conference sessions.' }}</p>
                    </div>

                    @if ($perks !== [])
                        <ul class="space-y-2 text-xs">
                            @foreach ($perks as $perk)
                                <li class="flex items-start gap-2">
                                    <svg class="mt-0.5 size-3.5 shrink-0 text-[var(--dedicated-accent)]" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                    <span>{{ $perk }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-auto space-y-2">
                        <p class="text-[11px] font-semibold {{ $soldOut ? 'text-rose-600' : ($tier->available_quantity <= 20 ? 'text-amber-600' : 'text-[var(--text-tertiary)]') }}">
                            {{ $soldOut ? 'Sold out' : ($tier->available_quantity <= 20 ? "Only {$tier->available_quantity} left" : number_format($tier->available_quantity) . ' available') }}
                        </p>
                        @if ($soldOut)
                            <span class="block w-full cursor-not-allowed rounded-xl bg-[var(--bg-subtle)] py-2.5 text-center text-xs font-bold text-[var(--text-tertiary)]">Sold Out</span>
                        @else
                            <a href="{{ $checkoutUrl }}" class="block w-full rounded-xl bg-[var(--text-primary)] py-2.5 text-center text-xs font-bold text-[var(--bg-canvas)] shadow-2xs transition hover:opacity-90 press-scale">Select Pass</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
