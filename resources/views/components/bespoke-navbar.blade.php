@props([
    'organization',
    'event' => null,
    'collapsible' => true,
    'isEventPage' => false,
])

@php
    $custom = is_array($event?->customization) ? $event->customization : [];
    $accentHex = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($custom['accent_hex'] ?? '')) === 1 ? $custom['accent_hex'] : '#d97706';
    $navUser = auth()->user();
    $staffHome = $navUser?->staffHomeUrl();
    $isMultiTenant = !App\Models\PlatformSetting::isDedicatedMode();
    $homeUrl = $isMultiTenant ? url('/' . $organization->slug) : url('/');
    $siteLinks = $organization->bespokeNavLinks($isMultiTenant);

    $checkoutUrl = null;
    if ($isEventPage) {
        $checkoutUrl = '#tickets';
    } elseif ($event !== null) {
        $checkoutUrl = route('public.event.show', ['org_slug' => $organization->slug, 'event_slug' => $event->slug]);
    }
@endphp

<div
    x-data="{
        open: false,
        collapsed: false,
        toggleCollapse() {
            this.collapsed = !this.collapsed;
        }
    }"
    class="pointer-events-none fixed inset-x-0 top-4 z-50 mx-auto flex justify-center px-4"
>
    <nav
        aria-label="{{ $organization->name }} navigation"
        class="pointer-events-auto flex items-center gap-1.5 rounded-full border border-[var(--border-hairline)] bg-[var(--bg-surface)]/90 p-1.5 shadow-lg backdrop-blur-xl transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
        :class="{
            'px-2 py-1.5 shadow-md': collapsed,
            'p-1.5': !collapsed
        }"
    >
        {{-- Brand / Home Pill --}}
        <a
            href="{{ $homeUrl }}"
            class="flex items-center gap-2 rounded-full bg-[var(--bg-subtle)] px-3 py-1.5 text-xs font-bold transition hover:opacity-90 press-scale"
            :title="collapsed ? 'Expand & Return Home' : '{{ $organization->name }} Home'"
        >
            <span class="size-2 rounded-full bg-[var(--dedicated-accent,{{ $accentHex }})]"></span>
            <span class="max-w-[9rem] truncate text-[var(--text-primary)]">{{ $organization->name }}</span>
        </a>

        {{-- Desktop Navigation Links (Collapsible) --}}
        <div
            x-show="!collapsed"
            x-transition:enter="transition-all duration-200 ease-out"
            x-transition:enter-start="opacity-0 max-w-0"
            x-transition:enter-end="opacity-100 max-w-2xl"
            x-transition:leave="transition-all duration-150 ease-in"
            x-transition:leave-start="opacity-100 max-w-2xl"
            x-transition:leave-end="opacity-0 max-w-0"
            class="hidden items-center gap-0.5 overflow-hidden text-xs font-semibold text-[var(--text-secondary)] lg:flex"
        >
            <a
                href="{{ $isEventPage ? '#schedule' : $homeUrl . '#schedule' }}"
                class="rounded-full px-3 py-1 transition hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]"
            >Schedule</a>

            @foreach ($siteLinks as $link)
                <a
                    href="{{ $link['url'] }}"
                    @class([
                        'rounded-full px-3 py-1 transition',
                        'bg-[var(--bg-subtle)] text-[var(--text-primary)]' => $link['active'],
                        'hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' => !$link['active'],
                    ])
                >{{ $link['label'] }}</a>
            @endforeach
        </div>

        {{-- Call To Action --}}
        @if ($checkoutUrl)
            <a
                href="{{ $checkoutUrl }}"
                x-show="!collapsed"
                x-transition
                class="hidden rounded-full bg-[var(--dedicated-accent,{{ $accentHex }})] px-4 py-1.5 text-xs font-bold text-white shadow-xs transition hover:opacity-90 press-scale sm:inline-flex"
            >Get Passes</a>
        @endif

        {{-- Staff Link --}}
        @if ($staffHome !== null)
            <a
                href="{{ $staffHome }}"
                x-show="!collapsed"
                x-transition
                class="hidden rounded-full border border-[var(--border-hairline)] px-3 py-1.5 text-xs font-semibold text-[var(--text-secondary)] transition hover:text-[var(--text-primary)] sm:inline-flex"
            >Manage Event →</a>
        @elseif ($navUser === null)
            <a
                href="{{ route('login') }}"
                x-show="!collapsed"
                x-transition
                class="hidden rounded-full px-3 py-1.5 text-xs font-semibold text-[var(--text-secondary)] transition hover:text-[var(--text-primary)] sm:inline-flex"
            >Sign In</a>
        @endif

        {{-- Mobile Hamburger --}}
        <button
            type="button"
            x-show="!collapsed"
            x-transition
            class="flex size-8 cursor-pointer items-center justify-center rounded-full border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[var(--text-secondary)] lg:hidden"
            x-on:click="open = !open"
            aria-label="Toggle menu"
            :aria-expanded="open"
        >
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>

        {{-- Theme Toggle --}}
        <button
            type="button"
            onclick="window.toggleStudioTheme ? window.toggleStudioTheme(event, null, this) : document.documentElement.classList.toggle('dark')"
            class="flex size-8 cursor-pointer items-center justify-center rounded-full border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[var(--text-secondary)] transition hover:text-[var(--text-primary)] press-scale"
            aria-label="Toggle theme"
            title="Toggle light/dark theme"
        >
            <svg class="size-3.5 block dark:hidden" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>
            <svg class="size-3.5 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" /></svg>
        </button>

        {{-- Soothing Collapse / Expand Micro-interaction Toggle --}}
        @if ($collapsible)
            <button
                type="button"
                x-on:click="toggleCollapse()"
                class="flex size-8 cursor-pointer items-center justify-center rounded-full border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[var(--text-secondary)] transition hover:text-[var(--text-primary)] press-scale"
                :title="collapsed ? 'Expand Navigation' : 'Minimize Navigation'"
                :aria-label="collapsed ? 'Expand Navigation' : 'Minimize Navigation'"
            >
                <svg
                    class="size-3.5 transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
                    :class="{ 'rotate-180': collapsed }"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="2.25"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
            </button>
        @endif
    </nav>

    {{-- Mobile Dropdown Drawer --}}
    <div
        x-show="open && !collapsed"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
        x-on:click.outside="open = false"
        class="pointer-events-auto fixed inset-x-4 top-20 z-50 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-xl backdrop-blur-xl sm:left-auto sm:right-4 sm:w-72 lg:hidden"
        style="display: none;"
    >
        <div class="flex flex-col gap-2 text-sm font-semibold">
            <a href="{{ $isEventPage ? '#schedule' : $homeUrl . '#schedule' }}" x-on:click="open = false" class="rounded-lg px-3 py-2 text-[var(--text-secondary)] transition hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]">Schedule</a>
            @foreach ($siteLinks as $link)
                <a href="{{ $link['url'] }}" x-on:click="open = false" class="rounded-lg px-3 py-2 text-[var(--text-secondary)] transition hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]">{{ $link['label'] }}</a>
            @endforeach
            @if ($checkoutUrl)
                <a href="{{ $checkoutUrl }}" x-on:click="open = false" class="mt-2 rounded-xl bg-[var(--dedicated-accent,{{ $accentHex }})] px-4 py-2.5 text-center text-xs font-bold text-white shadow-xs">Get Passes</a>
            @endif
        </div>
    </div>
</div>

