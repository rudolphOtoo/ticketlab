<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[var(--bg-canvas)] text-[var(--text-primary)] antialiased selection:bg-[var(--color-primary)] selection:text-[var(--color-primary-fg)] relative overflow-x-hidden flex flex-col justify-between">
        
        <!-- Ambient Mesh Glow -->
        <div class="pointer-events-none absolute inset-0 -z-10 flex items-start justify-center overflow-hidden">
            <div class="size-[42rem] rounded-full bg-gradient-to-b from-amber-500/12 via-orange-500/6 to-transparent blur-3xl opacity-70 dark:opacity-30 -translate-y-1/3"></div>
        </div>

        <!-- Floating Island Nav Bar -->
        <x-floating-nav :showBack="false" />

        @php
            $featuredEvents = \App\Models\Event::with(['organization', 'ticketTypes'])
                ->where('status', 'published')
                ->where('is_public', true)
                ->latest('id')
                ->take(3)
                ->get();
            $spotlightEvent = $featuredEvents->first();
        @endphp

        <!-- Main Hero Section -->
        <section class="max-w-6xl mx-auto px-4 sm:px-6 pt-24 sm:pt-32 pb-16 w-full">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
                
                <!-- Left Narrative Column -->
                <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-secondary)] shadow-2xs">
                        <span class="size-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                        <span>Gather · Connect · Celebrate</span>
                    </div>

                    <h1 class="font-serif text-4xl sm:text-6xl lg:text-7xl font-bold tracking-tight text-[var(--text-primary)] leading-[1.05]">
                        Where memorable gatherings begin.
                    </h1>

                    <p class="text-base sm:text-lg text-[var(--text-secondary)] font-normal leading-relaxed max-w-xl mx-auto lg:mx-0">
                        From church conferences and leadership summits to creative festivals and workshops — TicketLab makes discovering events and securing passes effortless and welcoming.
                    </p>

                    <!-- Quick Jump Search & Buttons -->
                    <div class="pt-2 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-3.5">
                        <a
                            href="{{ route('public.events.index') }}"
                            class="w-full sm:w-auto px-6 py-3 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs sm:text-sm font-semibold hover:opacity-95 transition shadow-2xs press-scale text-center cursor-pointer inline-flex items-center justify-center gap-2"
                        >
                            <span>Explore Upcoming Events</span>
                            <span class="text-xs">→</span>
                        </a>

                        <a
                            href="{{ route('dashboard') }}"
                            class="w-full sm:w-auto px-6 py-3 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-primary)] text-xs sm:text-sm font-semibold hover:bg-[var(--bg-subtle)] transition shadow-2xs press-scale text-center cursor-pointer inline-flex items-center justify-center gap-2"
                        >
                            <svg class="size-4 text-[var(--text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Host Your Gathering</span>
                        </a>
                    </div>

                    <!-- Direct Search Pill -->
                    <form action="{{ route('public.events.index') }}" method="GET" class="pt-2 max-w-md mx-auto lg:mx-0">
                        <div class="relative flex items-center">
                            <span class="absolute left-3.5 text-[var(--text-tertiary)] pointer-events-none">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input
                                type="text"
                                name="q"
                                placeholder="Search by topic, speaker, or city..."
                                class="w-full pl-10 pr-24 py-2.5 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-xs text-[var(--text-primary)] placeholder:text-[var(--text-tertiary)] shadow-2xs focus:border-[var(--text-primary)] focus:outline-hidden transition"
                            />
                            <button
                                type="submit"
                                class="absolute right-1.5 px-3 py-1 rounded-lg bg-[var(--bg-subtle)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] text-xs font-semibold transition cursor-pointer press-scale"
                            >
                                Search
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Right Spotlight Card Column -->
                <div class="lg:col-span-5">
                    @if ($spotlightEvent)
                        @php
                            $spotlightCustom = is_array($spotlightEvent->customization) ? $spotlightEvent->customization : [];
                            $spotlightHeadline = $spotlightCustom['headline'] ?? $spotlightEvent->title;
                            $minPrice = $spotlightEvent->ticketTypes->min('price');
                        @endphp
                        <div class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 sm:p-7 shadow-sm relative overflow-hidden group">
                            
                            <!-- Cover Accent -->
                            @if ($spotlightEvent->bannerUrl())
                                <div class="h-40 -mx-6 sm:-mx-7 -mt-6 sm:-mt-7 mb-5 overflow-hidden relative bg-[var(--bg-subtle)]">
                                    <img
                                        src="{{ $spotlightEvent->bannerUrl() }}"
                                        alt="{{ $spotlightEvent->title }}"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                    />
                                    <div class="absolute inset-0 bg-gradient-to-t from-[var(--bg-surface)] to-transparent"></div>
                                </div>
                            @endif

                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-mono font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">
                                        Spotlight Event
                                    </span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                                        Open for Passes
                                    </span>
                                </div>

                                <div>
                                    <h2 class="font-serif text-2xl font-bold text-[var(--text-primary)] leading-snug">
                                        {{ $spotlightEvent->title }}
                                    </h2>
                                    @if ($spotlightHeadline && $spotlightHeadline !== $spotlightEvent->title)
                                        <p class="text-xs text-[var(--text-secondary)] mt-1 line-clamp-2 leading-relaxed">
                                            {{ $spotlightHeadline }}
                                        </p>
                                    @endif
                                </div>

                                <!-- Metadata -->
                                <div class="space-y-2 py-3 border-y border-[var(--border-hairline)] text-xs text-[var(--text-secondary)]">
                                    <div class="flex items-center gap-2">
                                        <svg class="size-4 shrink-0 text-[var(--text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span>
                                            {{ $spotlightEvent->start_at ? $spotlightEvent->start_at->format('F j, Y · g:i A') : 'Date to be announced' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <svg class="size-4 shrink-0 text-[var(--text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="truncate">{{ $spotlightEvent->venue ?: 'Venue details to follow' }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-1">
                                    <div>
                                        <span class="block text-[10px] uppercase font-bold text-[var(--text-tertiary)]">Admission</span>
                                        <span class="text-sm font-bold text-[var(--text-primary)]">
                                            @if ($spotlightEvent->ticketTypes->isEmpty())
                                                TBA
                                            @elseif ($minPrice == 0)
                                                Free Pass
                                            @else
                                                GHS {{ number_format((float) $minPrice, 2) }}
                                            @endif
                                        </span>
                                    </div>

                                    <a
                                        href="{{ route('public.event.show', ['org_slug' => $spotlightEvent->organization->slug, 'event_slug' => $spotlightEvent->slug]) }}"
                                        class="px-4 py-2 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold hover:opacity-95 transition shadow-2xs press-scale inline-flex items-center gap-1.5"
                                    >
                                        <span>Get Passes</span>
                                        <span>→</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="rounded-3xl border border-dashed border-[var(--border-hairline)] bg-[var(--bg-surface)] p-8 text-center space-y-3">
                            <div class="size-12 rounded-2xl bg-[var(--bg-subtle)] text-[var(--text-tertiary)] flex items-center justify-center mx-auto">
                                <x-app-logo-icon class="size-6 text-[var(--text-primary)]" />
                            </div>
                            <h3 class="text-sm font-bold text-[var(--text-primary)]">Ready for Your Next Event?</h3>
                            <p class="text-xs text-[var(--text-secondary)] leading-relaxed">
                                Launch your event portal in minutes. Set ticket categories, design schedules, and receive Mobile Money & card payments.
                            </p>
                            <a
                                href="{{ route('dashboard') }}"
                                class="inline-flex items-center gap-1 px-4 py-2 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold shadow-2xs press-scale"
                            >
                                <span>Create an Event</span>
                                <span>→</span>
                            </a>
                        </div>
                    @endif
                </div>

            </div>
        </section>

        <!-- Highlights & Values Banner -->
        <section class="max-w-6xl mx-auto px-4 sm:px-6 py-8 w-full border-t border-[var(--border-hairline)]">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center md:text-left">
                <div class="space-y-1">
                    <span class="text-xl">💳</span>
                    <h3 class="text-xs font-bold text-[var(--text-primary)]">MoMo & Card Friendly</h3>
                    <p class="text-[11px] text-[var(--text-secondary)]">Instant, secure settlement with Ghana Mobile Money & debit cards.</p>
                </div>
                <div class="space-y-1">
                    <span class="text-xl">🎟</span>
                    <h3 class="text-xs font-bold text-[var(--text-primary)]">Digital Pass on Mobile</h3>
                    <p class="text-[11px] text-[var(--text-secondary)]">Instant QR confirmation delivered directly to your screen & email.</p>
                </div>
                <div class="space-y-1">
                    <span class="text-xl">⚡</span>
                    <h3 class="text-xs font-bold text-[var(--text-primary)]">Sub-Second Check-in</h3>
                    <p class="text-[11px] text-[var(--text-secondary)]">Swift camera scanners keep attendee lines moving smoothly at the gate.</p>
                </div>
                <div class="space-y-1">
                    <span class="text-xl">🤝</span>
                    <h3 class="text-xs font-bold text-[var(--text-primary)]">Community First</h3>
                    <p class="text-[11px] text-[var(--text-secondary)]">Your attendee data belongs to you. No unwanted spam or vendor lock-in.</p>
                </div>
            </div>
        </section>

        <!-- Featured Gatherings Showcase -->
        @if ($featuredEvents->isNotEmpty())
            <section class="max-w-6xl mx-auto px-4 sm:px-6 py-12 w-full">
                <div class="flex items-center justify-between mb-8 pb-3 border-b border-[var(--border-hairline)]">
                    <div>
                        <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Discover</span>
                        <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-[var(--text-primary)]">Featured Gatherings</h2>
                    </div>

                    <a
                        href="{{ route('public.events.index') }}"
                        class="text-xs font-semibold text-[var(--text-primary)] hover:underline inline-flex items-center gap-1 press-scale"
                    >
                        <span>View All Events</span>
                        <span>→</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach ($featuredEvents as $item)
                        @php
                            $itemMinPrice = $item->ticketTypes->min('price');
                        @endphp
                        <article class="flex flex-col justify-between rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] overflow-hidden shadow-2xs subtle-hover-glow transition group">
                            <div class="relative h-36 bg-[var(--bg-subtle)] overflow-hidden">
                                @if ($item->bannerUrl())
                                    <img
                                        src="{{ $item->bannerUrl() }}"
                                        alt="{{ $item->title }}"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                    />
                                @else
                                    <div class="w-full h-full bg-gradient-to-br from-amber-500/10 to-stone-500/20 flex items-center justify-center">
                                        <x-app-logo-icon class="size-6 text-[var(--text-tertiary)] opacity-60" />
                                    </div>
                                @endif
                                <div class="absolute top-2.5 left-2.5">
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-semibold bg-[var(--bg-surface)]/90 backdrop-blur-md text-[var(--text-primary)] border border-[var(--border-hairline)]">
                                        {{ $item->organization->name ?? 'Community' }}
                                    </span>
                                </div>
                            </div>

                            <div class="p-4 flex-1 flex flex-col justify-between space-y-3">
                                <div class="space-y-1">
                                    <h3 class="text-sm font-bold text-[var(--text-primary)] line-clamp-1 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">
                                        {{ $item->title }}
                                    </h3>
                                    <p class="text-xs text-[var(--text-secondary)]">
                                        {{ $item->start_at ? $item->start_at->format('M j, Y') : 'Date TBA' }}
                                        @if ($item->venue)
                                            · {{ $item->venue }}
                                        @endif
                                    </p>
                                </div>

                                <div class="pt-2.5 border-t border-[var(--border-hairline)] flex items-center justify-between">
                                    <span class="text-[11px] font-bold text-[var(--text-primary)]">
                                        @if ($item->ticketTypes->isEmpty())
                                            TBA
                                        @elseif ($itemMinPrice == 0)
                                            Free
                                        @else
                                            From GHS {{ number_format((float) $itemMinPrice, 2) }}
                                        @endif
                                    </span>

                                    <a
                                        href="{{ route('public.event.show', ['org_slug' => $item->organization->slug, 'event_slug' => $item->slug]) }}"
                                        class="text-xs font-semibold text-[var(--color-primary)] hover:underline inline-flex items-center gap-1 press-scale"
                                    >
                                        <span>Tickets</span>
                                        <span>→</span>
                                    </a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- Explore Channels & Links Grid -->
        <section class="max-w-6xl mx-auto px-4 sm:px-6 py-12 w-full">
            <div class="text-center max-w-xl mx-auto mb-8">
                <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Navigation</span>
                <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-[var(--text-primary)] mt-1">
                    Everything you need in one place
                </h2>
                <p class="text-xs text-[var(--text-secondary)] mt-1">
                    Quick shortcuts for attendees, hosts, and operations crews.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <a
                    href="{{ route('public.events.index') }}"
                    class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-2xs subtle-hover-glow transition flex flex-col justify-between group cursor-pointer"
                >
                    <div class="space-y-1.5">
                        <span class="text-xs font-mono font-bold text-amber-600 dark:text-amber-400">01</span>
                        <h3 class="text-sm font-bold text-[var(--text-primary)] group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">
                            Explore Gatherings
                        </h3>
                        <p class="text-[11px] text-[var(--text-secondary)] leading-relaxed">
                            Search and filter through upcoming conferences, festivals, and summits.
                        </p>
                    </div>
                    <span class="mt-3 text-xs font-semibold text-[var(--color-primary)] group-hover:translate-x-1 transition-transform inline-flex items-center gap-1">
                        <span>Browse directory</span>
                        <span>→</span>
                    </span>
                </a>

                <a
                    href="{{ route('dashboard') }}"
                    class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-2xs subtle-hover-glow transition flex flex-col justify-between group cursor-pointer"
                >
                    <div class="space-y-1.5">
                        <span class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400">02</span>
                        <h3 class="text-sm font-bold text-[var(--text-primary)] group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                            Host an Event
                        </h3>
                        <p class="text-[11px] text-[var(--text-secondary)] leading-relaxed">
                            Create your custom landing page, ticket categories, and dynamic questionnaires.
                        </p>
                    </div>
                    <span class="mt-3 text-xs font-semibold text-[var(--color-primary)] group-hover:translate-x-1 transition-transform inline-flex items-center gap-1">
                        <span>Launch Studio</span>
                        <span>→</span>
                    </span>
                </a>

                <a
                    href="{{ url('/ops/scanner') }}"
                    class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-2xs subtle-hover-glow transition flex flex-col justify-between group cursor-pointer"
                >
                    <div class="space-y-1.5">
                        <span class="text-xs font-mono font-bold text-blue-600 dark:text-blue-400">03</span>
                        <h3 class="text-sm font-bold text-[var(--text-primary)] group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                            Gate Scanner
                        </h3>
                        <p class="text-[11px] text-[var(--text-secondary)] leading-relaxed">
                            Sub-second mobile camera check-in with duplicate ticket alerts and audio chimes.
                        </p>
                    </div>
                    <span class="mt-3 text-xs font-semibold text-[var(--color-primary)] group-hover:translate-x-1 transition-transform inline-flex items-center gap-1">
                        <span>Open Scanner</span>
                        <span>→</span>
                    </span>
                </a>

                <a
                    href="{{ url('/ops/pos') }}"
                    class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-2xs subtle-hover-glow transition flex flex-col justify-between group cursor-pointer"
                >
                    <div class="space-y-1.5">
                        <span class="text-xs font-mono font-bold text-purple-600 dark:text-purple-400">04</span>
                        <h3 class="text-sm font-bold text-[var(--text-primary)] group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors">
                            On-Site Box Office
                        </h3>
                        <p class="text-[11px] text-[var(--text-secondary)] leading-relaxed">
                            Walk-in cash and MoMo registration terminal with instant change calculator.
                        </p>
                    </div>
                    <span class="mt-3 text-xs font-semibold text-[var(--color-primary)] group-hover:translate-x-1 transition-transform inline-flex items-center gap-1">
                        <span>Open Box Office</span>
                        <span>→</span>
                    </span>
                </a>

            </div>
        </section>

        <!-- Footer -->
        <footer class="w-full border-t border-[var(--border-hairline)] bg-[var(--bg-surface)]/60 mt-12 py-8">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 text-xs text-[var(--text-secondary)]">
                    <x-app-logo-icon class="size-4 text-[var(--text-primary)]" />
                    <span>© {{ date('Y') }} {{ config('app.name', 'TicketLab') }}. Universal Event & Ticketing Platform.</span>
                </div>

                <div class="flex items-center gap-5 text-xs text-[var(--text-secondary)]">
                    <a href="{{ route('public.events.index') }}" class="hover:text-[var(--text-primary)] transition">Explore Events</a>
                    <a href="{{ route('dashboard') }}" class="hover:text-[var(--text-primary)] transition">Organizer Sign In</a>
                    <a href="{{ url('/settings/profile') }}" class="hover:text-[var(--text-primary)] transition">Account</a>
                </div>
            </div>
        </footer>

    </body>
</html>
