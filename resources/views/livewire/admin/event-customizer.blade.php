<div class="space-y-6">
    <!-- Header & Event Switcher -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-[var(--text-primary)]">
                Visual Customizer Studio
            </h1>
            <p class="text-xs sm:text-sm text-[var(--text-secondary)] mt-0.5">
                Style your event landing page, configure speakers, multi-day schedules, dynamic questionnaires, and FAQs.
            </p>
        </div>

        <div class="flex items-center gap-3">
            @if ($events->count() > 1)
                <select
                    wire:model.live="selectedEventId"
                    class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-2 text-xs font-semibold text-[var(--text-primary)] shadow-xs focus:outline-hidden [color-scheme:light] dark:[color-scheme:dark] cursor-pointer"
                >
                    @foreach ($events as $ev)
                        <option value="{{ $ev->id }}">{{ $ev->title }}</option>
                    @endforeach
                </select>
            @endif

            @if ($currentEvent)
                <a
                    href="{{ route('public.event.show', ['org_slug' => $currentEvent->organization->slug, 'event_slug' => $currentEvent->slug]) }}"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition shadow-xs press-scale cursor-pointer"
                >
                    <span>Live Preview</span>
                    <svg class="size-3.5 text-[var(--text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                </a>
            @endif
        </div>
    </div>

    <!-- Flash Alert -->
    @if ($flashMessage)
        <div class="flex items-center justify-between rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-xs font-medium text-emerald-800 dark:text-emerald-300">
            <div class="flex items-center gap-2">
                <svg class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ $flashMessage }}</span>
            </div>
            <button type="button" wire:click="$set('flashMessage', null)" class="text-emerald-700 hover:text-emerald-900 dark:hover:text-emerald-100 cursor-pointer">
                &times;
            </button>
        </div>
    @endif

    @if ($currentEvent === null)
        <div class="rounded-3xl border border-dashed border-[var(--border-hairline)] bg-[var(--bg-surface)] p-12 text-center">
            <div class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-[var(--bg-subtle)] text-[var(--text-tertiary)]">
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <h3 class="mt-4 text-sm font-semibold text-[var(--text-primary)]">No events created yet</h3>
            <p class="mt-1 text-xs text-[var(--text-secondary)] max-w-sm mx-auto">
                Create an event to begin customizing visual presentation, program rosters, and custom fields.
            </p>
            <a href="{{ route('admin.events') }}" wire:navigate class="mt-5 inline-flex items-center gap-1.5 rounded-xl bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-[var(--color-primary-fg)] shadow-xs press-scale">+ Create Event</a>
        </div>
    @else
        <!-- Studio Tab Rail -->
        <div class="flex items-center gap-1.5 border-b border-[var(--border-hairline)] pb-2 overflow-x-auto">
            <button
                type="button"
                wire:click="$set('activeTab', 'brand')"
                @class([
                    'px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer press-scale shrink-0',
                    'bg-[var(--text-primary)] text-[var(--bg-canvas)] shadow-xs' => $activeTab === 'brand',
                    'text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' => $activeTab !== 'brand',
                ])
            >
                🎨 Brand & Palette
            </button>

            <button
                type="button"
                wire:click="$set('activeTab', 'speakers')"
                @class([
                    'px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer press-scale shrink-0',
                    'bg-[var(--text-primary)] text-[var(--bg-canvas)] shadow-xs' => $activeTab === 'speakers',
                    'text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' => $activeTab !== 'speakers',
                ])
            >
                🎤 Speakers & Guests ({{ $speakers->count() }})
            </button>

            <button
                type="button"
                wire:click="$set('activeTab', 'schedule')"
                @class([
                    'px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer press-scale shrink-0',
                    'bg-[var(--text-primary)] text-[var(--bg-canvas)] shadow-xs' => $activeTab === 'schedule',
                    'text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' => $activeTab !== 'schedule',
                ])
            >
                📅 Program Schedule ({{ $schedules->count() }})
            </button>

            <button
                type="button"
                wire:click="$set('activeTab', 'form_fields')"
                @class([
                    'px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer press-scale shrink-0',
                    'bg-[var(--text-primary)] text-[var(--bg-canvas)] shadow-xs' => $activeTab === 'form_fields',
                    'text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' => $activeTab !== 'form_fields',
                ])
            >
                📋 Questionnaire Builder ({{ $formFields->count() }})
            </button>

            <button
                type="button"
                wire:click="$set('activeTab', 'faqs')"
                @class([
                    'px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer press-scale shrink-0',
                    'bg-[var(--text-primary)] text-[var(--bg-canvas)] shadow-xs' => $activeTab === 'faqs',
                    'text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' => $activeTab !== 'faqs',
                ])
            >
                ❓ FAQs ({{ $faqs->count() }})
            </button>
        </div>

        <!-- Tab 1: Brand & Palette -->
        @if ($activeTab === 'brand')
            <form wire:submit="saveBrandAndHero" class="space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left: Form Controls -->
                    <div class="lg:col-span-2 space-y-5">
                        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 sm:p-6 shadow-xs space-y-4">
                            <h3 class="text-sm font-bold text-[var(--text-primary)]">Core Event Identity</h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                                        Event Title <span class="text-rose-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="title"
                                        class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs font-medium text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden"
                                    />
                                    @error('title') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                                        Venue Location
                                    </label>
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="venue"
                                        placeholder="e.g. Perez Dome, Dzorwulu, Accra"
                                        class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs font-medium text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden"
                                    />
                                    @error('venue') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                                        Start Date & Time
                                    </label>
                                    <input
                                        type="datetime-local"
                                        wire:model.live.debounce.300ms="start_at"
                                        class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden [color-scheme:light] dark:[color-scheme:dark]"
                                    />
                                    @error('start_at') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                                        End Date & Time
                                    </label>
                                    <input
                                        type="datetime-local"
                                        wire:model="end_at"
                                        class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden [color-scheme:light] dark:[color-scheme:dark]"
                                    />
                                    @error('end_at') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Hero Copy & Visual Media -->
                        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 sm:p-6 shadow-xs space-y-4">
                            <h3 class="text-sm font-bold text-[var(--text-primary)]">Hero Presentation</h3>

                            <div>
                                <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                                    Hero Headline <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="headline"
                                    placeholder="e.g. Unlocking Africa's Tech & Creative Vanguard"
                                    class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs font-medium text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden"
                                />
                                @error('headline') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                                    Hero Tagline
                                </label>
                                <textarea
                                    wire:model.live.debounce.300ms="tagline"
                                    rows="2"
                                    placeholder="Brief summary shown directly below the headline on public tickets page..."
                                    class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs font-medium text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden"
                                ></textarea>
                                @error('tagline') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                                    Banner Image URL
                                </label>
                                <input
                                    type="url"
                                    wire:model.live.blur="banner_path"
                                    placeholder="https://images.unsplash.com/... or /images/banner.jpg"
                                    class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs font-medium text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden"
                                />
                                @error('banner_path') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Theme Presets & Palette -->
                        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 sm:p-6 shadow-xs space-y-4">
                            <h3 class="text-sm font-bold text-[var(--text-primary)]">Color Palette & Mood</h3>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <label
                                    wire:click="$set('accent_hex', '#18181b')"
                                    class="flex flex-col gap-2 p-3 rounded-xl border cursor-pointer transition {{ $theme_preset === 'serene' ? 'border-[var(--text-primary)] bg-[var(--bg-subtle)] ring-1 ring-[var(--text-primary)]' : 'border-[var(--border-hairline)] bg-[var(--bg-canvas)]' }}"
                                >
                                    <input type="radio" value="serene" wire:model.live="theme_preset" class="sr-only" />
                                    <div class="flex items-center gap-1.5">
                                        <div class="size-4 rounded-full bg-zinc-900 border border-zinc-700"></div>
                                        <div class="size-4 rounded-full bg-zinc-100 border border-zinc-300"></div>
                                    </div>
                                    <span class="text-xs font-bold text-[var(--text-primary)]">Serene Studio</span>
                                    <span class="text-[10px] text-[var(--text-secondary)]">Warm Linen / Basalt</span>
                                </label>

                                <label
                                    wire:click="$set('accent_hex', '#6366f1')"
                                    class="flex flex-col gap-2 p-3 rounded-xl border cursor-pointer transition {{ $theme_preset === 'royal' ? 'border-[var(--text-primary)] bg-[var(--bg-subtle)] ring-1 ring-[var(--text-primary)]' : 'border-[var(--border-hairline)] bg-[var(--bg-canvas)]' }}"
                                >
                                    <input type="radio" value="royal" wire:model.live="theme_preset" class="sr-only" />
                                    <div class="flex items-center gap-1.5">
                                        <div class="size-4 rounded-full bg-indigo-600"></div>
                                        <div class="size-4 rounded-full bg-amber-400"></div>
                                    </div>
                                    <span class="text-xs font-bold text-[var(--text-primary)]">Royal Indigo</span>
                                    <span class="text-[10px] text-[var(--text-secondary)]">Amethyst & Gold</span>
                                </label>

                                <label
                                    wire:click="$set('accent_hex', '#059669')"
                                    class="flex flex-col gap-2 p-3 rounded-xl border cursor-pointer transition {{ $theme_preset === 'emerald' ? 'border-[var(--text-primary)] bg-[var(--bg-subtle)] ring-1 ring-[var(--text-primary)]' : 'border-[var(--border-hairline)] bg-[var(--bg-canvas)]' }}"
                                >
                                    <input type="radio" value="emerald" wire:model.live="theme_preset" class="sr-only" />
                                    <div class="flex items-center gap-1.5">
                                        <div class="size-4 rounded-full bg-emerald-600"></div>
                                        <div class="size-4 rounded-full bg-teal-300"></div>
                                    </div>
                                    <span class="text-xs font-bold text-[var(--text-primary)]">Nordic Pine</span>
                                    <span class="text-[10px] text-[var(--text-secondary)]">Forest & Bronze</span>
                                </label>

                                <label
                                    wire:click="$set('accent_hex', '#d97706')"
                                    class="flex flex-col gap-2 p-3 rounded-xl border cursor-pointer transition {{ $theme_preset === 'amber' ? 'border-[var(--text-primary)] bg-[var(--bg-subtle)] ring-1 ring-[var(--text-primary)]' : 'border-[var(--border-hairline)] bg-[var(--bg-canvas)]' }}"
                                >
                                    <input type="radio" value="amber" wire:model.live="theme_preset" class="sr-only" />
                                    <div class="flex items-center gap-1.5">
                                        <div class="size-4 rounded-full bg-amber-600"></div>
                                        <div class="size-4 rounded-full bg-orange-400"></div>
                                    </div>
                                    <span class="text-xs font-bold text-[var(--text-primary)]">Warm Ochre</span>
                                    <span class="text-[10px] text-[var(--text-secondary)]">Savannah Amber</span>
                                </label>
                            </div>

                            <div class="flex items-center gap-3 pt-2">
                                <label for="accent_hex" class="text-xs font-semibold text-[var(--text-secondary)]">
                                    Custom Accent Hex:
                                </label>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="color"
                                        id="accent_hex"
                                        wire:model.live="accent_hex"
                                        class="size-8 rounded-lg border border-[var(--border-hairline)] cursor-pointer bg-transparent"
                                    />
                                    <input
                                        type="text"
                                        wire:model.live="accent_hex"
                                        class="w-24 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-2.5 py-1 text-xs font-mono font-bold uppercase text-[var(--text-primary)]"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Section Visibility Toggles & Live Preview Box -->
                    <div class="space-y-5">
                        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 sm:p-6 shadow-xs space-y-4">
                            <h3 class="text-sm font-bold text-[var(--text-primary)]">Feature Sections Visibility</h3>
                            <div class="space-y-3">
                                <label class="flex items-center justify-between text-xs font-medium text-[var(--text-primary)] cursor-pointer">
                                    <span>Live Countdown Timer</span>
                                    <input type="checkbox" wire:model.live="show_countdown" class="size-4 rounded-md text-[var(--color-primary)] cursor-pointer" />
                                </label>

                                <label class="flex items-center justify-between text-xs font-medium text-[var(--text-primary)] cursor-pointer">
                                    <span>Speakers & Guests Grid</span>
                                    <input type="checkbox" wire:model.live="show_speakers" class="size-4 rounded-md text-[var(--color-primary)] cursor-pointer" />
                                </label>

                                <label class="flex items-center justify-between text-xs font-medium text-[var(--text-primary)] cursor-pointer">
                                    <span>Day-by-Day Schedule</span>
                                    <input type="checkbox" wire:model.live="show_schedule" class="size-4 rounded-md text-[var(--color-primary)] cursor-pointer" />
                                </label>

                                <label class="flex items-center justify-between text-xs font-medium text-[var(--text-primary)] cursor-pointer">
                                    <span>FAQ Accordion</span>
                                    <input type="checkbox" wire:model.live="show_faqs" class="size-4 rounded-md text-[var(--color-primary)] cursor-pointer" />
                                </label>

                                @if (!$isDedicatedMode)
                                    <div class="border-t border-[var(--border-hairline)] pt-3">
                                        <label class="flex items-start justify-between gap-3 text-xs font-medium text-[var(--text-primary)] cursor-pointer">
                                            <div>
                                                <span class="font-bold">Public Directory Listing</span>
                                                <p class="text-[10px] text-[var(--text-secondary)] font-normal mt-0.5">
                                                    When enabled, event is listed on public <code class="font-mono text-[10px]">/events</code>. When uncheck, event is private (direct link only).
                                                </p>
                                            </div>
                                            <input type="checkbox" wire:model="is_public" class="size-4 rounded-md text-[var(--color-primary)] cursor-pointer mt-0.5" />
                                        </label>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Live Landing Preview: mirrors the public hero as palette and copy change -->
                        <div class="lg:sticky lg:top-4 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-xs space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-mono uppercase tracking-wider text-[var(--text-tertiary)]">Live Preview</span>
                                <span wire:loading.delay class="text-[10px] text-[var(--text-tertiary)]">Updating…</span>
                            </div>
                            @php
                                $previewAccent = preg_match('/^#[0-9a-fA-F]{6}$/', $accent_hex) === 1 ? $accent_hex : '#18181b';
                                $previewBanner = \App\Support\MediaUrl::resolve($banner_path) ?? $currentEvent?->bannerUrl();
                                $previewStart = filled($start_at) ? rescue(static fn() => \Illuminate\Support\Carbon::parse($start_at), null, false) : null;
                            @endphp
                            <div class="relative overflow-hidden rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)]" style="--preview-accent: {{ $previewAccent }}">
                                @if ($previewBanner)
                                    <img src="{{ $previewBanner }}" alt="" class="absolute inset-0 size-full object-cover opacity-20" />
                                @endif
                                <div class="pointer-events-none absolute -right-10 -top-10 size-40 rounded-full opacity-25 blur-2xl" style="background-color: var(--preview-accent)"></div>
                                <div class="relative space-y-3 p-5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-2 py-0.5 text-[9px] font-semibold text-[var(--text-secondary)]">
                                        <span class="size-1 rounded-full" style="background-color: var(--preview-accent)"></span>
                                        {{ $currentEvent?->organization?->name ?? 'Your Organization' }} Presents
                                    </span>
                                    <h4 class="font-serif text-lg font-bold leading-tight text-[var(--text-primary)]">{{ $headline ?: ($title ?: 'Your headline') }}</h4>
                                    <p class="line-clamp-3 text-[11px] text-[var(--text-secondary)]">{{ $tagline ?: 'Your tagline appears here.' }}</p>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-lg px-3 py-1.5 text-[10px] font-bold text-white" style="background-color: var(--preview-accent)">Reserve Your Seat →</span>
                                        <span class="text-[10px] text-[var(--text-secondary)]">{{ $venue ?: 'Venue TBA' }}</span>
                                    </div>
                                    @if ($show_countdown && $previewStart)
                                        <div class="grid grid-cols-4 gap-1.5 pt-1 text-center">
                                            @foreach (['Days' => max(0, (int) now()->diffInDays($previewStart, false)), 'Hours' => '00', 'Mins' => '00', 'Secs' => '00'] as $unit => $value)
                                                <div class="rounded-md bg-[var(--bg-subtle)] py-1">
                                                    <div class="font-mono text-xs font-black">{{ is_int($value) ? str_pad((string) $value, 2, '0', STR_PAD_LEFT) : $value }}</div>
                                                    <div class="text-[8px] uppercase text-[var(--text-tertiary)]">{{ $unit }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="flex flex-wrap gap-1 border-t border-[var(--border-hairline)] bg-[var(--bg-surface)] px-5 py-2 text-[9px] font-semibold text-[var(--text-tertiary)]">
                                    @foreach (['Speakers' => $show_speakers, 'Schedule' => $show_schedule, 'FAQs' => $show_faqs] as $sectionName => $visible)
                                        <span @class(['rounded-full px-2 py-0.5', 'bg-[var(--bg-subtle)] text-[var(--text-primary)]' => $visible, 'line-through opacity-50' => !$visible])>{{ $sectionName }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="w-full px-5 py-3 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold hover:opacity-95 transition shadow-xs press-scale cursor-pointer"
                        >
                            Save Branding & Styles
                        </button>
                    </div>
                </div>
            </form>
        @endif

        <!-- Tab 2: Speakers & Guests -->
        @if ($activeTab === 'speakers')
            <div class="space-y-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-[var(--text-primary)]">Keynote Speakers & Special Guests</h3>
                        <p class="text-xs text-[var(--text-secondary)] mt-0.5">Manage photo rosters, talk topics, and roles.</p>
                    </div>

                    <button
                        type="button"
                        wire:click="openCreateSpeakerModal"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold hover:opacity-95 transition shadow-xs press-scale cursor-pointer"
                    >
                        + Add Speaker
                    </button>
                </div>

                @if ($speakers->isEmpty())
                    <div class="rounded-2xl border border-dashed border-[var(--border-hairline)] bg-[var(--bg-surface)] p-8 text-center">
                        <p class="text-xs text-[var(--text-secondary)]">No speakers added yet. Click above to introduce your keynote roster.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach ($speakers as $spk)
                            <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3 shadow-2xs flex items-start justify-between gap-2.5 subtle-hover-glow transition">
                                <div class="flex items-start gap-2.5">
                                    @if ($spk->photoUrl())
                                        <img src="{{ $spk->photoUrl() }}" alt="{{ $spk->name }}" class="size-9 rounded-lg object-cover border border-[var(--border-hairline)]" />
                                    @else
                                        <div class="size-9 rounded-lg bg-[var(--bg-subtle)] border border-[var(--border-hairline)] flex items-center justify-center font-bold text-[11px] text-[var(--text-primary)]">
                                            {{ substr($spk->name, 0, 2) }}
                                        </div>
                                    @endif

                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-1.5">
                                            <h4 class="text-xs font-bold text-[var(--text-primary)] leading-tight">{{ $spk->name }}</h4>
                                            @if ($spk->is_featured)
                                                <span class="px-1.5 py-0.2 rounded-sm bg-amber-500/10 text-amber-600 dark:text-amber-400 font-semibold text-[9px]">Featured</span>
                                            @endif
                                        </div>
                                        <p class="text-[10px] font-medium text-[var(--text-secondary)]">{{ $spk->title_role }}</p>
                                        @if ($spk->topic)
                                            <p class="text-[9px] text-[var(--text-tertiary)] italic line-clamp-1">"{{ $spk->topic }}"</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-1 shrink-0">
                                    <button
                                        type="button"
                                        wire:click="editSpeaker({{ $spk->id }})"
                                        class="p-1 rounded-md border border-[var(--border-hairline)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition cursor-pointer press-scale"
                                    >
                                        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="deleteSpeaker({{ $spk->id }})"
                                        wire:confirm="Remove speaker?"
                                        class="p-1 rounded-md border border-[var(--border-hairline)] text-[var(--text-secondary)] hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition cursor-pointer press-scale"
                                    >
                                        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <!-- Tab 3: Program Schedule -->
        @if ($activeTab === 'schedule')
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-[var(--text-primary)]">Day-by-Day Program Schedule</h3>
                        <p class="text-[11px] text-[var(--text-secondary)] mt-0.5">Sessions, timings, halls, and speakers.</p>
                    </div>

                    <button
                        type="button"
                        wire:click="openCreateScheduleModal"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold hover:opacity-95 transition shadow-2xs press-scale cursor-pointer"
                    >
                        + Add Session
                    </button>
                </div>

                @if ($schedules->isEmpty())
                    <div class="rounded-xl border border-dashed border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 text-center">
                        <p class="text-xs text-[var(--text-secondary)]">No program sessions added. Map out day-by-day itineraries.</p>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach ($schedules as $item)
                            <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 subtle-hover-glow transition">
                                <div class="flex items-start sm:items-center gap-2.5">
                                    <span class="px-2 py-0.5 rounded-md bg-[var(--bg-subtle)] text-[10px] font-mono font-bold text-[var(--text-secondary)] shrink-0">
                                        {{ $item->day_label }}
                                    </span>
                                    <div class="space-y-0.5">
                                        <h4 class="text-xs font-bold text-[var(--text-primary)]">{{ $item->session_title }}</h4>
                                        <div class="flex flex-wrap items-center gap-2 text-[10px] text-[var(--text-secondary)]">
                                            <span>⏱ {{ $item->start_time }} - {{ $item->end_time }}</span>
                                            @if ($item->venue_hall)
                                                <span>· 📍 {{ $item->venue_hall }}</span>
                                            @endif
                                            @if ($item->speaker_name)
                                                <span>· 🎙 {{ $item->speaker_name }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1 shrink-0 self-end sm:self-center">
                                    <button
                                        type="button"
                                        wire:click="editSchedule({{ $item->id }})"
                                        class="p-1 rounded-md border border-[var(--border-hairline)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition cursor-pointer press-scale"
                                    >
                                        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="deleteSchedule({{ $item->id }})"
                                        wire:confirm="Delete this session?"
                                        class="p-1 rounded-md border border-[var(--border-hairline)] text-[var(--text-secondary)] hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition cursor-pointer press-scale"
                                    >
                                        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <!-- Tab 4: Dynamic Questionnaire Builder -->
        @if ($activeTab === 'form_fields')
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-[var(--text-primary)]">Custom Registration Questionnaire</h3>
                        <p class="text-[11px] text-[var(--text-secondary)] mt-0.5">Capture custom data during checkout (e.g. T-Shirt Size, Bus Route).</p>
                    </div>

                    <button
                        type="button"
                        wire:click="openCreateFieldModal"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold hover:opacity-95 transition shadow-2xs press-scale cursor-pointer"
                    >
                        + Add Custom Field
                    </button>
                </div>

                @if ($formFields->isEmpty())
                    <div class="rounded-xl border border-dashed border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 text-center">
                        <p class="text-xs text-[var(--text-secondary)]">No custom fields yet. Patron checkout currently requires only Name, Email, and Phone.</p>
                    </div>
                @else
                    <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] overflow-hidden shadow-2xs">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[var(--text-secondary)] font-semibold text-[11px]">
                                <tr>
                                    <th class="px-3.5 py-2.5">Label</th>
                                    <th class="px-3.5 py-2.5">Field Key</th>
                                    <th class="px-3.5 py-2.5">Type</th>
                                    <th class="px-3.5 py-2.5">Required</th>
                                    <th class="px-3.5 py-2.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--border-hairline)] text-[11px]">
                                @foreach ($formFields as $f)
                                    <tr class="hover:bg-[var(--bg-subtle)]/50 transition">
                                        <td class="px-3.5 py-2 font-bold text-[var(--text-primary)]">{{ $f->label }}</td>
                                        <td class="px-3.5 py-2 font-mono text-[var(--text-secondary)]">{{ $f->field_key }}</td>
                                        <td class="px-3.5 py-2">
                                            <span class="px-1.5 py-0.5 rounded bg-[var(--bg-subtle)] border border-[var(--border-hairline)] font-medium text-[9px]">
                                                {{ strtoupper($f->input_type) }}
                                            </span>
                                        </td>
                                        <td class="px-3.5 py-2">
                                            <span class="font-medium {{ $f->is_required ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-[var(--text-tertiary)]' }}">
                                                {{ $f->is_required ? 'Yes' : 'Optional' }}
                                            </span>
                                        </td>
                                        <td class="px-3.5 py-2 text-right space-x-1">
                                            <button
                                                type="button"
                                                wire:click="editField({{ $f->id }})"
                                                class="p-1 rounded border border-[var(--border-hairline)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition cursor-pointer press-scale"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="deleteField({{ $f->id }})"
                                                wire:confirm="Delete field?"
                                                class="p-1 rounded border border-[var(--border-hairline)] text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition cursor-pointer press-scale"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        <!-- Tab 5: FAQs -->
        @if ($activeTab === 'faqs')
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-[var(--text-primary)]">Frequently Asked Questions</h3>
                        <p class="text-[11px] text-[var(--text-secondary)] mt-0.5">Provide answers regarding tickets, parking, and passes.</p>
                    </div>

                    <button
                        type="button"
                        wire:click="openCreateFaqModal"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold hover:opacity-95 transition shadow-2xs press-scale cursor-pointer"
                    >
                        + Add FAQ
                    </button>
                </div>

                @if ($faqs->isEmpty())
                    <div class="rounded-xl border border-dashed border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 text-center">
                        <p class="text-xs text-[var(--text-secondary)]">No FAQs added yet.</p>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach ($faqs as $faq)
                            <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3 shadow-2xs space-y-1.5 subtle-hover-glow transition">
                                <div class="flex items-start justify-between gap-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="px-1.5 py-0.5 rounded bg-[var(--bg-subtle)] text-[9px] font-semibold text-[var(--text-secondary)]">
                                            {{ $faq->category }}
                                        </span>
                                        <h4 class="text-xs font-bold text-[var(--text-primary)]">{{ $faq->question }}</h4>
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0">
                                        <button
                                            type="button"
                                            wire:click="editFaq({{ $faq->id }})"
                                            class="px-2 py-0.5 rounded text-[11px] border border-[var(--border-hairline)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition cursor-pointer press-scale"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="deleteFaq({{ $faq->id }})"
                                            wire:confirm="Delete FAQ?"
                                            class="px-2 py-0.5 rounded text-[11px] border border-[var(--border-hairline)] text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition cursor-pointer press-scale"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>
                                <p class="text-[11px] text-[var(--text-secondary)] leading-relaxed pl-1">{{ $faq->answer }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    @endif

    <!-- Speaker Modal -->
    @if ($isSpeakerModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-black/50 backdrop-blur-xs" @keydown.window.escape="$wire.set('isSpeakerModalOpen', false)">
            <div class="w-full max-w-md rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-2xl p-6 space-y-4 animate-physical-modal">
                <h3 class="text-sm font-bold text-[var(--text-primary)]">{{ $editingSpeakerId ? 'Edit Speaker' : 'Add New Speaker' }}</h3>
                <form wire:submit="saveSpeaker" class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Full Name *</label>
                        <input type="text" wire:model="speaker_name" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                        @error('speaker_name') <p class="text-xs text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Title / Organization</label>
                        <input type="text" wire:model="speaker_title_role" placeholder="e.g. Founder at TechStudio" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Topic / Keynote Subject</label>
                        <input type="text" wire:model="speaker_topic" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                    </div>
                    <div>
                        <label for="speaker_photo" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Photo <span class="font-normal text-[var(--text-tertiary)]">(JPG, PNG or WebP · max 2 MB)</span></label>
                        <div class="flex items-center gap-3">
                            <div class="size-14 shrink-0 overflow-hidden rounded-full border border-[var(--border-hairline)] bg-[var(--bg-subtle)]">
                                @if ($speaker_photo && in_array($speaker_photo->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true))
                                    <img src="{{ $speaker_photo->temporaryUrl() }}" alt="" class="size-full object-cover" />
                                @elseif (\App\Support\MediaUrl::resolve($speaker_photo_path))
                                    <img src="{{ \App\Support\MediaUrl::resolve($speaker_photo_path) }}" alt="" class="size-full object-cover" />
                                @endif
                            </div>
                            <input id="speaker_photo" type="file" accept="image/jpeg,image/png,image/webp" wire:model="speaker_photo" class="text-xs text-[var(--text-secondary)] file:mr-2 file:cursor-pointer file:rounded-lg file:border-0 file:bg-[var(--bg-subtle)] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[var(--text-primary)]" />
                        </div>
                        <div wire:loading wire:target="speaker_photo" class="mt-1 text-[11px] text-[var(--text-tertiary)]">Uploading…</div>
                        @error('speaker_photo') <p class="text-xs text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                        <input type="url" wire:model="speaker_photo_path" placeholder="…or paste an image URL" class="mt-2 w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Bio</label>
                        <textarea wire:model="speaker_bio" rows="2" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]"></textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="speaker_featured" wire:model="speaker_is_featured" class="size-4 rounded-md text-[var(--color-primary)]" />
                        <label for="speaker_featured" class="text-xs font-medium text-[var(--text-primary)] cursor-pointer">Highlight as Featured Speaker</label>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-[var(--border-hairline)]">
                        <button type="button" wire:click="$set('isSpeakerModalOpen', false)" class="px-3.5 py-1.5 rounded-xl border border-[var(--border-hairline)] text-xs text-[var(--text-secondary)]">Cancel</button>
                        <button type="submit" class="px-4 py-1.5 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold">Save Speaker</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Schedule Modal -->
    @if ($isScheduleModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-black/50 backdrop-blur-xs" @keydown.window.escape="$wire.set('isScheduleModalOpen', false)">
            <div class="w-full max-w-md rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-2xl p-6 space-y-4 animate-physical-modal">
                <h3 class="text-sm font-bold text-[var(--text-primary)]">{{ $editingScheduleId ? 'Edit Session' : 'Add Program Session' }}</h3>
                <form wire:submit="saveSchedule" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Day Label *</label>
                            <input type="text" wire:model="schedule_day_label" placeholder="e.g. Day 1, Saturday" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                            @error('schedule_day_label') <p class="text-xs text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Venue Hall</label>
                            <input type="text" wire:model="schedule_venue_hall" placeholder="Main Auditorium" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Session Title *</label>
                        <input type="text" wire:model="schedule_session_title" placeholder="e.g. Keynote Address" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                        @error('schedule_session_title') <p class="text-xs text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Assigned Speaker</label>
                        <input type="text" wire:model="schedule_speaker_name" placeholder="Speaker Name" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Start Time *</label>
                            <input type="text" wire:model="schedule_start_time" placeholder="09:00 AM" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">End Time</label>
                            <input type="text" wire:model="schedule_end_time" placeholder="10:30 AM" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-[var(--border-hairline)]">
                        <button type="button" wire:click="$set('isScheduleModalOpen', false)" class="px-3.5 py-1.5 rounded-xl border border-[var(--border-hairline)] text-xs text-[var(--text-secondary)]">Cancel</button>
                        <button type="submit" class="px-4 py-1.5 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold">Save Session</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Form Field Modal -->
    @if ($isFieldModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-black/50 backdrop-blur-xs" @keydown.window.escape="$wire.set('isFieldModalOpen', false)">
            <div class="w-full max-w-md rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-2xl p-6 space-y-4 animate-physical-modal">
                <h3 class="text-sm font-bold text-[var(--text-primary)]">{{ $editingFieldId ? 'Edit Custom Field' : 'Add Checkout Question' }}</h3>
                <form wire:submit="saveField" class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Question Label *</label>
                        <input type="text" wire:model.live="field_label" placeholder="e.g. Dietary Preferences" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                        @error('field_label') <p class="text-xs text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Field Identifier Key *</label>
                        <input type="text" wire:model="field_key" placeholder="dietary_preferences" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]" />
                        @error('field_key') <p class="text-xs text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Input Format *</label>
                        <select wire:model.live="field_input_type" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)] [color-scheme:light] dark:[color-scheme:dark]">
                            <option value="text">Single Line Text</option>
                            <option value="textarea">Paragraph / Multi-line</option>
                            <option value="select">Dropdown Selection</option>
                            <option value="radio">Radio Choice</option>
                            <option value="checkbox">Multi-Checkbox</option>
                        </select>
                    </div>

                    @if (in_array($field_input_type, ['select', 'radio', 'checkbox'], true))
                        <div>
                            <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Options (comma-separated) *</label>
                            <input type="text" wire:model="field_options_raw" placeholder="Option A, Option B, Option C" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Placeholder</label>
                        <input type="text" wire:model="field_placeholder" placeholder="Hint for attendee..." class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="field_req" wire:model="field_is_required" class="size-4 rounded-md text-[var(--color-primary)]" />
                        <label for="field_req" class="text-xs font-medium text-[var(--text-primary)] cursor-pointer">Required for checkout</label>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-[var(--border-hairline)]">
                        <button type="button" wire:click="$set('isFieldModalOpen', false)" class="px-3.5 py-1.5 rounded-xl border border-[var(--border-hairline)] text-xs text-[var(--text-secondary)]">Cancel</button>
                        <button type="submit" class="px-4 py-1.5 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold">Save Field</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- FAQ Modal -->
    @if ($isFaqModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-black/50 backdrop-blur-xs" @keydown.window.escape="$wire.set('isFaqModalOpen', false)">
            <div class="w-full max-w-md rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-2xl p-6 space-y-4 animate-physical-modal">
                <h3 class="text-sm font-bold text-[var(--text-primary)]">{{ $editingFaqId ? 'Edit FAQ Item' : 'Add FAQ Item' }}</h3>
                <form wire:submit="saveFaq" class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Category</label>
                        <input type="text" wire:model="faq_category" placeholder="General, Tickets, Venue" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Question *</label>
                        <input type="text" wire:model="faq_question" placeholder="e.g. Can I transfer my ticket?" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                        @error('faq_question') <p class="text-xs text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Answer *</label>
                        <textarea wire:model="faq_answer" rows="3" class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)]"></textarea>
                        @error('faq_answer') <p class="text-xs text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="faq_pub" wire:model="faq_is_published" class="size-4 rounded-md text-[var(--color-primary)]" />
                        <label for="faq_pub" class="text-xs font-medium text-[var(--text-primary)] cursor-pointer">Published and visible to public</label>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-[var(--border-hairline)]">
                        <button type="button" wire:click="$set('isFaqModalOpen', false)" class="px-3.5 py-1.5 rounded-xl border border-[var(--border-hairline)] text-xs text-[var(--text-secondary)]">Cancel</button>
                        <button type="submit" class="px-4 py-1.5 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold">Save FAQ</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
