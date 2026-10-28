<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <title>{{ $event?->title ?? $organization->name }} | {{ $organization->name }}</title>
        <meta name="description" content="{{ $event?->description ?? 'Welcome to ' . $organization->name }}" />
    </head>
    <body class="min-h-screen bg-[var(--bg-canvas)] text-[var(--text-primary)] antialiased selection:bg-[var(--color-primary)] selection:text-[var(--color-primary-fg)] relative overflow-x-hidden flex flex-col justify-between">

        @php
            $custom = is_array($event?->customization) ? $event->customization : [];
            $accentHex = $custom['accent_hex'] ?? '#18181b';
            $headline = $custom['headline'] ?? $event?->title ?? $organization->name;
            $tagline = $custom['tagline'] ?? $event?->description ?? 'An exclusive bespoke gathering hosted by ' . $organization->name;
            $showSpeakers = (bool) ($custom['show_speakers'] ?? true);
            $showSchedule = (bool) ($custom['show_schedule'] ?? true);
            $showFaqs = (bool) ($custom['show_faqs'] ?? true);
            $showCountdown = (bool) ($custom['show_countdown'] ?? true);
            $speakers = $event?->speakers ?? collect();
            $schedules = $event?->schedules ?? collect();
            $faqs = $event?->faqs ?? collect();
            $ticketTypes = $event?->ticketTypes ?? collect();
        @endphp

        <!-- Dedicated Client Floating Nav -->
        <nav
            aria-label="Dedicated organization navigation"
            class="fixed top-4 inset-x-0 mx-auto max-w-fit z-50 rounded-full border border-[var(--border-hairline)] bg-[var(--bg-surface)]/90 backdrop-blur-xl shadow-lg p-1.5 flex items-center gap-2 pointer-events-auto"
        >
            <a
                href="{{ url('/') }}"
                class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-[var(--bg-subtle)] text-xs font-bold text-[var(--text-primary)] hover:opacity-90 transition press-scale"
            >
                <span class="size-2 rounded-full bg-amber-500"></span>
                <span>{{ $organization->name }}</span>
            </a>

            <div class="hidden sm:flex items-center gap-1 text-xs font-semibold text-[var(--text-secondary)]">
                <a href="#about" class="px-3 py-1 rounded-full hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition">About</a>
                @if($showSpeakers && $speakers->isNotEmpty())
                    <a href="#speakers" class="px-3 py-1 rounded-full hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition">Speakers</a>
                @endif
                @if($showSchedule && $schedules->isNotEmpty())
                    <a href="#schedule" class="px-3 py-1 rounded-full hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition">Schedule</a>
                @endif
                @if($showFaqs && $faqs->isNotEmpty())
                    <a href="#faqs" class="px-3 py-1 rounded-full hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition">FAQs</a>
                @endif
            </div>

            @if($event !== null)
                <a
                    href="{{ route('public.event.show', ['org_slug' => $organization->slug, 'event_slug' => $event->slug]) }}"
                    class="px-4 py-1.5 rounded-full bg-[var(--text-primary)] text-[var(--bg-canvas)] text-xs font-bold press-scale shadow-xs hover:opacity-90 transition"
                >
                    Get Passes
                </a>
            @endif

            <button
                type="button"
                onclick="window.toggleStudioTheme ? window.toggleStudioTheme(event, null, this) : (window.toggleTheme ? window.toggleTheme(event, null, this) : document.documentElement.classList.toggle('dark'))"
                class="size-8 rounded-full border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:border-[var(--text-primary)] flex items-center justify-center transition-colors press-scale cursor-pointer"
                aria-label="Toggle dark/light theme"
                title="Toggle theme (Circular Ripple)"
            >
                <svg class="size-4 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
                <svg class="size-4 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </button>
        </nav>

        <!-- Bespoke Hero Section -->
        <header class="relative min-h-[85vh] flex items-center px-4 sm:px-6 lg:px-8 pt-28 pb-16 overflow-hidden">
            <!-- Ambient Glow -->
            <div class="pointer-events-none absolute inset-0 -z-10 flex items-start justify-center overflow-hidden">
                <div class="size-[40rem] rounded-full bg-gradient-to-b from-amber-500/15 via-orange-500/5 to-transparent blur-3xl opacity-80 dark:opacity-30 -translate-y-1/3"></div>
            </div>

            <div class="max-w-6xl mx-auto w-full grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
                <div class="lg:col-span-8 space-y-6 text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-secondary)] shadow-2xs">
                        <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>{{ $organization->name }} Presents</span>
                    </div>

                    <h1 class="font-serif text-4xl sm:text-6xl lg:text-7xl font-bold tracking-tight text-[var(--text-primary)] leading-[1.08]">
                        {{ $headline }}
                    </h1>

                    <p class="text-base sm:text-lg text-[var(--text-secondary)] font-normal leading-relaxed max-w-2xl mx-auto lg:mx-0">
                        {{ $tagline }}
                    </p>

                    @if($event !== null)
                        <div class="pt-2 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                            <a
                                href="{{ route('public.event.show', ['org_slug' => $organization->slug, 'event_slug' => $event->slug]) }}"
                                class="w-full sm:w-auto px-7 py-3.5 rounded-xl bg-[var(--text-primary)] text-[var(--bg-canvas)] text-sm font-bold hover:opacity-90 transition shadow-md press-scale text-center cursor-pointer inline-flex items-center justify-center gap-2"
                            >
                                <span>Reserve Your Seat</span>
                                <span>→</span>
                            </a>
                            <a
                                href="#about"
                                class="w-full sm:w-auto px-6 py-3.5 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-primary)] text-sm font-semibold hover:bg-[var(--bg-subtle)] transition shadow-2xs press-scale text-center"
                            >
                                Explore Gathering
                            </a>
                        </div>
                    @endif
                </div>

                <!-- Event Details Badge Block -->
                @if($event !== null)
                    <div class="lg:col-span-4 flex flex-col items-center lg:items-end">
                        <div class="w-full max-w-sm rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-xl space-y-5">
                            <div class="border-b border-[var(--border-hairline)] pb-4">
                                <span class="text-[10px] uppercase font-mono font-bold tracking-wider text-[var(--text-tertiary)]">When & Where</span>
                                <div class="text-xl font-bold text-[var(--text-primary)] mt-1">
                                    {{ $event->start_at?->format('F j, Y') ?? 'Dates TBA' }}
                                </div>
                                <div class="text-xs text-[var(--text-secondary)] mt-0.5">
                                    {{ $event->start_at?->format('g:i A') ?? '' }}
                                    @if($event->end_at) - {{ $event->end_at->format('g:i A') }} @endif
                                </div>
                            </div>

                            @if($event->venue)
                                <div class="border-b border-[var(--border-hairline)] pb-4">
                                    <span class="text-[10px] uppercase font-mono font-bold tracking-wider text-[var(--text-tertiary)]">Location</span>
                                    <div class="text-sm font-semibold text-[var(--text-primary)] mt-1 flex items-start gap-1.5">
                                        <span>📍</span>
                                        <span>{{ $event->venue }}</span>
                                    </div>
                                </div>
                            @endif

                            <div class="flex items-center justify-between pt-1">
                                <span class="text-xs text-[var(--text-secondary)]">Hosted by</span>
                                <span class="text-xs font-bold text-[var(--text-primary)]">{{ $organization->name }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </header>

        <!-- About Section -->
        <section id="about" class="py-16 max-w-4xl mx-auto px-4 sm:px-6 w-full text-center space-y-4">
            <span class="text-xs font-mono font-bold uppercase tracking-widest text-[var(--text-tertiary)]">About This Gathering</span>
            <h2 class="text-2xl sm:text-3xl font-bold text-[var(--text-primary)]">
                A Purposefully Curated Experience
            </h2>
            <p class="text-sm sm:text-base text-[var(--text-secondary)] leading-relaxed">
                {{ $event?->description ?? $tagline }}
            </p>
        </section>

        <!-- Speakers Section -->
        @if($showSpeakers && $speakers->isNotEmpty())
            <section id="speakers" class="py-16 max-w-6xl mx-auto px-4 sm:px-6 w-full space-y-8">
                <div class="text-center space-y-2">
                    <span class="text-xs font-mono font-bold uppercase tracking-widest text-[var(--text-tertiary)]">Featured Contributors</span>
                    <h2 class="text-2xl sm:text-3xl font-bold text-[var(--text-primary)]">Distinguished Speakers</h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($speakers as $speaker)
                        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 shadow-2xs space-y-3">
                            <div class="size-16 rounded-full bg-[var(--bg-subtle)] flex items-center justify-center font-bold text-lg text-[var(--text-secondary)] overflow-hidden">
                                @if($speaker->photo_path)
                                    <img src="{{ $speaker->photo_path }}" alt="{{ $speaker->name }}" class="size-full object-cover" />
                                @else
                                    {{ substr($speaker->name, 0, 1) }}
                                @endif
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-[var(--text-primary)]">{{ $speaker->name }}</h3>
                                <p class="text-xs text-[var(--text-secondary)]">{{ $speaker->title }} · {{ $speaker->organization }}</p>
                            </div>
                            @if($speaker->topic)
                                <p class="text-xs font-medium text-amber-700 dark:text-amber-300">Topic: {{ $speaker->topic }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- Schedule Section -->
        @if($showSchedule && $schedules->isNotEmpty())
            <section id="schedule" class="py-16 max-w-4xl mx-auto px-4 sm:px-6 w-full space-y-8">
                <div class="text-center space-y-2">
                    <span class="text-xs font-mono font-bold uppercase tracking-widest text-[var(--text-tertiary)]">Program Outline</span>
                    <h2 class="text-2xl sm:text-3xl font-bold text-[var(--text-primary)]">Event Schedule</h2>
                </div>

                <div class="space-y-3">
                    @foreach($schedules as $item)
                        <div class="flex items-start gap-4 p-4 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-2xs">
                            <div class="min-w-24 font-mono text-xs font-bold text-[var(--text-tertiary)] pt-0.5">
                                {{ $item->starts_at?->format('H:i') ?? 'TBA' }}
                            </div>
                            <div class="flex-1">
                                <h3 class="text-sm font-bold text-[var(--text-primary)]">{{ $item->title }}</h3>
                                @if($item->speaker_name)
                                    <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">Speaker: {{ $item->speaker_name }}</p>
                                @endif
                                @if($item->location)
                                    <p class="text-[11px] text-[var(--text-tertiary)] mt-0.5">📍 {{ $item->location }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- Ticket Packages CTA Section -->
        @if($event !== null && $ticketTypes->isNotEmpty())
            <section id="tickets" class="py-16 max-w-5xl mx-auto px-4 sm:px-6 w-full space-y-8">
                <div class="text-center space-y-2">
                    <span class="text-xs font-mono font-bold uppercase tracking-widest text-[var(--text-tertiary)]">Admission & Access</span>
                    <h2 class="text-2xl sm:text-3xl font-bold text-[var(--text-primary)]">Available Passes</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($ticketTypes as $tier)
                        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-2xs flex flex-col justify-between space-y-4">
                            <div class="space-y-2">
                                <h3 class="text-lg font-bold text-[var(--text-primary)]">{{ $tier->name }}</h3>
                                <div class="text-2xl font-black text-[var(--text-primary)]">
                                    @if($tier->price <= 0)
                                        <span class="text-emerald-600">Free</span>
                                    @else
                                        <span>GHS {{ number_format($tier->price, 2) }}</span>
                                    @endif
                                </div>
                                <p class="text-xs text-[var(--text-secondary)]">{{ $tier->description ?? 'Standard access pass to all conference sessions.' }}</p>
                            </div>

                            <a
                                href="{{ route('public.event.show', ['org_slug' => $organization->slug, 'event_slug' => $event->slug]) }}"
                                class="w-full py-2.5 rounded-xl bg-[var(--text-primary)] text-[var(--bg-canvas)] text-xs font-bold text-center press-scale shadow-2xs hover:opacity-90 transition block"
                            >
                                Select Pass
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- FAQs Section -->
        @if($showFaqs && $faqs->isNotEmpty())
            <section id="faqs" class="py-16 max-w-3xl mx-auto px-4 sm:px-6 w-full space-y-8">
                <div class="text-center space-y-2">
                    <span class="text-xs font-mono font-bold uppercase tracking-widest text-[var(--text-tertiary)]">Clarifications</span>
                    <h2 class="text-2xl sm:text-3xl font-bold text-[var(--text-primary)]">Frequently Asked Questions</h2>
                </div>

                <div class="space-y-3">
                    @foreach($faqs as $faq)
                        <details class="group rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 cursor-pointer">
                            <summary class="text-xs sm:text-sm font-bold text-[var(--text-primary)] flex items-center justify-between list-none">
                                <span>{{ $faq->question }}</span>
                                <span class="text-xs text-[var(--text-tertiary)] group-open:rotate-180 transition-transform">▼</span>
                            </summary>
                            <p class="text-xs text-[var(--text-secondary)] mt-3 leading-relaxed border-t border-[var(--border-hairline)] pt-3">
                                {{ $faq->answer }}
                            </p>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- Dedicated Footer -->
        <footer class="border-t border-[var(--border-hairline)] bg-[var(--bg-surface)] py-8 px-4 text-center text-xs text-[var(--text-tertiary)]">
            <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    © {{ date('Y') }} {{ $organization->name }}. All rights reserved.
                </div>
                <div class="flex items-center gap-4">
                    <span>Direct Contact: {{ $organization->email ?? 'info@' . $organization->slug . '.org' }}</span>
                </div>
            </div>
        </footer>

    </body>
</html>
