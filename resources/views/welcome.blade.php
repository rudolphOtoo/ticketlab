<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[var(--bg-canvas)] text-[var(--text-primary)] antialiased selection:bg-[var(--color-primary)] selection:text-[var(--color-primary-fg)] relative overflow-x-hidden flex flex-col justify-between">
        
        <!-- Ambient Backdrop Mesh -->
        <div class="pointer-events-none absolute inset-0 -z-10 flex items-start justify-center overflow-hidden">
            <div class="size-[42rem] rounded-full bg-gradient-to-b from-amber-500/10 via-orange-500/5 to-transparent blur-3xl opacity-60 dark:opacity-30 -translate-y-1/3"></div>
        </div>

        <!-- Floating Island Nav Bar -->
        <x-floating-nav :showBack="false" />

        <!-- Hero Section -->
        <section class="max-w-5xl mx-auto px-4 sm:px-6 pt-24 sm:pt-32 pb-16 text-center">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-secondary)] shadow-xs mb-6">
                <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Universal Event & Ticketing Architecture</span>
                <span class="text-[var(--text-tertiary)]">·</span>
                <span class="text-[var(--text-primary)] font-medium">Zero-Overselling Concurrency</span>
            </div>

            <h1 class="text-4xl sm:text-6xl md:text-7xl font-extrabold tracking-tight text-[var(--text-primary)] leading-[1.08] mb-6 max-w-4xl mx-auto">
                High-Speed Ticketing & Live Ground Operations.
            </h1>

            <p class="text-base sm:text-lg md:text-xl text-[var(--text-secondary)] font-normal leading-relaxed max-w-2xl mx-auto mb-10">
                Engineered for conferences, summits, and festivals across Africa. Atomic capacity reservation, instant Mobile Money settlement, sub-second camera check-in, and cash desk reconciliation.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3.5 max-w-md mx-auto">
                <a
                    href="{{ route('dashboard') }}"
                    class="w-full sm:w-auto px-6 py-3 rounded-field bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-sm font-semibold hover:opacity-95 transition shadow-xs press-scale text-center cursor-pointer inline-flex items-center justify-center gap-2"
                >
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Launch Operations Hub</span>
                </a>

                <a
                    href="#events"
                    class="w-full sm:w-auto px-6 py-3 rounded-field border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-primary)] text-sm font-semibold hover:bg-[var(--bg-subtle)] transition shadow-xs press-scale text-center cursor-pointer"
                >
                    Explore Public Events ↓
                </a>
            </div>
        </section>

        <!-- Live Event Spotlight Section -->
        <section id="events" class="max-w-6xl mx-auto px-4 sm:px-6 py-12 w-full">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-8 gap-3 border-b border-[var(--border-hairline)] pb-4">
                <div>
                    <span class="text-xs font-semibold tracking-wider uppercase text-[var(--text-secondary)]">Current Roster</span>
                    <h2 class="text-2xl font-bold tracking-tight text-[var(--text-primary)]">Active & Published Events</h2>
                </div>
                <span class="text-xs text-[var(--text-secondary)]">Powered by single-database tenant isolation</span>
            </div>

            @php
                $publishedEvents = \App\Models\Event::with(['organization', 'ticketTypes'])->where('status', 'published')->take(3)->get();
            @endphp

            @if($publishedEvents->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($publishedEvents as $event)
                        <div class="flex flex-col justify-between rounded-box border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 shadow-xs subtle-hover-glow transition group">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-medium text-[var(--text-secondary)]">
                                        {{ $event->organization->name ?? 'Organization' }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-semibold text-[11px]">
                                        Open for Registration
                                    </span>
                                </div>

                                <h3 class="text-lg font-bold text-[var(--text-primary)] group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">
                                    {{ $event->title }}
                                </h3>

                                <div class="space-y-1.5 text-xs text-[var(--text-secondary)]">
                                    <div class="flex items-center gap-1.5">
                                        <svg class="size-4 shrink-0 text-[var(--text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span>{{ $event->start_at->format('M d, Y') }} — {{ $event->end_at->format('M d, Y') }}</span>
                                    </div>
                                    @if($event->venue)
                                        <div class="flex items-center gap-1.5">
                                            <svg class="size-4 shrink-0 text-[var(--text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <span class="truncate">{{ $event->venue }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-6 pt-4 border-t border-[var(--border-hairline)] flex items-center justify-between">
                                <span class="text-xs text-[var(--text-secondary)]">
                                    {{ $event->ticketTypes->count() }} Tier(s) Available
                                </span>
                                <a
                                    href="{{ route('public.event.show', ['org_slug' => $event->organization->slug, 'event_slug' => $event->slug]) }}"
                                    class="text-xs font-semibold text-[var(--color-primary)] hover:underline inline-flex items-center gap-1 press-scale"
                                >
                                    <span>Get Tickets</span>
                                    <span>→</span>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-box border border-dashed border-[var(--border-hairline)] bg-[var(--bg-surface)] p-8 text-center">
                    <p class="text-sm text-[var(--text-secondary)] mb-2">No live events published yet.</p>
                    <a href="{{ route('dashboard') }}" class="text-xs font-semibold text-[var(--color-primary)] hover:underline">
                        Open Operations Hub to seed or create an event →
                    </a>
                </div>
            @endif
        </section>

        <!-- Core Architecture Feature Pillars -->
        <section id="features" class="max-w-6xl mx-auto px-4 sm:px-6 py-12 w-full">
            <div class="text-center max-w-xl mx-auto mb-12">
                <span class="text-xs font-semibold tracking-wider uppercase text-[var(--text-secondary)]">Robust Foundations</span>
                <h2 class="text-3xl font-bold tracking-tight text-[var(--text-primary)] mt-1">Engineered for Ground Realities</h2>
                <p class="text-sm text-[var(--text-secondary)] mt-2">Built specifically to prevent overselling, handle cash reconciliation, and survive network drops.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                
                <!-- Pillar 1 -->
                <div class="rounded-box border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 shadow-xs flex flex-col justify-between">
                    <div class="space-y-2.5">
                        <div class="size-9 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
                            🔒
                        </div>
                        <h3 class="text-sm font-semibold text-[var(--text-primary)]">Zero-Overselling Engine</h3>
                        <p class="text-xs text-[var(--text-secondary)] leading-relaxed">
                            Pessimistic concurrency locking (`lockForUpdate()`) and 15-minute lease reservations guarantee absolute inventory integrity.
                        </p>
                    </div>
                    <span class="mt-4 pt-3 border-t border-[var(--border-hairline)] text-[11px] font-mono text-[var(--text-tertiary)]">
                        Atomic DB Transactions
                    </span>
                </div>

                <!-- Pillar 2 -->
                <div class="rounded-box border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 shadow-xs flex flex-col justify-between">
                    <div class="space-y-2.5">
                        <div class="size-9 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                            📷
                        </div>
                        <h3 class="text-sm font-semibold text-[var(--text-primary)]">Mobile Door Scanner</h3>
                        <p class="text-xs text-[var(--text-secondary)] leading-relaxed">
                            Direct camera stream decoding with sub-second feedback, Web Audio harmonic chime, duplicate buzzer, and native haptic pulses.
                        </p>
                    </div>
                    <span class="mt-4 pt-3 border-t border-[var(--border-hairline)] text-[11px] font-mono text-[var(--text-tertiary)]">
                        Sub-250ms Gate Loop
                    </span>
                </div>

                <!-- Pillar 3 -->
                <div class="rounded-box border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 shadow-xs flex flex-col justify-between">
                    <div class="space-y-2.5">
                        <div class="size-9 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                            💵
                        </div>
                        <h3 class="text-sm font-semibold text-[var(--text-primary)]">On-Site Cash Desk</h3>
                        <p class="text-xs text-[var(--text-secondary)] leading-relaxed">
                            Walk-in cash sales terminal with Ghanaian banknote quick pills, instant change calculator, and printable SVG pass modals.
                        </p>
                    </div>
                    <span class="mt-4 pt-3 border-t border-[var(--border-hairline)] text-[11px] font-mono text-[var(--text-tertiary)]">
                        Cash & MoMo Split
                    </span>
                </div>

                <!-- Pillar 4 -->
                <div class="rounded-box border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 shadow-xs flex flex-col justify-between">
                    <div class="space-y-2.5">
                        <div class="size-9 rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold">
                            📦
                        </div>
                        <h3 class="text-sm font-semibold text-[var(--text-primary)]">Zero Data Lock-In</h3>
                        <p class="text-xs text-[var(--text-secondary)] leading-relaxed">
                            Streaming chunked CSV exports flattening dynamic custom questions, alongside full JSON tenant database backups.
                        </p>
                    </div>
                    <span class="mt-4 pt-3 border-t border-[var(--border-hairline)] text-[11px] font-mono text-[var(--text-tertiary)]">
                        Full Data Sovereignty
                    </span>
                </div>

            </div>
        </section>

        <!-- Footer -->
        <footer class="w-full border-t border-[var(--border-hairline)] bg-[var(--bg-surface)]/50 mt-16">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 text-xs text-[var(--text-secondary)]">
                    <x-app-logo-icon class="size-4 text-[var(--text-primary)]" />
                    <span>© {{ date('Y') }} {{ config('app.name', 'TicketLab') }}. Universal Event & Ticketing Platform.</span>
                </div>

                <div class="flex items-center gap-4 text-xs font-medium text-[var(--text-secondary)]">
                    <span class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                        <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Cluster Operational
                    </span>
                    <span>·</span>
                    <a href="{{ route('dashboard') }}" class="hover:text-[var(--text-primary)] transition">Operations Hub</a>
                </div>
            </div>
        </footer>

    </body>
</html>
