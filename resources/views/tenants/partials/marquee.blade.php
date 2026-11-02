@php
    $custom = is_array($event?->customization) ? $event->customization : [];
    $words = array_values(array_filter(
        is_array($custom['marquee'] ?? null) ? $custom['marquee'] : ['Connect', 'Grow', 'Lead', 'Purposeful Careers', 'Mentorship', 'Leadership'],
        static fn($word): bool => is_string($word) && $word !== '',
    ));
@endphp

<div class="overflow-hidden border-y border-[var(--border-hairline)] bg-[var(--bg-surface)] py-4" aria-hidden="true">
    <div class="dedicated-marquee flex w-max gap-8 whitespace-nowrap font-serif text-xl font-semibold text-[var(--text-secondary)] sm:text-2xl">
        @foreach ([1, 2] as $copy)
            @foreach ($words as $word)
                <span class="flex items-center gap-8">{{ $word }} <span class="text-[var(--dedicated-accent)]">·</span></span>
            @endforeach
        @endforeach
    </div>
</div>
