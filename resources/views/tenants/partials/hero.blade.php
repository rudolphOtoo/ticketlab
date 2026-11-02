@php
    $custom = is_array($event?->customization) ? $event->customization : [];
    $headline = $custom['headline'] ?? $event?->title ?? $organization->name;
    $tagline = $custom['tagline'] ?? $event?->description ?? 'An exclusive gathering hosted by ' . $organization->name;
    $showCountdown = (bool) ($custom['show_countdown'] ?? true);
    $bannerUrl = $event?->bannerUrl();
    $now = now();
    $phase = match (true) {
        $event === null => 'none',
        $event->end_at->isPast() => 'ended',
        $event->start_at->lte($now) => 'live',
        default => 'upcoming',
    };
    $checkoutUrl = $event !== null ? route('public.event.show', ['org_slug' => $organization->slug, 'event_slug' => $event->slug]) : null;
@endphp

<header class="relative flex min-h-[88vh] items-center overflow-hidden px-4 pb-16 pt-28 sm:px-6 lg:px-8">
    @if ($bannerUrl)
        <div class="pointer-events-none absolute inset-0 -z-20">
            <img src="{{ $bannerUrl }}" alt="" class="size-full object-cover opacity-25 dark:opacity-20" />
            <div class="absolute inset-0 bg-gradient-to-b from-[var(--bg-canvas)]/40 via-[var(--bg-canvas)]/80 to-[var(--bg-canvas)]"></div>
        </div>
    @endif
    <div class="pointer-events-none absolute inset-0 -z-10 flex items-start justify-center overflow-hidden">
        <div class="size-[40rem] -translate-y-1/3 rounded-full bg-[var(--dedicated-accent)] opacity-15 blur-3xl dark:opacity-10"></div>
    </div>

    <div class="mx-auto grid w-full max-w-6xl grid-cols-1 items-center gap-10 lg:grid-cols-12">
        <div class="space-y-6 text-center lg:col-span-7 lg:text-left">
            <div class="inline-flex items-center gap-2 rounded-full border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-1 text-xs font-semibold text-[var(--text-secondary)] shadow-2xs">
                <span class="size-1.5 animate-pulse rounded-full bg-[var(--dedicated-accent)]"></span>
                <span>{{ $organization->name }} Presents</span>
            </div>

            <h1 class="font-serif text-4xl font-bold leading-[1.08] tracking-tight sm:text-6xl lg:text-7xl">{{ $headline }}</h1>

            <p class="mx-auto max-w-2xl text-base leading-relaxed text-[var(--text-secondary)] sm:text-lg lg:mx-0">{{ $tagline }}</p>

            @if ($checkoutUrl)
                @php
                    $isMultiTenant = !App\Models\PlatformSetting::isDedicatedMode();
                    $highlightsUrl = $isMultiTenant ? route('public.tenant.page', ['org_slug' => $organization->slug, 'page' => 'highlights']) : route('dedicated.highlights');
                    $aboutUrl = $isMultiTenant ? route('public.tenant.page', ['org_slug' => $organization->slug, 'page' => 'about']) : route('dedicated.about');
                @endphp
                <div class="flex flex-col items-center justify-center gap-3 pt-2 sm:flex-row lg:justify-start">
                    <a href="{{ $phase === 'ended' ? $highlightsUrl : $checkoutUrl }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[var(--dedicated-accent)] px-7 py-3.5 text-sm font-bold text-white shadow-md transition hover:opacity-90 press-scale sm:w-auto">
                        <span>{{ match ($phase) { 'live' => 'Happening Now · Get Passes', 'ended' => 'Relive the Highlights', default => 'Reserve Your Seat' } }}</span>
                        <span aria-hidden="true">→</span>
                    </a>
                    <a href="{{ $aboutUrl }}" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-6 py-3.5 text-center text-sm font-semibold shadow-2xs transition hover:bg-[var(--bg-subtle)] press-scale sm:w-auto">
                        Explore the Gathering
                    </a>
                </div>
            @endif
        </div>

        @if ($event !== null)
            <div class="flex flex-col items-center gap-4 lg:col-span-5 lg:items-end">
                <div class="w-full max-w-sm space-y-5 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-xl">
                    <div class="flex items-center gap-3 border-b border-[var(--border-hairline)] pb-4">
                        <div class="flex size-14 shrink-0 flex-col items-center justify-center rounded-xl bg-[var(--dedicated-accent)] text-white">
                            <span class="text-[10px] font-bold uppercase">{{ $event->start_at->format('M') }}</span>
                            <span class="text-xl font-black leading-none">{{ $event->start_at->format('d') }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="font-bold">{{ $event->start_at->format('l, F j, Y') }}</div>
                            <div class="text-xs text-[var(--text-secondary)]">{{ $event->start_at->format('g:i A') }} – {{ $event->end_at->format('g:i A') }}</div>
                            @if ($event->venue)
                                <div class="mt-0.5 truncate text-xs text-[var(--text-secondary)]">{{ $event->venue }}</div>
                            @endif
                        </div>
                    </div>

                    @if ($showCountdown && $phase === 'upcoming')
                        <div x-data="dedicatedCountdown(@js($event->start_at->toIso8601String()))" class="grid grid-cols-4 gap-2 text-center">
                            @foreach (['days' => 'Days', 'hours' => 'Hours', 'mins' => 'Mins', 'secs' => 'Secs'] as $unit => $label)
                                <div class="rounded-xl bg-[var(--bg-subtle)] py-2.5">
                                    <div class="font-mono text-2xl font-black tabular-nums" x-text="{{ $unit }}">00</div>
                                    <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">{{ $label }}</div>
                                </div>
                            @endforeach
                        </div>
                    @elseif ($phase === 'live')
                        <p class="rounded-xl bg-emerald-500/10 px-3 py-2 text-center text-xs font-semibold text-emerald-700 dark:text-emerald-300">● Live now — doors are open</p>
                    @endif
                </div>
            </div>
        @endif
    </div>
</header>
