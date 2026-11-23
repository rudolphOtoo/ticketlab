<div x-data="ticketCheckout" @checkout-refreshed.window="refresh($event.detail.config)" class="min-h-screen bg-[var(--bg-canvas)] text-[var(--text-primary)] pt-20 sm:pt-24 pb-12">
    {{-- Read once when Alpine starts. Kept out of x-data so a Livewire re-render never rebuilds the buyer's state. --}}
    <script type="application/json" data-checkout-config>@json($checkout)</script>
    <x-bespoke-navbar :organization="$organization" :event="$event" :collapsible="true" :is-event-page="true" />

    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <header class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-[var(--text-secondary)]">{{ $organization->name }}</p>
                    <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-[var(--text-primary)] sm:text-4xl">
                        {{ $customization['headline'] ?? $event->title }}
                    </h1>
                    @if (!empty($customization['tagline']))
                        <p class="mt-2 text-base text-[var(--text-secondary)] font-medium">
                            {{ $customization['tagline'] }}
                        </p>
                    @endif
                </div>

                <a href="#tickets" class="hidden sm:inline-flex items-center justify-center rounded-xl bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-[var(--color-primary-fg)] shadow-xs hover:opacity-90 transition press-scale">
                    Get Tickets
                </a>
            </div>
        </header>

        <div class="grid gap-8 lg:grid-cols-3">
            <section class="space-y-8 lg:col-span-2">
                <!-- Main Event Hero Card -->
                <div class="overflow-hidden rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-md">
                    @if ($event->bannerUrl())
                        <div class="relative">
                            <x-media-image
                                :value="$event->banner_path"
                                preset="hero"
                                :alt="$event->title"
                                sizes="(min-width: 1024px) 720px, 100vw"
                                :eager="true"
                                class="h-64 w-full object-cover sm:h-80"
                                onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';"
                            />
                            <div class="hidden h-56 w-full items-center justify-center bg-gradient-to-br from-[var(--bg-subtle)] via-[var(--bg-surface)] to-[var(--bg-canvas)] border-b border-[var(--border-hairline)] p-6 text-center text-[var(--text-primary)]">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.2em] text-[var(--accent-brand)] font-bold">{{ $organization->name }}</p>
                                    <p class="mt-2 text-2xl sm:text-3xl font-black text-[var(--text-primary)]">{{ $customization['headline'] ?? $event->title }}</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex h-56 w-full items-center justify-center bg-gradient-to-br from-[var(--bg-subtle)] via-[var(--bg-surface)] to-[var(--bg-canvas)] border-b border-[var(--border-hairline)] p-6 text-center text-[var(--text-primary)]">
                            <div>
                                <p class="text-xs uppercase tracking-[0.2em] text-[var(--accent-brand)] font-bold">{{ $organization->name }}</p>
                                <p class="mt-2 text-2xl sm:text-3xl font-black text-[var(--text-primary)]">{{ $customization['headline'] ?? $event->title }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="space-y-6 p-6 sm:p-8">
                        <div class="flex flex-wrap items-center gap-3 text-sm text-[var(--text-secondary)]">
                            <span class="inline-flex items-center gap-2 rounded-full border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3 py-1 font-medium">
                                <span class="size-2 rounded-full bg-emerald-500"></span>
                                {{ $event->start_at->format('D, j M Y') }}
                                @if (! $event->start_at->isSameDay($event->end_at))
                                    – {{ $event->end_at->format('D, j M Y') }}
                                @endif
                            </span>
                            @if ($event->venue)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3 py-1 font-medium">
                                    📍 {{ $event->venue }}
                                </span>
                            @endif
                        </div>

                        <!-- Countdown Timer -->
                        @if ($event->start_at)
                            <div
                                x-data="{
                                    target: new Date(@js($event->start_at->toIso8601String())).getTime(),
                                    now: Date.now(),
                                    days: 0, hours: 0, minutes: 0, seconds: 0,
                                    isPast: false,
                                    init() {
                                        this.update();
                                        setInterval(() => { this.now = Date.now(); this.update(); }, 1000);
                                    },
                                    update() {
                                        const diff = this.target - this.now;
                                        if (diff <= 0) {
                                            this.isPast = true;
                                            return;
                                        }
                                        this.days = Math.floor(diff / (1000 * 60 * 60 * 24));
                                        this.hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                        this.minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                                        this.seconds = Math.floor((diff % (1000 * 60)) / 1000);
                                    }
                                }"
                                x-show="!isPast"
                                class="grid grid-cols-4 gap-3 text-center"
                            >
                                <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] p-3 shadow-xs">
                                    <div class="font-mono text-2xl font-black text-[var(--text-primary)] tabular-nums" x-text="String(days).padStart(2,'0')">00</div>
                                    <div class="text-[11px] font-semibold text-[var(--text-tertiary)] uppercase tracking-wider">Days</div>
                                </div>
                                <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] p-3 shadow-xs">
                                    <div class="font-mono text-2xl font-black text-[var(--text-primary)] tabular-nums" x-text="String(hours).padStart(2,'0')">00</div>
                                    <div class="text-[11px] font-semibold text-[var(--text-tertiary)] uppercase tracking-wider">Hours</div>
                                </div>
                                <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] p-3 shadow-xs">
                                    <div class="font-mono text-2xl font-black text-[var(--text-primary)] tabular-nums" x-text="String(minutes).padStart(2,'0')">00</div>
                                    <div class="text-[11px] font-semibold text-[var(--text-tertiary)] uppercase tracking-wider">Minutes</div>
                                </div>
                                <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] p-3 shadow-xs">
                                    <div class="font-mono text-2xl font-black text-[var(--text-primary)] tabular-nums" x-text="String(seconds).padStart(2,'0')">00</div>
                                    <div class="text-[11px] font-semibold text-[var(--text-tertiary)] uppercase tracking-wider">Seconds</div>
                                </div>
                            </div>
                        @endif

                        <div>
                            <h2 class="text-xl font-bold tracking-tight text-[var(--text-primary)]">About this event</h2>
                            <p class="mt-2 whitespace-pre-line text-sm leading-7 text-[var(--text-secondary)]">
                                {{ $event->description ?: 'Join us for this special gathering. Select a ticket tier below to reserve your seat.' }}
                            </p>
                        </div>

                        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] p-4 text-xs text-[var(--text-secondary)] space-y-1">
                            <p class="font-semibold text-[var(--text-primary)]">{{ $event->start_at->format('l, j F Y') }}</p>
                            <p>{{ $event->start_at->format('g:i A') }} – {{ $event->end_at->format('g:i A') }}</p>
                            @if ($event->venue)
                                <p class="mt-1">📍 {{ $event->venue }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Ticket Tiers Selection Section: chosen in the browser, no server round trip per tap -->
                <div id="tickets" class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-md sm:p-8 space-y-5">
                    <fieldset>
                        <legend class="text-xl font-bold tracking-tight text-[var(--text-primary)]">Choose your ticket</legend>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            @foreach ($checkout['tiers'] as $tier)
                                <label
                                    wire:key="ticket-{{ $tier['id'] }}"
                                    data-ticket="{{ $tier['id'] }}"
                                    class="group block rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 text-[var(--text-primary)] transition-colors cursor-pointer hover:border-[var(--text-secondary)] has-checked:border-[var(--border-focused)] has-checked:bg-[var(--color-primary)] has-checked:text-[var(--color-primary-fg)] has-checked:shadow-md has-disabled:cursor-not-allowed has-disabled:opacity-50 has-disabled:bg-[var(--bg-subtle)] has-focus-visible:ring-2 has-focus-visible:ring-[var(--border-focused)] has-focus-visible:ring-offset-2"
                                >
                                    <input
                                        type="radio"
                                        name="ticket_type"
                                        value="{{ $tier['id'] }}"
                                        class="sr-only"
                                        x-model.number="ticketId"
                                        :disabled="! selectable(tier({{ $tier['id'] }}))"
                                        @checked($tier['id'] === $ticketTypeId)
                                    />
                                    @php
                                        $ticketType = $ticketTypes->firstWhere('id', $tier['id']);
                                        $seats = $tier['seats'][$subdivisionId ?? 0] ?? $tier['seats'][0];
                                    @endphp
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-bold text-sm">{{ $tier['name'] }}</p>
                                            <p class="mt-1 text-xs text-[var(--text-secondary)] group-has-checked:text-[var(--color-primary-fg)]/80">
                                                {{ $ticketType?->description ?: 'Standard admittance pass' }}
                                            </p>
                                        </div>
                                        <span
                                            data-badge
                                            :data-tone="badgeTone(tier({{ $tier['id'] }}))"
                                            x-text="badge(tier({{ $tier['id'] }}))"
                                            class="shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-semibold bg-emerald-100 text-emerald-800 data-[tone=limited]:bg-amber-100 data-[tone=limited]:text-amber-800 data-[tone=closed]:bg-zinc-200 data-[tone=closed]:text-zinc-600 group-has-checked:bg-[var(--color-primary-fg)] group-has-checked:text-[var(--color-primary)] group-has-checked:font-bold"
                                        >{{ $tier['status'] ?? ($seats <= 0 ? 'Sold Out' : ($seats <= $tier['limitedAt'] ? 'Limited' : 'Available')) }}</span>
                                    </div>

                                    <div class="mt-4 flex items-baseline justify-between">
                                        <span class="text-lg font-black">GHS {{ number_format($tier['price'] / 100, 2) }}</span>
                                        <span class="text-[11px] text-[var(--text-secondary)] group-has-checked:text-[var(--color-primary-fg)]/80">
                                            <span data-seats-left x-text="seatsLeft(tier({{ $tier['id'] }}))">{{ $seats }}</span> left
                                        </span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    @if ($subdivisions->isNotEmpty())
                        <div class="pt-4 border-t border-[var(--border-hairline)]">
                        <fieldset data-zone-venues>
                            <legend class="text-xs font-semibold text-[var(--text-secondary)]">
                                Select your {{ $organization->subdivision_label }}
                            </legend>

                            <ul class="mt-2 grid gap-2">
                                @foreach ($subdivisions as $zone)
                                    @php $zv = $zoneVenues[$zone->id]; @endphp
                                    <li wire:key="zone-{{ $zone->id }}">
                                        <label
                                            data-zone="{{ $zone->id }}"
                                            class="group/zone block w-full cursor-pointer rounded-xl border border-[var(--border-hairline)] px-3 py-2 text-left text-xs transition-colors hover:bg-[var(--bg-subtle)] has-checked:border-[var(--border-focused)] has-checked:bg-[var(--bg-subtle)] has-checked:ring-1 has-checked:ring-[var(--border-focused)] has-focus-visible:ring-2 has-focus-visible:ring-[var(--border-focused)]"
                                        >
                                            <input
                                                type="radio"
                                                name="zone"
                                                id="zone-{{ $zone->id }}"
                                                value="{{ $zone->id }}"
                                                class="sr-only"
                                                x-model.number="zoneId"
                                                @checked((int) $subdivisionId === $zone->id)
                                            />
                                            <span class="flex items-center justify-between gap-2">
                                                <span class="block font-semibold text-[var(--text-primary)]">{{ $zone->name }}</span>
                                                <span aria-hidden="true" class="size-3.5 rounded-full border border-[var(--border-hairline)] group-has-checked/zone:border-4 group-has-checked/zone:border-[var(--border-focused)]"></span>
                                            </span>
                                            <span class="block text-[var(--text-secondary)]">{{ $zv->when() }}</span>
                                            @if ($zv->place())
                                                <span class="block text-[var(--text-secondary)]">📍 {{ $zv->place() }}</span>
                                            @endif
                                        </label>
                                        @if ($zv->mapUrl)
                                            <a href="{{ $zv->mapUrl }}" target="_blank" rel="noopener noreferrer" class="mt-0.5 inline-block pl-3 text-[11px] font-semibold underline text-[var(--text-secondary)]">Open map</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            @error('subdivisionId')
                                <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                            @enderror
                        </fieldset>
                        </div>
                    @endif

                    <p x-show="choiceError" x-text="choiceError" x-cloak role="alert" class="text-xs font-semibold text-rose-500"></p>
                    @error('ticketTypeId')
                        <p class="text-xs font-semibold text-rose-500">{{ $message }}</p>
                    @enderror

                    <!-- Quantity Controls -->
                    <div class="pt-4 border-t border-[var(--border-hairline)] flex items-center justify-between">
                        <div>
                            <span id="quantity-label" class="text-xs font-semibold text-[var(--text-primary)]">Ticket Quantity</span>
                            <p class="text-[11px] text-[var(--text-secondary)]">Maximum {{ $checkout['maxPerOrder'] }} passes per checkout</p>
                        </div>

                        <div class="flex items-center gap-3" role="group" aria-labelledby="quantity-label">
                            <button
                                type="button"
                                @click="decrement()"
                                :disabled="quantity <= 1"
                                aria-label="Fewer tickets"
                                class="size-8 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-sm font-bold text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] flex items-center justify-center cursor-pointer transition press-scale disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                -
                            </button>
                            <span data-quantity aria-live="polite" class="font-bold text-sm min-w-6 text-center text-[var(--text-primary)] tabular-nums" x-text="quantity">{{ $quantity }}</span>
                            <button
                                type="button"
                                @click="increment()"
                                :disabled="quantity >= maxQuantity"
                                aria-label="More tickets"
                                class="size-8 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-sm font-bold text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] flex items-center justify-center cursor-pointer transition press-scale disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                +
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Speakers & Guests Grid -->
                @if ($speakers->isNotEmpty())
                    <div class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-md sm:p-8 space-y-6">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-bold tracking-tight text-[var(--text-primary)]">Featured Speakers</h2>
                            <span class="rounded-full bg-[var(--bg-subtle)] px-3 py-1 text-xs font-semibold text-[var(--text-secondary)]">
                                {{ $speakers->count() }} Profiles
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach ($speakers as $speaker)
                                <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)]/50 p-4 flex flex-col justify-between space-y-3">
                                    <div class="flex items-start gap-3">
                                        <div class="size-12 rounded-full bg-[var(--bg-subtle)] flex items-center justify-center font-bold text-sm text-[var(--text-secondary)] shrink-0 overflow-hidden border border-[var(--border-hairline)]">
                                            @if ($speaker->photoUrl())
                                                <x-media-image :value="$speaker->photo_path" preset="logo" sizes="48px" :alt="$speaker->name" class="size-full object-cover" />
                                            @else
                                                {{ substr($speaker->name, 0, 1) }}
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h3 class="text-xs font-bold text-[var(--text-primary)] truncate">{{ $speaker->name }}</h3>
                                            <p class="text-[11px] text-[var(--text-secondary)] truncate">{{ $speaker->title ?? $speaker->title_role }}</p>
                                        </div>
                                    </div>

                                    @if ($speaker->topic)
                                        <div class="rounded-lg bg-[var(--status-warning-bg)] border border-[var(--status-warning)]/20 px-2.5 py-1 text-[11px] font-medium text-[var(--status-warning)]">
                                            Topic: {{ $speaker->topic }}
                                        </div>
                                    @endif

                                    @if ($speaker->bio)
                                        <button
                                            type="button"
                                            wire:click="viewSpeaker({{ $speaker->id }})"
                                            class="text-[11px] font-semibold text-[var(--text-secondary)] hover:text-[var(--text-primary)] text-left cursor-pointer press-scale"
                                        >
                                            Read Full Bio →
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Program Schedule Timeline -->
                @if ($schedules->isNotEmpty())
                    <div class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-md sm:p-8 space-y-5">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-bold tracking-tight text-[var(--text-primary)]">Program Schedule</h2>
                        </div>

                        <div x-data="{ tab: @js($scheduleDays->keys()->first()) }" class="space-y-4">
                            @if ($scheduleDays->count() > 1)
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($scheduleDays as $dayLabel => $daySessions)
                                        <button
                                            type="button"
                                            @click="tab = @js($dayLabel)"
                                            :class="tab === @js($dayLabel) ? 'bg-[var(--color-primary)] text-[var(--color-primary-fg)] font-semibold shadow-xs' : 'border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-secondary)] hover:text-[var(--text-primary)]'"
                                            class="rounded-full px-3 py-1 text-xs transition cursor-pointer press-scale"
                                        >
                                            {{ $dayLabel }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            <div class="space-y-2.5">
                                @foreach ($scheduleDays as $dayLabel => $daySessions)
                                    <div x-show="tab === @js($dayLabel)" class="space-y-2.5">
                                        @foreach ($daySessions as $session)
                                            <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)]/50 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                                <div class="flex items-start gap-3">
                                                    <div class="min-w-20 font-mono text-xs font-bold text-[var(--text-tertiary)] pt-0.5">
                                                        {{ $session->start_time ?? 'TBA' }}
                                                    </div>
                                                    <div>
                                                        <h4 class="text-xs sm:text-sm font-bold text-[var(--text-primary)]">
                                                            {{ $session->session_title ?? $session->title }}
                                                        </h4>
                                                        <p class="text-[11px] text-[var(--text-secondary)] mt-0.5">
                                                            @if ($session->speaker_name)
                                                                <span class="text-[var(--status-warning)] font-medium">Speaker: {{ $session->speaker_name }}</span>
                                                            @endif
                                                            @if ($session->venue_hall ?? $session->location)
                                                                <span>• {{ $session->venue_hall ?? $session->location }}</span>
                                                            @endif
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Frequently Asked Questions (FAQs) Accordion -->
                @if ($faqs->isNotEmpty())
                    <div class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-md sm:p-8 space-y-5">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <h2 class="text-xl font-bold tracking-tight text-[var(--text-primary)]">Frequently Asked Questions</h2>
                            </div>
                            <div class="w-full sm:w-64">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="faqSearch"
                                    placeholder="Search FAQs..."
                                    class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-input)] px-3 py-1.5 text-xs text-[var(--text-primary)] placeholder-[var(--text-tertiary)] focus:border-[var(--border-focused)] focus:outline-hidden"
                                />
                            </div>
                        </div>

                        <div class="space-y-3">
                            @foreach ($faqs as $faq)
                                <details class="group rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)]/50 p-4 cursor-pointer focus-ring">
                                    <summary class="text-xs sm:text-sm font-bold text-[var(--text-primary)] flex items-center justify-between list-none">
                                        <span>{{ $faq->question }}</span>
                                        <span class="text-xs text-[var(--text-tertiary)] group-open:rotate-180 transition-transform duration-200 ease-out">▼</span>
                                    </summary>
                                    <p class="text-xs text-[var(--text-secondary)] mt-3 leading-relaxed border-t border-[var(--border-hairline)] pt-3">
                                        {{ $faq->answer }}
                                    </p>
                                </details>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Additional Event Information Overview -->
                @if ($formFields->isNotEmpty())
                    <div class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-md sm:p-8 space-y-4">
                        <div>
                            <h2 class="text-xl font-bold tracking-tight text-[var(--text-primary)]">Event Information</h2>
                        </div>
                        <div class="grid gap-2.5 sm:grid-cols-2">
                            @foreach ($formFields as $field)
                                <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)]/50 p-3.5">
                                    <p class="text-xs font-bold text-[var(--text-primary)]">
                                        {{ $field->label }}
                                        @if ($field->is_required)
                                            <span class="text-rose-500">*</span>
                                        @endif
                                    </p>
                                    @if ($field->placeholder)
                                        <p class="text-[11px] text-[var(--text-secondary)] mt-0.5">{{ $field->placeholder }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>

            <!-- Order Summary Sticky Card (Right Rail): worked out in the browser from the same fee formula as the server -->
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-md space-y-5" data-order-summary>
                    <p class="text-xs font-semibold text-[var(--text-tertiary)] uppercase tracking-wider">Order Summary</p>

                    <div x-show="ticket" @if ($initialTicket === null) x-cloak @endif class="space-y-5">
                        <div class="flex items-start justify-between gap-4 border-b border-[var(--border-hairline)] pb-4">
                            <div>
                                <p class="font-bold text-[var(--text-primary)]" x-text="ticket?.name">{{ $initialTicket['name'] ?? '' }}</p>
                                <p class="text-xs text-[var(--text-secondary)] mt-0.5">
                                    <span x-text="ticket ? money(ticket.price) : ''">GHS {{ number_format(($initialTicket['price'] ?? 0) / 100, 2) }}</span>
                                    x <span x-text="quantity">{{ $quantity }}</span>
                                </p>
                            </div>
                            <p class="font-bold text-[var(--text-primary)]" x-text="money(totals.net)">GHS {{ number_format($initialTotals['net'], 2) }}</p>
                        </div>

                        @if ($subdivisions->isNotEmpty())
                            <div class="text-xs" data-selected-zone>
                                <p class="font-semibold text-[var(--text-secondary)]">{{ $organization->subdivision_label }}</p>
                                <template x-if="zone">
                                    <div>
                                        <p class="font-bold text-[var(--text-primary)]" data-zone-name x-text="zone.name"></p>
                                        <p class="text-[var(--text-secondary)]" data-zone-when x-text="zone.when"></p>
                                        <p class="text-[var(--text-secondary)]" data-zone-place x-show="zone.place" x-text="'📍 ' + zone.place"></p>
                                    </div>
                                </template>
                                <p x-show="! zone" class="text-[var(--text-tertiary)]">Choose your {{ strtolower($organization->subdivision_label) }}</p>
                            </div>
                        @endif

                        <dl class="space-y-2 text-xs">
                            <div class="flex justify-between text-[var(--text-secondary)]">
                                <dt>Ticket Subtotal</dt>
                                <dd class="font-medium text-[var(--text-primary)]" x-text="money(totals.net)">GHS {{ number_format($initialTotals['net'], 2) }}</dd>
                            </div>
                            @if ($checkout['fee']['platformFee'] > 0)
                                <div class="flex justify-between text-[var(--text-secondary)]">
                                    <dt>Platform Fee</dt>
                                    <dd class="font-medium text-[var(--text-primary)]" x-text="money(totals.platformFee)">GHS {{ number_format($initialTotals['platform_fee'], 2) }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between text-[var(--text-secondary)]">
                                <dt>Processing Fee</dt>
                                <dd class="font-medium text-[var(--text-primary)]" data-processing-fee x-text="money(totals.processingFee)">GHS {{ number_format($initialTotals['processing_fee'], 2) }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-[var(--border-hairline)] pt-3 text-sm font-bold text-[var(--text-primary)]">
                                <dt>Total Payable</dt>
                                <dd class="text-base text-[var(--text-primary)]" data-total x-text="money(totals.gross)">GHS {{ number_format($initialTotals['gross'], 2) }}</dd>
                            </div>
                        </dl>

                        <button
                            type="button"
                            @click="proceed()"
                            data-proceed
                            class="w-full cursor-pointer rounded-xl bg-[var(--color-primary)] px-4 py-3 text-sm font-semibold text-[var(--color-primary-fg)] shadow-xs hover:opacity-90 transition press-scale text-center"
                        >
                            Proceed to Checkout →
                        </button>
                    </div>

                    <div x-show="! ticket" @if ($initialTicket !== null) x-cloak @endif class="py-8 text-center text-[var(--text-tertiary)] text-xs">
                        Select a ticket tier to review checkout pricing.
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <!-- Checkout details: always in the page and opened by Alpine, so "Proceed" needs no round trip -->
    <div
        x-show="open"
        x-cloak
        @keydown.escape.window="open = false"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs overflow-y-auto"
        data-checkout-modal
    >
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="checkout-title"
            @click.outside="if (! paying) open = false"
            x-trap.noscroll="open"
            class="w-full max-w-lg rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-2xl sm:p-8 space-y-5 my-8 max-h-[90vh] overflow-y-auto animate-physical-modal"
        >
                <div class="flex items-start justify-between">
                    <div>
                        <h3 id="checkout-title" class="text-lg font-bold text-[var(--text-primary)]">Attendee Checkout</h3>
                        <p class="text-xs text-[var(--text-secondary)] mt-0.5">
                            <span x-text="ticket?.name"></span> • <span x-text="quantity"></span> ticket(s)
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="open = false"
                        class="size-8 rounded-full border border-[var(--border-hairline)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] flex items-center justify-center transition cursor-pointer press-scale"
                        aria-label="Close checkout"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                @if ($flashMessage)
                    <div class="rounded-xl bg-[var(--status-success-bg)] border border-[var(--status-success)]/20 p-3 text-xs text-[var(--status-success)] font-medium">
                        {{ $flashMessage }}
                    </div>
                @endif

                <p x-show="choiceError && open" x-text="choiceError" x-cloak role="alert" class="rounded-xl bg-[var(--status-danger-bg)] border border-[var(--status-danger)]/20 p-3 text-xs text-[var(--status-danger)] font-medium"></p>

                {{-- Problems with the order itself (price changed, sold out, zone not payable) from the server's re-check. --}}
                @foreach (['payment', 'clientTotalPesewas', 'ticketTypeId', 'quantity', 'subdivisionId'] as $orderError)
                    @error($orderError)
                        <div role="alert" data-order-error class="rounded-xl bg-[var(--status-danger-bg)] border border-[var(--status-danger)]/20 p-3 text-xs text-[var(--status-danger)] font-medium">
                            {{ $message }}
                        </div>
                    @enderror
                @endforeach

                <form @submit.prevent="pay()" class="space-y-4">
                    <div>
                        <label for="customerName" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Full Name</label>
                        <input
                            id="customerName"
                            type="text"
                            wire:model="customerName"
                            class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-input)] px-3.5 py-2 text-xs shadow-xs focus:border-[var(--border-focused)] focus:outline-hidden"
                            placeholder="Kwame Mensah"
                        />
                        @error('customerName') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="customerEmail" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Email Address</label>
                            <input
                                id="customerEmail"
                                type="email"
                                wire:model="customerEmail"
                                class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-input)] px-3.5 py-2 text-xs shadow-xs focus:border-[var(--border-focused)] focus:outline-hidden"
                                placeholder="kwame@example.com"
                            />
                            @error('customerEmail') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="customerPhone" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">Mobile Phone (MoMo)</label>
                            <input
                                id="customerPhone"
                                type="tel"
                                wire:model="customerPhone"
                                class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-input)] px-3.5 py-2 text-xs shadow-xs focus:border-[var(--border-focused)] focus:outline-hidden"
                                placeholder="0244123456"
                            />
                            @error('customerPhone') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if ($subdivisions->isNotEmpty())
                        <div class="rounded-xl border border-[var(--border-hairline)] px-3.5 py-2 text-xs" data-chosen-zone-venue>
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-[var(--text-secondary)]">{{ $organization->subdivision_label }}</span>
                                <button type="button" @click="open = false; $nextTick(() => document.querySelector('[data-zone-venues] input:checked, [data-zone-venues] input')?.focus())" class="text-[11px] font-semibold underline text-[var(--text-secondary)] cursor-pointer">Change</button>
                            </div>
                            <p class="font-bold text-[var(--text-primary)]" x-text="zone?.name"></p>
                            <p class="text-[11px] text-[var(--text-secondary)]">
                                <span x-text="zone?.when"></span><span x-show="zone?.place" x-text="' · 📍 ' + zone?.place"></span>
                            </p>
                        </div>
                    @endif

                    <!-- Dynamic Attendee Questionnaire Injection -->
                    @if ($formFields->isNotEmpty())
                        <div class="border-t border-[var(--border-hairline)] pt-4 space-y-4">
                            <div>
                                <h4 class="text-xs font-bold text-[var(--text-primary)]">Additional Information</h4>
                                <p class="text-[11px] text-[var(--text-secondary)] mt-0.5">Please provide the details requested by the organizer.</p>
                            </div>

                            <div class="space-y-3.5">
                                @foreach ($formFields as $field)
                                    <div>
                                        <label class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                                            {{ $field->label }}
                                            @if ($field->is_required)
                                                <span class="text-rose-500">*</span>
                                            @endif
                                        </label>

                                        @if ($field->field_type === 'textarea')
                                            <textarea
                                                wire:model="customFieldResponses.{{ $field->field_key }}"
                                                rows="2"
                                                placeholder="{{ $field->placeholder ?: 'Type your response here...' }}"
                                                class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-input)] px-3.5 py-2 text-xs shadow-xs focus:border-[var(--border-focused)] focus:outline-hidden"
                                            ></textarea>
                                        @elseif ($field->field_type === 'select')
                                            <select
                                                wire:model="customFieldResponses.{{ $field->field_key }}"
                                                class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-input)] px-3.5 py-2 text-xs shadow-xs focus:border-[var(--border-focused)] focus:outline-hidden"
                                            >
                                                <option value="">{{ $field->placeholder ?: '-- Select an option --' }}</option>
                                                @foreach ($field->options ?? [] as $opt)
                                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($field->field_type === 'radio')
                                            <div class="space-y-1.5 pt-0.5">
                                                @foreach ($field->options ?? [] as $opt)
                                                    <label class="flex items-center gap-2 text-xs text-[var(--text-secondary)] cursor-pointer">
                                                        <input
                                                             type="radio"
                                                            wire:model="customFieldResponses.{{ $field->field_key }}"
                                                            value="{{ $opt }}"
                                                            class="text-[var(--color-primary)] focus:ring-[var(--border-focused)]"
                                                        />
                                                        <span>{{ $opt }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif ($field->field_type === 'checkbox')
                                            <label class="flex items-start gap-2 text-xs text-[var(--text-secondary)] cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    wire:model="customFieldResponses.{{ $field->field_key }}"
                                                    class="size-4 rounded text-[var(--color-primary)] focus:ring-[var(--border-focused)] mt-0.5 cursor-pointer"
                                                />
                                                <span>{{ $field->placeholder ?: 'Yes, I confirm / accept.' }}</span>
                                            </label>
                                        @else
                                            <input
                                                type="text"
                                                wire:model="customFieldResponses.{{ $field->field_key }}"
                                                placeholder="{{ $field->placeholder ?: 'Type your response...' }}"
                                                class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-input)] px-3.5 py-2 text-xs shadow-xs focus:border-[var(--border-focused)] focus:outline-hidden"
                                            />
                                        @endif

                                        @error('customFieldResponses.' . $field->field_key)
                                            <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                                        @enderror
                                        @error('formResponses.' . $field->field_key)
                                            <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="rounded-2xl bg-[var(--bg-subtle)] p-4 border border-[var(--border-hairline)]">
                        <div class="flex justify-between items-center text-sm font-bold text-[var(--text-primary)]">
                            <span>Total Payable:</span>
                            <span>
                                <span x-show="refreshed" x-cloak class="mr-1 rounded-full bg-[var(--status-warning-bg)] px-2 py-0.5 text-[10px] font-semibold text-[var(--status-warning)]">Updated</span>
                                <span data-modal-total x-text="money(totals.gross)"></span>
                            </span>
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full cursor-pointer rounded-xl bg-[var(--color-primary)] py-3 px-4 text-xs font-semibold text-[var(--color-primary-fg)] shadow-xs hover:opacity-90 transition press-scale disabled:cursor-wait disabled:opacity-70"
                        :disabled="paying"
                        :aria-busy="paying"
                        data-pay
                    >
                        <span x-show="! paying">Reserve Seats & Pay with Paystack →</span>
                        <span x-show="paying" x-cloak>Holding seats & preparing Paystack...</span>
                    </button>
                </form>
        </div>
    </div>

    <!-- Speaker Bio Modal Drawer -->
    @if ($viewingSpeaker)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
            <div class="w-full max-w-lg rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-2xl sm:p-8 space-y-5 animate-physical-modal">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="size-14 rounded-full bg-[var(--bg-subtle)] flex items-center justify-center font-bold text-lg text-[var(--text-secondary)] overflow-hidden shrink-0 border border-[var(--border-hairline)]">
                            @if ($viewingSpeaker->photoUrl())
                                <x-media-image :value="$viewingSpeaker->photo_path" preset="logo" sizes="56px" :alt="$viewingSpeaker->name" class="size-full object-cover" />
                            @else
                                {{ substr($viewingSpeaker->name, 0, 1) }}
                            @endif
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-[var(--text-primary)]">{{ $viewingSpeaker->name }}</h3>
                            <p class="text-xs text-[var(--text-secondary)]">{{ $viewingSpeaker->title ?? $viewingSpeaker->title_role }}</p>
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click="closeSpeakerModal"
                        class="size-8 rounded-full border border-[var(--border-hairline)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] flex items-center justify-center transition press-scale cursor-pointer"
                        aria-label="Close speaker profile"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                @if ($viewingSpeaker->topic)
                    <div class="rounded-xl bg-[var(--status-warning-bg)] border border-[var(--status-warning)]/20 p-3 text-xs font-semibold text-[var(--status-warning)]">
                        Featured Session Topic: {{ $viewingSpeaker->topic }}
                    </div>
                @endif

                @if ($viewingSpeaker->bio)
                    <div class="border-t border-[var(--border-hairline)] pt-3">
                        <h4 class="text-xs font-bold text-[var(--text-primary)] mb-2">Biography</h4>
                        <p class="text-xs sm:text-sm text-[var(--text-secondary)] leading-relaxed whitespace-pre-line">
                            {{ $viewingSpeaker->bio }}
                        </p>
                    </div>
                @endif

                <div class="pt-2 text-right">
                    <button
                        type="button"
                        wire:click="closeSpeakerModal"
                        class="px-4 py-2 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold hover:opacity-90 transition press-scale cursor-pointer"
                    >
                        Close Profile
                    </button>
                </div>
            </div>
        </div>
    @endif

    <script src="https://js.paystack.co/v2/inline.js"></script>
    <script>
        document.addEventListener('livewire:init', () => {
            // The transaction (amount, zone payout split) is opened on the server; the popup only resumes it.
            Livewire.on('open-paystack', (event) => {
                const accessCode = event?.accessCode ?? event?.detail?.accessCode;

                if (! accessCode || typeof PaystackPop === 'undefined') {
                    return;
                }

                new PaystackPop().resumeTransaction(accessCode, {
                    onSuccess: (transaction) => Livewire.dispatch('payment-completed', { reference: transaction.reference }),
                    onCancel: () => Livewire.dispatch('payment-cancelled'),
                    onError: () => Livewire.dispatch('payment-cancelled'),
                });
            });
        });
    </script>
</div>
