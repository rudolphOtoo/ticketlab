<section id="about" class="mx-auto w-full max-w-4xl space-y-5 px-4 py-20 text-center sm:px-6">
    @include('tenants.partials.section-heading', ['eyebrow' => 'About This Gathering', 'heading' => 'A Purposefully Curated Experience'])
    <p class="whitespace-pre-line text-sm leading-relaxed text-[var(--text-secondary)] sm:text-base">
        {{ $event?->description ?? 'An exclusive gathering hosted by ' . $organization->name . '.' }}
    </p>
    @if ($event !== null)
        <dl class="mx-auto grid max-w-2xl grid-cols-1 gap-3 pt-4 text-left sm:grid-cols-3">
            <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4">
                <dt class="text-[10px] font-bold uppercase tracking-wider text-[var(--text-tertiary)]">When</dt>
                <dd class="mt-1 text-sm font-semibold">{{ $event->start_at->format('M j, Y') }}</dd>
            </div>
            <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4">
                <dt class="text-[10px] font-bold uppercase tracking-wider text-[var(--text-tertiary)]">Where</dt>
                <dd class="mt-1 text-sm font-semibold">{{ $event->venue ?? 'Venue TBA' }}</dd>
            </div>
            <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4">
                <dt class="text-[10px] font-bold uppercase tracking-wider text-[var(--text-tertiary)]">Hosted by</dt>
                <dd class="mt-1 text-sm font-semibold">{{ $organization->name }}</dd>
            </div>
        </dl>
    @endif
</section>
