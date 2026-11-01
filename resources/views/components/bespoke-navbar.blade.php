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
    $siteLinks = $organization->bespokeNavLinks();

    $checkoutUrl = null;
    if ($isEventPage) {
        $checkoutUrl = '#tickets';
    } elseif ($event !== null) {
        $checkoutUrl = route('public.event.show', ['org_slug' => $organization->slug, 'event_slug' => $event->slug]);
    }

    $milestones = [
        [
            'label' => 'Schedule',
            'desc' => 'Session roadmap & hours',
            'url' => $isEventPage ? '#schedule' : $homeUrl . '#schedule',
            'active' => false,
        ],
    ];
    foreach ($siteLinks as $link) {
        $milestones[] = [
            'label' => $link['label'],
            'desc' => match(strtolower(trim($link['label']))) {
                'speakers' => 'Featured keynote leaders',
                'packages', 'passes', 'tickets' => 'Admission & tier access',
                'highlights', 'gallery' => 'Visual moments & media',
                'faq' => 'Common attendee questions',
                'contact' => 'Venue location & support',
                default => 'Event section',
            },
            'url' => $link['url'],
            'active' => (bool) ($link['active'] ?? false),
        ];
    }
@endphp

<div
    x-data="{
        open: false,
        collapsed: false,
        toggleCollapse() {
            this.collapsed = !this.collapsed;
        },
        toggleMobileMenu() {
            this.open = !this.open;
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
            id="mobile-menu-trigger"
            type="button"
            x-show="!collapsed"
            x-transition
            class="flex size-8 cursor-pointer items-center justify-center rounded-full border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition lg:hidden press-scale"
            x-on:click.stop="toggleMobileMenu()"
            aria-label="Toggle menu"
            :aria-expanded="open"
        >
            <svg class="pointer-events-none size-4 transition-transform duration-200" :class="{ 'rotate-90': open }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" :d="open ? 'M6 18L18 6M6 6l12 12' : 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5'" />
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

        {{-- Soothing Collapse / Expand Micro-interaction Toggle (Desktop Only) --}}
        @if ($collapsible)
            <button
                type="button"
                x-on:click="toggleCollapse()"
                class="hidden lg:flex size-8 cursor-pointer items-center justify-center rounded-full border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[var(--text-secondary)] transition hover:text-[var(--text-primary)] press-scale"
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

    {{-- Mobile Dimming Backdrop --}}
    <div
        x-show="open && !collapsed"
        x-transition.opacity.duration.200ms
        x-on:click="open = false"
        class="pointer-events-auto fixed inset-0 z-40 bg-black/50 backdrop-blur-xs lg:hidden"
        style="display: none;"
        aria-hidden="true"
    ></div>

    {{-- Mobile Dropdown Drawer: Circular Timeline Journey --}}
    <div
        x-show="open && !collapsed"
        x-transition:enter="transition ease-[cubic-bezier(0.16,1,0.3,1)] duration-260"
        x-transition:enter-start="opacity-0 -translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-[cubic-bezier(0.2,0,0,1)] duration-180"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
        x-on:click.outside="if (!$event.target.closest('#mobile-menu-trigger')) open = false"
        class="pointer-events-auto fixed inset-x-4 top-20 z-50 rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)]/95 p-5 shadow-2xl backdrop-blur-2xl sm:left-auto sm:right-4 sm:w-80 lg:hidden"
        style="display: none;"
    >
        {{-- Header title with current event badge --}}
        <div class="flex items-center justify-between pb-3 mb-3 border-b border-[var(--border-hairline)]">
            <span class="text-[10px] font-bold uppercase tracking-widest text-[var(--text-tertiary)]">Event Journey</span>
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[var(--bg-subtle)] text-[var(--text-secondary)] border border-[var(--border-hairline)]">
                <span class="size-1.5 rounded-full bg-[var(--dedicated-accent,{{ $accentHex }})]"></span>
                <span>{{ $event?->title ?? $organization->name }}</span>
            </span>
        </div>

        {{-- Vertical Track with Circular Timeline Nodes --}}
        <div class="relative pl-6 before:absolute before:left-2.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-[var(--border-hairline)] space-y-3.5">
            @foreach ($milestones as $idx => $m)
                <a
                    href="{{ $m['url'] }}"
                    x-on:click="open = false"
                    class="group relative flex items-start gap-3 text-left transition press-scale"
                >
                    {{-- Timeline Circular Node --}}
                    <div class="absolute -left-6 top-0.5 flex size-5 items-center justify-center rounded-full border-2 {{ $m['active'] ? 'border-[var(--dedicated-accent,'.$accentHex.')] bg-[var(--dedicated-accent,'.$accentHex.')]/20 shadow-[0_0_8px_var(--dedicated-accent,'.$accentHex.')]' : 'border-[var(--border-hairline)] bg-[var(--bg-surface)] group-hover:border-[var(--text-secondary)]' }} transition">
                        <span class="size-1.5 rounded-full {{ $m['active'] ? 'bg-[var(--dedicated-accent,'.$accentHex.')]' : 'bg-[var(--text-tertiary)] group-hover:bg-[var(--text-primary)]' }}"></span>
                    </div>

                    {{-- Milestone Title & Subtitle --}}
                    <div class="flex flex-col">
                        <span class="text-xs font-bold transition {{ $m['active'] ? 'text-[var(--text-primary)]' : 'text-[var(--text-secondary)] group-hover:text-[var(--text-primary)]' }}">
                            {{ $m['label'] }}
                        </span>
                        <span class="text-[10px] text-[var(--text-tertiary)] font-normal leading-tight">
                            {{ $m['desc'] }}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Bottom Action: Pass Purchase Terminal --}}
        @if ($checkoutUrl)
            <div class="mt-5 pt-3 border-t border-[var(--border-hairline)]">
                <a
                    href="{{ $checkoutUrl }}"
                    x-on:click="open = false"
                    class="flex items-center justify-between w-full rounded-2xl bg-[var(--dedicated-accent,{{ $accentHex }})] px-4 py-2.5 text-xs font-bold text-white shadow-md transition hover:opacity-95 press-scale"
                >
                    <span class="flex items-center gap-2">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                        </svg>
                        <span>Get Passes</span>
                    </span>
                    <svg class="size-4 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
            </div>
        @endif
    </div>
</div>

