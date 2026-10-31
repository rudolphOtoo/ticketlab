<div class="min-h-screen bg-[var(--bg-canvas)] text-[var(--text-primary)] pt-20 sm:pt-24 pb-12">
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
                        <img
                            src="{{ $event->bannerUrl() }}"
                            alt="{{ $event->title }}"
                            class="h-64 w-full object-cover sm:h-80"
                        />
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
                                    target: new Date('{{ $event->start_at->toIso8601String() }}').getTime(),
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

                <!-- Ticket Tiers Selection Section -->
                <div id="tickets" class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-md sm:p-8 space-y-5">
                    <div>
                        <h2 class="text-xl font-bold tracking-tight text-[var(--text-primary)]">Choose your ticket</h2>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($ticketTypes as $ticketType)
                            @php
                                $isSoldOut = (int) ($ticketType->available_quantity ?? 0) <= 0;
                                $isSelected = $ticketTypeId === $ticketType->id;
                            @endphp
                            <div
                                wire:key="ticket-{{ $ticketType->id }}"
                                tabindex="0"
                                wire:click="{{ $isSoldOut ? '' : "selectTicketType({$ticketType->id})" }}"
                                @keydown.enter="{{ $isSoldOut ? '' : "selectTicketType({$ticketType->id})" }}"
                                @class([
                                    'rounded-2xl border p-4 transition-all focus-ring press-scale',
                                    'opacity-50 cursor-not-allowed bg-[var(--bg-subtle)] border-[var(--border-hairline)]' => $isSoldOut,
                                    'cursor-pointer hover:border-[var(--text-secondary)]' => ! $isSoldOut,
                                    'border-[var(--border-focused)] bg-[var(--color-primary)] text-[var(--color-primary-fg)] shadow-md' => $isSelected,
                                    'border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-primary)]' => ! $isSelected,
                                ])
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-sm">{{ $ticketType->name }}</p>
                                        <p @class([
                                            'mt-1 text-xs',
                                            'text-[var(--color-primary-fg)]/80' => $isSelected,
                                            'text-[var(--text-secondary)]' => ! $isSelected,
                                        ])>
                                            {{ $ticketType->description ?: 'Standard admittance pass' }}
                                        </p>
                                    </div>
                                    <span @class([
                                        'rounded-full px-2.5 py-0.5 text-[10px] font-semibold',
                                        'bg-[var(--color-primary-fg)] text-[var(--color-primary)] font-bold' => $isSelected,
                                        $this->availabilityBadgeClass($ticketType) => ! $isSelected,
                                    ])>
                                        {{ $this->availabilityBadge($ticketType) }}
                                    </span>
                                </div>

                                <div class="mt-4 flex items-baseline justify-between">
                                    <span class="text-lg font-black">
                                        GHS {{ number_format((float) $ticketType->price, 2) }}
                                    </span>
                                    <span @class([
                                        'text-[11px]',
                                        'text-[var(--color-primary-fg)]/80' => $isSelected,
                                        'text-[var(--text-secondary)]' => ! $isSelected,
                                    ])>
                                        {{ $ticketType->available_quantity }} left
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @error('ticketTypeId')
                        <p class="text-xs font-semibold text-rose-500">{{ $message }}</p>
                    @enderror

                    @if ($subdivisions->isNotEmpty())
                        <div class="pt-4 border-t border-[var(--border-hairline)]">
                            <label for="landingSubdivision" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                                Select your {{ $organization->subdivision_label }}
                            </label>
                            <select
                                id="landingSubdivision"
                                wire:model.live="subdivisionId"
                                class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-input)] px-3 py-2 text-xs shadow-xs focus:border-[var(--border-focused)] focus:outline-hidden"
                            >
                                <option value="">No {{ strtolower($organization->subdivision_label) }} / general admission</option>
                                @foreach ($subdivisions as $subdivision)
                                    <option value="{{ $subdivision->id }}">{{ $subdivision->name }}</option>
                                @endforeach
                            </select>
                            @error('subdivisionId')
                                <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <!-- Quantity Controls -->
                    <div class="pt-4 border-t border-[var(--border-hairline)] flex items-center justify-between">
                        <div>
                            <span class="text-xs font-semibold text-[var(--text-primary)]">Ticket Quantity</span>
                            <p class="text-[11px] text-[var(--text-secondary)]">Maximum 20 passes per checkout</p>
                        </div>

                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                wire:click="decrementQuantity"
                                class="size-8 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-sm font-bold text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] flex items-center justify-center cursor-pointer transition press-scale"
                            >
                                -
                            </button>
                            <span class="font-bold text-sm min-w-6 text-center text-[var(--text-primary)] tabular-nums">{{ $quantity }}</span>
                            <button
                                type="button"
                                wire:click="incrementQuantity"
                                class="size-8 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-sm font-bold text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] flex items-center justify-center cursor-pointer transition press-scale"
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
                                                <img src="{{ $speaker->photoUrl() }}" alt="{{ $speaker->name }}" class="size-full object-cover">
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

                        <div x-data="{ tab: '{{ $scheduleDays->keys()->first() }}' }" class="space-y-4">
                            @if ($scheduleDays->count() > 1)
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($scheduleDays as $dayLabel => $daySessions)
                                        <button
                                            type="button"
                                            @click="tab = '{{ $dayLabel }}'"
                                            :class="tab === '{{ $dayLabel }}' ? 'bg-[var(--color-primary)] text-[var(--color-primary-fg)] font-semibold shadow-xs' : 'border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-secondary)] hover:text-[var(--text-primary)]'"
                                            class="rounded-full px-3 py-1 text-xs transition cursor-pointer press-scale"
                                        >
                                            {{ $dayLabel }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            <div class="space-y-2.5">
                                @foreach ($scheduleDays as $dayLabel => $daySessions)
                                    <div x-show="tab === '{{ $dayLabel }}'" class="space-y-2.5">
                                        @foreach ($daySessions as $session)
                                            <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)]/50 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                                <div class="flex items-start gap-3">
                                                    <div class="min-w-20 font-mono text-xs font-bold text-[var(--text-tertiary)] pt-0.5">
                                                        {{ $session->start_time ?? ($session->starts_at?->format('H:i') ?? 'TBA') }}
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

            <!-- Order Summary Sticky Card (Right Rail) -->
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-md space-y-5">
                    <p class="text-xs font-semibold text-[var(--text-tertiary)] uppercase tracking-wider">Order Summary</p>

                    @if ($selectedTicketType)
                        <div class="flex items-start justify-between gap-4 border-b border-[var(--border-hairline)] pb-4">
                            <div>
                                <p class="font-bold text-[var(--text-primary)]">{{ $selectedTicketType->name }}</p>
                                <p class="text-xs text-[var(--text-secondary)] mt-0.5">GHS {{ number_format((float) $selectedTicketType->price, 2) }} x {{ $quantity }}</p>
                            </div>
                            <p class="font-bold text-[var(--text-primary)]">GHS {{ number_format($subtotal, 2) }}</p>
                        </div>

                        <dl class="space-y-2 text-xs">
                            <div class="flex justify-between text-[var(--text-secondary)]">
                                <dt>Ticket Subtotal</dt>
                                <dd class="font-medium text-[var(--text-primary)]">GHS {{ number_format($breakdown['net'], 2) }}</dd>
                            </div>
                            @if ($breakdown['platform_fee'] > 0)
                                <div class="flex justify-between text-[var(--text-secondary)]">
                                    <dt>Platform Fee</dt>
                                    <dd class="font-medium text-[var(--text-primary)]">GHS {{ number_format($breakdown['platform_fee'], 2) }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between text-[var(--text-secondary)]">
                                <dt>Processing Fee</dt>
                                <dd class="font-medium text-[var(--text-primary)]">GHS {{ number_format($breakdown['processing_fee'], 2) }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-[var(--border-hairline)] pt-3 text-sm font-bold text-[var(--text-primary)]">
                                <dt>Total Payable</dt>
                                <dd class="text-base text-[var(--text-primary)]">GHS {{ number_format($breakdown['gross'], 2) }}</dd>
                            </div>
                        </dl>

                        <button
                            type="button"
                            wire:click="openCheckout"
                            class="w-full cursor-pointer rounded-xl bg-[var(--color-primary)] px-4 py-3 text-sm font-semibold text-[var(--color-primary-fg)] shadow-xs hover:opacity-90 transition press-scale text-center"
                        >
                            Proceed to Checkout →
                        </button>
                    @else
                        <div class="py-8 text-center text-[var(--text-tertiary)] text-xs">
                            Select a ticket tier to review checkout pricing.
                        </div>
                    @endif
                </div>
            </aside>
        </div>
    </div>

    <!-- Slide-over / Modal Checkout Drawer -->
    @if ($checkoutOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs overflow-y-auto">
            <div class="w-full max-w-lg rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-2xl sm:p-8 space-y-5 my-8 max-h-[90vh] overflow-y-auto animate-physical-modal">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-[var(--text-primary)]">Attendee Checkout</h3>
                        <p class="text-xs text-[var(--text-secondary)] mt-0.5">{{ $selectedTicketType?->name }} • {{ $quantity }} ticket(s)</p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeCheckout"
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

                @error('payment')
                    <div class="rounded-xl bg-[var(--status-danger-bg)] border border-[var(--status-danger)]/20 p-3 text-xs text-[var(--status-danger)] font-medium">
                        {{ $message }}
                    </div>
                @enderror

                <form wire:submit="startCheckout" class="space-y-4">
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
                        <div>
                            <label for="subdivisionId" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                                {{ $organization->subdivision_label ?: 'Campus / Branch' }}
                            </label>
                            <select
                                id="subdivisionId"
                                wire:model="subdivisionId"
                                class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-input)] px-3.5 py-2 text-xs shadow-xs focus:border-[var(--border-focused)] focus:outline-hidden"
                            >
                                <option value="">-- Select an option --</option>
                                @foreach ($subdivisions as $sub)
                                    <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                                @endforeach
                            </select>
                            @error('subdivisionId') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
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

                                        @if ($field->help_text)
                                            <p class="text-[11px] text-[var(--text-tertiary)] mb-1.5">{{ $field->help_text }}</p>
                                        @endif

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
                            <span>GHS {{ number_format($breakdown['gross'], 2) }}</span>
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full cursor-pointer rounded-xl bg-[var(--color-primary)] py-3 px-4 text-xs font-semibold text-[var(--color-primary-fg)] shadow-xs hover:opacity-90 transition press-scale"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove>Reserve Seats & Pay with Paystack →</span>
                        <span wire:loading>Holding seats & preparing Paystack...</span>
                    </button>
                </form>
            </div>
        </div>
    @endif

    <!-- Speaker Bio Modal Drawer -->
    @if ($viewingSpeaker)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
            <div class="w-full max-w-lg rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-2xl sm:p-8 space-y-5 animate-physical-modal">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="size-14 rounded-full bg-[var(--bg-subtle)] flex items-center justify-center font-bold text-lg text-[var(--text-secondary)] overflow-hidden shrink-0 border border-[var(--border-hairline)]">
                            @if ($viewingSpeaker->photoUrl())
                                <img src="{{ $viewingSpeaker->photoUrl() }}" alt="{{ $viewingSpeaker->name }}" class="size-full object-cover">
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

    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('open-paystack', (event) => {
                const payload = event?.payload ?? event?.detail?.payload ?? event;

                if (! payload || typeof PaystackPop === 'undefined') {
                    return;
                }

                const parameters = {
                    key: payload.key,
                    email: payload.email,
                    amount: payload.amount,
                    currency: payload.currency,
                    ref: payload.reference,
                    metadata: payload.metadata || {},
                    callback: function (response) {
                        Livewire.dispatch('payment-completed', { reference: response.reference });
                    },
                    onClose: function () {
                        Livewire.dispatch('payment-cancelled');
                    },
                };

                if (payload.subaccount) {
                    parameters.subaccount = payload.subaccount;
                }

                PaystackPop.setup(parameters).openIframe();
            });
        });
    </script>
</div>
