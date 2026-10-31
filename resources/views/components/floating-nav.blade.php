@props([
    'showBack' => true,
    'current' => null,
])

@php
    $navUser = auth()->user();
    $staffHome = $navUser?->staffHomeUrl();
    $navLinks = \App\Models\PlatformSetting::isDedicatedMode()
        ? [
            ['label' => 'Schedule', 'href' => url('/') . '#schedule', 'active' => false],
            ['label' => 'Speakers', 'href' => route('dedicated.speakers'), 'active' => request()->routeIs('dedicated.speakers')],
            ['label' => 'Packages', 'href' => route('dedicated.packages'), 'active' => request()->routeIs('dedicated.packages')],
            ['label' => 'FAQs', 'href' => route('dedicated.faq'), 'active' => request()->routeIs('dedicated.faq')],
        ]
        : [
            ['label' => 'Events', 'href' => route('public.events.index'), 'active' => request()->routeIs('public.events.*')],
        ];
@endphp

<nav
    aria-label="Global floating navigation"
    class="fixed top-4 inset-x-0 mx-auto max-w-fit z-50 rounded-full border border-[var(--border-hairline)] bg-[var(--bg-surface)]/85 backdrop-blur-xl shadow-lg p-1.5 flex items-center gap-1 sm:gap-1.5 transition-[border-color,box-shadow] duration-200 pointer-events-auto"
>
    {{-- Fluid History Controls (Back & Forward) --}}
    @if($showBack)
        <div class="flex items-center rounded-full bg-[var(--bg-subtle)] border border-[var(--border-hairline)] p-0.5">
            <button
                type="button"
                onclick="window.history.length > 1 ? window.history.back() : window.location.href='/'"
                class="size-7 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-surface)] flex items-center justify-center transition-colors press-scale cursor-pointer group"
                aria-label="Go back"
                title="Go back (Alt+Left or Backspace)"
            >
                <svg class="size-3.5 transition-transform duration-150 group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <div class="h-3.5 w-px bg-[var(--border-hairline)]"></div>

            <button
                type="button"
                onclick="window.history.forward()"
                class="size-7 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-surface)] flex items-center justify-center transition-colors press-scale cursor-pointer group"
                aria-label="Go forward"
                title="Go forward (Alt+Right)"
            >
                <svg class="size-3.5 transition-transform duration-150 group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>
    @endif

    {{-- Brand Anchor Glyph --}}
    <a
        href="{{ url('/') }}"
        class="size-8 rounded-full bg-[var(--bg-subtle)] border border-[var(--border-hairline)] flex items-center justify-center text-[var(--text-primary)] hover:border-[var(--text-primary)] transition-colors press-scale cursor-pointer"
        aria-label="TicketLab Homepage"
        title="TicketLab Home"
    >
        <x-app-logo-icon class="size-4.5" />
    </a>

    {{-- Context Pill if on Sub-page --}}
    @if($current)
        <span class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[var(--bg-subtle)] text-[11px] font-semibold text-[var(--text-secondary)] border border-[var(--border-hairline)]">
            <span class="size-1.5 rounded-full bg-emerald-500"></span>
            <span>{{ $current }}</span>
        </span>
    @endif

    {{-- Nav Links Panning Rail --}}
    <div class="flex items-center gap-1 text-xs font-semibold">
        <a
            href="{{ url('/') }}"
            @class([
                'px-3 py-1.5 rounded-full transition-colors duration-150 press-scale cursor-pointer',
                'bg-[var(--text-primary)] text-[var(--bg-canvas)] shadow-xs' => request()->is('/'),
                'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)]' => !request()->is('/'),
            ])
        >
            Home
        </a>

        @foreach ($navLinks as $navLink)
            <a
                href="{{ $navLink['href'] }}"
                @class([
                    'hidden sm:inline-flex px-3 py-1.5 rounded-full transition-colors duration-150 press-scale cursor-pointer',
                    'bg-[var(--text-primary)] text-[var(--bg-canvas)] shadow-xs' => $navLink['active'],
                    'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)]' => !$navLink['active'],
                ])
            >
                {{ $navLink['label'] }}
            </a>
        @endforeach
    </div>

    {{-- Fluid Theme Toggle (Liquid Ripple Trigger with Button Origin) --}}
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

    {{-- Auth Quick CTA Pill: staff get one discrete door into their own surface; internal tools are never listed here. --}}
    @if ($staffHome !== null)
        <a
            href="{{ $staffHome }}"
            class="inline-flex items-center gap-1 pl-3 pr-3.5 py-1.5 rounded-full bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold hover:opacity-90 transition-opacity shadow-xs press-scale cursor-pointer"
        >
            <span>Manage Event</span>
            <span class="text-[10px]">→</span>
        </a>
    @elseif ($navUser !== null)
        <a
            href="{{ route('dashboard') }}"
            class="inline-flex items-center px-3.5 py-1.5 rounded-full bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold hover:opacity-90 transition-opacity shadow-xs press-scale cursor-pointer"
        >
            My Passes
        </a>
    @else
        <a
            href="{{ route('login') }}"
            class="inline-flex items-center px-3.5 py-1.5 rounded-full bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold hover:opacity-90 transition-opacity shadow-xs press-scale cursor-pointer"
        >
            Sign In
        </a>
    @endif
</nav>

<script>
    if (!window._floatingNavKeyboardRegistered) {
        window._floatingNavKeyboardRegistered = true;
        window.addEventListener('keydown', (e) => {
            const tag = document.activeElement?.tagName?.toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select' || document.activeElement?.isContentEditable) {
                return;
            }
            if (e.key === 'Backspace' || (e.altKey && e.key === 'ArrowLeft')) {
                if (window.history.length > 1) {
                    e.preventDefault();
                    window.history.back();
                }
            } else if (e.altKey && e.key === 'ArrowRight') {
                e.preventDefault();
                window.history.forward();
            }
        });
    }
</script>
