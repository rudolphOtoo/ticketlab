@php
    // Every other published event stays reachable from the landing page, whichever one is featured.
    $otherEvents = ($events ?? collect())->reject(static fn ($listed) => $event !== null && $listed->is($event))->values();
@endphp

@if ($otherEvents->isNotEmpty())
    <section id="events" class="mx-auto w-full max-w-6xl space-y-8 px-4 py-16 sm:px-6">
        @include('tenants.partials.section-heading', ['eyebrow' => 'Programme', 'heading' => 'More Events', 'lead' => 'Every event ' . $organization->name . ' is hosting.'])

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($otherEvents as $listed)
                <a
                    href="{{ route('public.event.show', ['org_slug' => $organization->slug, 'event_slug' => $listed->slug]) }}"
                    class="group flex flex-col overflow-hidden rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-2xs transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="aspect-[16/8] bg-[var(--bg-subtle)]">
                        @if ($listed->bannerUrl())
                            <img src="{{ $listed->bannerUrl() }}" alt="{{ $listed->title }}" class="size-full object-cover" loading="lazy" />
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col gap-1 p-4">
                        <p class="font-mono text-[11px] font-bold uppercase tracking-wider text-[var(--dedicated-accent)]">
                            {{ $listed->start_at->format('D, M j Y') }}{{ $listed->end_at->isPast() ? ' · Ended' : '' }}
                        </p>
                        <h3 class="text-sm font-bold leading-snug">{{ $listed->title }}</h3>
                        @if ($listed->venue)
                            <p class="text-xs text-[var(--text-secondary)]">{{ $listed->venue }}</p>
                        @endif
                        <span class="mt-auto pt-2 text-xs font-semibold text-[var(--dedicated-accent)] group-hover:underline">View event →</span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif
