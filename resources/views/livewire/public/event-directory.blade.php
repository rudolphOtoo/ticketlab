<div class="min-h-screen bg-[var(--bg-canvas)] text-[var(--text-primary)] relative overflow-x-hidden flex flex-col justify-between selection:bg-[var(--color-primary)] selection:text-[var(--color-primary-fg)]">

    <!-- Ambient Glow Backdrop -->
    <div class="pointer-events-none absolute inset-0 -z-10 flex items-start justify-center overflow-hidden">
        <div class="size-[38rem] rounded-full bg-gradient-to-b from-amber-500/10 via-orange-500/5 to-transparent blur-3xl opacity-60 dark:opacity-25 -translate-y-1/2"></div>
    </div>

    <!-- Floating Navigation Bar -->
    <x-floating-nav :showBack="true" current="Events" />

    <main class="w-full max-w-6xl mx-auto px-4 sm:px-6 pt-24 sm:pt-28 pb-16 flex-1">
        
        <!-- Header & Editorial Kicker -->
        <div class="text-center max-w-2xl mx-auto mb-10">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-semibold uppercase tracking-wider border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-secondary)] shadow-2xs mb-4">
                <span class="size-1.5 rounded-full bg-amber-500"></span>
                <span>Gatherings & Experiences</span>
            </span>

            <h1 class="font-serif text-3xl sm:text-5xl font-bold tracking-tight text-[var(--text-primary)] leading-tight mb-3">
                Explore Upcoming Events
            </h1>

            <p class="text-sm sm:text-base text-[var(--text-secondary)] leading-relaxed">
                Discover worship summits, professional conferences, community workshops, and creative gatherings. Secure your passes in seconds.
            </p>
        </div>

        <!-- Search Bar & Category Filters -->
        <div class="max-w-2xl mx-auto mb-10 space-y-3.5">
            <div class="relative flex items-center">
                <span class="absolute left-3.5 text-[var(--text-tertiary)] pointer-events-none">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>

                <input
                    type="search"
                    wire:model.live.debounce.250ms="search"
                    placeholder="Search by event title, host, topic, or venue..."
                    class="w-full pl-10 pr-10 py-3 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-xs sm:text-sm text-[var(--text-primary)] placeholder:text-[var(--text-tertiary)] shadow-2xs focus:border-[var(--text-primary)] focus:outline-hidden transition"
                />

                @if ($search !== '')
                    <button
                        type="button"
                        wire:click="$set('search', '')"
                        class="absolute right-3.5 size-5 rounded-full bg-[var(--bg-subtle)] text-[var(--text-tertiary)] hover:text-[var(--text-primary)] flex items-center justify-center text-xs transition cursor-pointer press-scale"
                        title="Clear search"
                    >
                        &times;
                    </button>
                @endif
            </div>

            <!-- Filter Pills -->
            <div class="flex items-center justify-center gap-1.5 flex-wrap">
                <button
                    type="button"
                    wire:click="$set('filter', 'all')"
                    @class([
                        'px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer press-scale',
                        'bg-[var(--text-primary)] text-[var(--bg-canvas)] shadow-2xs' => $filter === 'all',
                        'bg-[var(--bg-surface)] text-[var(--text-secondary)] border border-[var(--border-hairline)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' => $filter !== 'all',
                    ])
                >
                    All Events
                </button>

                <button
                    type="button"
                    wire:click="$set('filter', 'upcoming')"
                    @class([
                        'px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer press-scale',
                        'bg-[var(--text-primary)] text-[var(--bg-canvas)] shadow-2xs' => $filter === 'upcoming',
                        'bg-[var(--bg-surface)] text-[var(--text-secondary)] border border-[var(--border-hairline)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' => $filter !== 'upcoming',
                    ])
                >
                    Upcoming Dates
                </button>

                <button
                    type="button"
                    wire:click="$set('filter', 'free')"
                    @class([
                        'px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer press-scale',
                        'bg-[var(--text-primary)] text-[var(--bg-canvas)] shadow-2xs' => $filter === 'free',
                        'bg-[var(--bg-surface)] text-[var(--text-secondary)] border border-[var(--border-hairline)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' => $filter !== 'free',
                    ])
                >
                    Free Passes
                </button>

                <button
                    type="button"
                    wire:click="$set('filter', 'paid')"
                    @class([
                        'px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer press-scale',
                        'bg-[var(--text-primary)] text-[var(--bg-canvas)] shadow-2xs' => $filter === 'paid',
                        'bg-[var(--bg-surface)] text-[var(--text-secondary)] border border-[var(--border-hairline)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' => $filter !== 'paid',
                    ])
                >
                    Paid Tickets
                </button>
            </div>
        </div>

        <!-- Event Cards Grid -->
        @if ($events->isEmpty())
            <div class="max-w-md mx-auto text-center rounded-3xl border border-dashed border-[var(--border-hairline)] bg-[var(--bg-surface)] p-10 shadow-2xs">
                <div class="size-12 rounded-2xl bg-[var(--bg-subtle)] text-[var(--text-tertiary)] flex items-center justify-center mx-auto mb-4">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-[var(--text-primary)]">No events match your search</h3>
                <p class="text-xs text-[var(--text-secondary)] mt-1 mb-5">
                    Try adjusting your search terms or view all published events.
                </p>
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold shadow-2xs press-scale cursor-pointer"
                >
                    Reset All Filters
                </button>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($events as $event)
                    @php
                        $custom = is_array($event->customization) ? $event->customization : [];
                        $headline = $custom['headline'] ?? $event->title;
                        $accentHex = $custom['accent_hex'] ?? '#c5a028';

                        $minPrice = $event->ticketTypes->min('price');
                        $maxPrice = $event->ticketTypes->max('price');
                    @endphp

                    <article class="flex flex-col justify-between rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] overflow-hidden shadow-2xs subtle-hover-glow transition group">
                        
                        <!-- Top Banner / Imagery -->
                        <div class="relative h-44 w-full bg-[var(--bg-subtle)] overflow-hidden">
                            @if ($event->banner_path)
                                <img
                                    src="{{ $event->banner_path }}"
                                    alt="{{ $event->title }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300 ease-out"
                                />
                            @else
                                <div class="w-full h-full bg-gradient-to-br from-amber-500/15 via-orange-500/10 to-stone-500/20 flex items-center justify-center">
                                    <div class="text-center p-4">
                                        <x-app-logo-icon class="size-8 mx-auto text-[var(--text-tertiary)] opacity-60 mb-1" />
                                        <span class="text-[11px] font-mono uppercase tracking-wider text-[var(--text-tertiary)]">TicketLab Gathering</span>
                                    </div>
                                </div>
                            @endif

                            <div class="absolute inset-0 bg-gradient-to-t from-[var(--bg-surface)] via-transparent to-transparent opacity-80"></div>

                            <!-- Org Pill Badge -->
                            <div class="absolute top-3 left-3">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-[var(--bg-surface)]/90 backdrop-blur-md text-[var(--text-primary)] border border-[var(--border-hairline)] shadow-2xs">
                                    {{ $event->organization->name ?? 'Community' }}
                                </span>
                            </div>

                            <!-- Price Pill -->
                            <div class="absolute top-3 right-3">
                                @if ($event->ticketTypes->isEmpty())
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-zinc-500/20 text-zinc-700 dark:text-zinc-300 backdrop-blur-md">
                                        Passes TBA
                                    </span>
                                @elseif ($minPrice == 0 && $maxPrice == 0)
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 backdrop-blur-md">
                                        Free Entry
                                    </span>
                                @elseif ($minPrice == $maxPrice)
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-800 dark:text-amber-200 backdrop-blur-md">
                                        GHS {{ number_format((float) $minPrice, 2) }}
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-800 dark:text-amber-200 backdrop-blur-md">
                                        From GHS {{ number_format((float) $minPrice, 2) }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Content Body -->
                        <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                            <div class="space-y-2">
                                <h3 class="text-base font-bold text-[var(--text-primary)] group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors line-clamp-2 leading-snug">
                                    {{ $event->title }}
                                </h3>

                                @if ($headline && $headline !== $event->title)
                                    <p class="text-xs text-[var(--text-secondary)] line-clamp-2 leading-relaxed">
                                        {{ $headline }}
                                    </p>
                                @endif

                                <!-- Date & Location Meta -->
                                <div class="space-y-1.5 pt-2 text-xs text-[var(--text-secondary)]">
                                    <div class="flex items-center gap-2">
                                        <svg class="size-4 shrink-0 text-[var(--text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span>
                                            @if ($event->start_at)
                                                {{ $event->start_at->format('M j, Y') }}
                                                @if ($event->start_at->format('g:i A') !== '12:00 AM')
                                                    · {{ $event->start_at->format('g:i A') }}
                                                @endif
                                            @else
                                                Date to be announced
                                            @endif
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <svg class="size-4 shrink-0 text-[var(--text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="truncate">{{ $event->venue ?: 'Venue to be confirmed' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Bottom CTA -->
                            <div class="pt-3 border-t border-[var(--border-hairline)] flex items-center justify-between">
                                <span class="text-[11px] text-[var(--text-tertiary)]">
                                    {{ $event->ticketTypes->count() }} {{ \Illuminate\Support\Str::plural('tier', $event->ticketTypes->count()) }}
                                </span>

                                <a
                                    href="{{ route('public.event.show', ['org_slug' => $event->organization->slug, 'event_slug' => $event->slug]) }}"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold shadow-2xs hover:opacity-95 transition press-scale"
                                >
                                    <span>Get Tickets</span>
                                    <span class="text-[10px]">→</span>
                                </a>
                            </div>
                        </div>

                    </article>
                @endforeach
            </div>
        @endif

    </main>

    <!-- Warm Minimal Footer -->
    <footer class="w-full border-t border-[var(--border-hairline)] bg-[var(--bg-surface)]/60 py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-[var(--text-secondary)]">
            <div class="flex items-center gap-2">
                <x-app-logo-icon class="size-4 text-[var(--text-primary)]" />
                <span>© {{ date('Y') }} {{ config('app.name', 'TicketLab') }}. Universal Event & Ticketing Platform.</span>
            </div>

            <div class="flex items-center gap-5">
                <a href="{{ url('/') }}" class="hover:text-[var(--text-primary)] transition">Home</a>
                <a href="{{ route('public.events.index') }}" class="font-semibold text-[var(--text-primary)]">Explore Events</a>
                <a href="{{ route('dashboard') }}" class="hover:text-[var(--text-primary)] transition">Host an Event</a>
            </div>
        </div>
    </footer>

</div>
