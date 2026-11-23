@props([
    'organization',
    'event' => null,
    'title' => null,
    'description' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <title>{{ $title ? $title . ' | ' : '' }}{{ $event?->title ?? $organization->name }}</title>
        <meta name="description" content="{{ $description ?? $event?->description ?? 'Welcome to ' . $organization->name }}" />
        <style>
            :root { {{ \App\Support\BrandColor::cssVariables($organization, $event) }} }
            @keyframes dedicated-marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }
            .dedicated-marquee { animation: dedicated-marquee 32s linear infinite; }
            .dedicated-marquee:hover { animation-play-state: paused; }
            @media (prefers-reduced-motion: reduce) { .dedicated-marquee { animation: none; } }
        </style>
        <script>
            // Live countdown used by the hero; ticks once a second and stops at zero.
            window.dedicatedCountdown = (target) => ({
                days: '00', hours: '00', mins: '00', secs: '00', started: false, timer: null,
                init() { this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
                destroy() { clearInterval(this.timer); },
                tick() {
                    const diff = Math.max(0, new Date(target).getTime() - Date.now());
                    const pad = (n) => String(Math.floor(n)).padStart(2, '0');
                    this.days = pad(diff / 86400000);
                    this.hours = pad((diff / 3600000) % 24);
                    this.mins = pad((diff / 60000) % 60);
                    this.secs = pad((diff / 1000) % 60);
                    this.started = diff === 0;
                    if (this.started) { clearInterval(this.timer); }
                },
            });
        </script>
        @livewireStyles
    </head>
    <body class="relative flex min-h-screen flex-col overflow-x-hidden bg-[var(--bg-canvas)] text-[var(--text-primary)] antialiased selection:bg-[var(--dedicated-accent)] selection:text-white">
        <x-bespoke-navbar :organization="$organization" :event="$event" />

        <main class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-[var(--border-hairline)] bg-[var(--bg-surface)] px-4 py-10 text-xs text-[var(--text-tertiary)]">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 sm:flex-row">
                <div>© {{ date('Y') }} {{ $organization->name }}. All rights reserved.</div>
                <div class="flex flex-wrap items-center justify-center gap-4">
                    <a href="{{ $organization->pageUrl('partnership') }}" class="transition hover:text-[var(--text-primary)]">Partnership</a>
                    <a href="{{ $organization->pageUrl('contact') }}" class="transition hover:text-[var(--text-primary)]">Contact</a>
                    <span>{{ $organization->contactEmail() }}</span>
                </div>
            </div>
        </footer>
    </body>
</html>
