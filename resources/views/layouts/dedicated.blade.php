@props([
    'organization',
    'event' => null,
    'title' => null,
    'description' => null,
])

@php
    $custom = is_array($event?->customization) ? $event->customization : [];
    $accentHex = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($custom['accent_hex'] ?? '')) === 1 ? $custom['accent_hex'] : '#d97706';
    $navUser = auth()->user();
    $staffHome = $navUser?->staffHomeUrl();
    $siteLinks = [
        ['route' => 'dedicated.about', 'label' => 'About'],
        ['route' => 'dedicated.speakers', 'label' => 'Speakers'],
        ['route' => 'dedicated.packages', 'label' => 'Packages'],
        ['route' => 'dedicated.highlights', 'label' => 'Highlights'],
        ['route' => 'dedicated.faq', 'label' => 'FAQs'],
        ['route' => 'dedicated.contact', 'label' => 'Contact'],
    ];
    $checkoutUrl = $event !== null
        ? route('public.event.show', ['org_slug' => $organization->slug, 'event_slug' => $event->slug])
        : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <title>{{ $title ? $title . ' | ' : '' }}{{ $event?->title ?? $organization->name }}</title>
        <meta name="description" content="{{ $description ?? $event?->description ?? 'Welcome to ' . $organization->name }}" />
        <style>
            :root { --dedicated-accent: {{ $accentHex }}; }
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
    </head>
    <body class="relative flex min-h-screen flex-col overflow-x-hidden bg-[var(--bg-canvas)] text-[var(--text-primary)] antialiased selection:bg-[var(--dedicated-accent)] selection:text-white">
        <nav
            aria-label="{{ $organization->name }} navigation"
            x-data="{ open: false }"
            class="pointer-events-auto fixed inset-x-0 top-4 z-50 mx-auto flex max-w-fit items-center gap-1.5 rounded-full border border-[var(--border-hairline)] bg-[var(--bg-surface)]/90 p-1.5 shadow-lg backdrop-blur-xl"
        >
            <a href="{{ url('/') }}" class="flex items-center gap-2 rounded-full bg-[var(--bg-subtle)] px-3 py-1.5 text-xs font-bold transition hover:opacity-90 press-scale">
                <span class="size-2 rounded-full bg-[var(--dedicated-accent)]"></span>
                <span class="max-w-[9rem] truncate">{{ $organization->name }}</span>
            </a>

            <div class="hidden items-center gap-0.5 text-xs font-semibold text-[var(--text-secondary)] lg:flex">
                <a href="{{ url('/') }}#schedule" class="rounded-full px-3 py-1 transition hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]">Schedule</a>
                @foreach ($siteLinks as $link)
                    <a
                        href="{{ route($link['route']) }}"
                        @class([
                            'rounded-full px-3 py-1 transition',
                            'bg-[var(--bg-subtle)] text-[var(--text-primary)]' => request()->routeIs($link['route']),
                            'hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' => !request()->routeIs($link['route']),
                        ])
                    >{{ $link['label'] }}</a>
                @endforeach
            </div>

            @if ($checkoutUrl)
                <a href="{{ $checkoutUrl }}" class="rounded-full bg-[var(--dedicated-accent)] px-4 py-1.5 text-xs font-bold text-white shadow-xs transition hover:opacity-90 press-scale">Get Passes</a>
            @endif

            @if ($staffHome !== null)
                <a href="{{ $staffHome }}" class="hidden rounded-full border border-[var(--border-hairline)] px-3 py-1.5 text-xs font-semibold text-[var(--text-secondary)] transition hover:text-[var(--text-primary)] sm:inline-flex">Manage Event →</a>
            @elseif ($navUser === null)
                <a href="{{ route('login') }}" class="hidden rounded-full px-3 py-1.5 text-xs font-semibold text-[var(--text-secondary)] transition hover:text-[var(--text-primary)] sm:inline-flex">Sign In</a>
            @endif

            <button type="button" class="flex size-8 cursor-pointer items-center justify-center rounded-full border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[var(--text-secondary)] lg:hidden" x-on:click="open = !open" aria-label="Toggle menu" :aria-expanded="open">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
            </button>

            <button
                type="button"
                onclick="window.toggleStudioTheme ? window.toggleStudioTheme(event, null, this) : document.documentElement.classList.toggle('dark')"
                class="flex size-8 cursor-pointer items-center justify-center rounded-full border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[var(--text-secondary)] transition-colors hover:border-[var(--text-primary)] hover:text-[var(--text-primary)] press-scale"
                aria-label="Toggle dark/light theme"
            >
                <svg class="size-4 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                <svg class="hidden size-4 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
            </button>

            {{-- Mobile menu panel --}}
            <div
                x-show="open"
                x-transition.origin.top
                x-on:click.outside="open = false"
                class="absolute inset-x-0 top-full mt-2 grid grid-cols-2 gap-1 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-2 text-xs font-semibold shadow-xl lg:hidden"
                style="display: none;"
            >
                <a href="{{ url('/') }}#schedule" class="rounded-xl px-3 py-2 hover:bg-[var(--bg-subtle)]">Schedule</a>
                @foreach ($siteLinks as $link)
                    <a href="{{ route($link['route']) }}" class="rounded-xl px-3 py-2 hover:bg-[var(--bg-subtle)]">{{ $link['label'] }}</a>
                @endforeach
                @if ($staffHome !== null)
                    <a href="{{ $staffHome }}" class="col-span-2 rounded-xl px-3 py-2 hover:bg-[var(--bg-subtle)]">Manage Event →</a>
                @elseif ($navUser === null)
                    <a href="{{ route('login') }}" class="col-span-2 rounded-xl px-3 py-2 hover:bg-[var(--bg-subtle)]">Sign In</a>
                @endif
            </div>
        </nav>

        <main class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-[var(--border-hairline)] bg-[var(--bg-surface)] px-4 py-10 text-xs text-[var(--text-tertiary)]">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 sm:flex-row">
                <div>© {{ date('Y') }} {{ $organization->name }}. All rights reserved.</div>
                <div class="flex flex-wrap items-center justify-center gap-4">
                    <a href="{{ route('dedicated.partnership') }}" class="transition hover:text-[var(--text-primary)]">Partnership</a>
                    <a href="{{ route('dedicated.contact') }}" class="transition hover:text-[var(--text-primary)]">Contact</a>
                    <span>{{ $organization->contactEmail() }}</span>
                </div>
            </div>
        </footer>
    </body>
</html>
