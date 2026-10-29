<div class="min-h-screen bg-zinc-50 py-10">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <header class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">{{ $organization->name }}</p>
                    <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-zinc-900 sm:text-4xl">
                        {{ $customization['headline'] ?? $event->title }}
                    </h1>
                    @if (!empty($customization['tagline']))
                        <p class="mt-2 text-base text-zinc-600 font-medium">
                            {{ $customization['tagline'] }}
                        </p>
                    @endif
                </div>

                <a href="#tickets" class="hidden sm:inline-flex items-center justify-center rounded-xl bg-zinc-900 px-5 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-zinc-800 transition press-scale">
                    Get Tickets
                </a>
            </div>
        </header>

        <div class="grid gap-8 lg:grid-cols-3">
            <section class="space-y-8 lg:col-span-2">
                <!-- Main Event Hero Card -->
                <div class="overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-sm">
                    @if ($event->bannerUrl())
                        <img
                            src="{{ $event->bannerUrl() }}"
                            alt="{{ $event->title }}"
                            class="h-64 w-full object-cover sm:h-80"
                        />
                    @else
                        <div class="flex h-56 w-full items-center justify-center bg-gradient-to-br from-zinc-900 via-zinc-800 to-zinc-950 p-6 text-center text-white">
                            <div>
                                <p class="text-xs uppercase tracking-[0.3em] text-amber-400 font-bold">{{ $organization->name }}</p>
                                <p class="mt-2 text-2xl sm:text-3xl font-black">{{ $customization['headline'] ?? $event->title }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="space-y-6 p-6 sm:p-8">
                        <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-600">
                            <span class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 font-medium">
                                <span class="size-2 rounded-full bg-emerald-500"></span>
                                {{ $event->start_at->format('D, j M Y') }}
                                @if (! $event->start_at->isSameDay($event->end_at))
                                    – {{ $event->end_at->format('D, j M Y') }}
                                @endif
                            </span>
                            @if ($event->venue)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 font-medium">
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
                                <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-3 shadow-2xs">
                                    <div class="text-2xl font-black text-zinc-900" x-text="String(days).padStart(2,'0')">00</div>
                                    <div class="text-[11px] font-semibold text-zinc-500 uppercase tracking-wider">Days</div>
                                </div>
                                <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-3 shadow-2xs">
                                    <div class="text-2xl font-black text-zinc-900" x-text="String(hours).padStart(2,'0')">00</div>
                                    <div class="text-[11px] font-semibold text-zinc-500 uppercase tracking-wider">Hours</div>
                                </div>
                                <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-3 shadow-2xs">
                                    <div class="text-2xl font-black text-zinc-900" x-text="String(minutes).padStart(2,'0')">00</div>
                                    <div class="text-[11px] font-semibold text-zinc-500 uppercase tracking-wider">Minutes</div>
                                </div>
                                <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-3 shadow-2xs">
                                    <div class="text-2xl font-black text-zinc-900" x-text="String(seconds).padStart(2,'0')">00</div>
                                    <div class="text-[11px] font-semibold text-zinc-500 uppercase tracking-wider">Seconds</div>
                                </div>
                            </div>
                        @endif

                        <div>
                            <h2 class="text-xl font-bold tracking-tight text-zinc-900">About this event</h2>
                            <p class="mt-2 whitespace-pre-line text-sm leading-7 text-zinc-600">
                                {{ $event->description ?: 'Join us for this special gathering. Select a ticket tier below to reserve your seat.' }}
                            </p>
                        </div>

                        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 text-xs text-zinc-600 space-y-1">
                            <p class="font-semibold text-zinc-900">{{ $event->start_at->format('l, j F Y') }}</p>
                            <p>{{ $event->start_at->format('g:i A') }} – {{ $event->end_at->format('g:i A') }}</p>
                            @if ($event->venue)
                                <p class="mt-1">📍 {{ $event->venue }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Ticket Tiers Selection Section -->
                <div id="tickets" class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 space-y-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Tier selection</p>
                        <h2 class="mt-1 text-xl font-bold tracking-tight text-zinc-900">Choose your ticket</h2>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($ticketTypes as $ticketType)
                            @php
                                $isSoldOut = (int) ($ticketType->available_quantity ?? 0) <= 0;
                                $isSelected = $ticketTypeId === $ticketType->id;
                            @endphp
                            <div
                                wire:key="ticket-{{ $ticketType->id }}"
                                wire:click="{{ $isSoldOut ? '' : "selectTicketType({$ticketType->id})" }}"
                                class="rounded-2xl border p-4 transition-all {{ $isSoldOut ? 'opacity-50 cursor-not-allowed bg-zinc-100 border-zinc-200' : 'cursor-pointer hover:border-zinc-400' }} {{ $isSelected ? 'border-zinc-900 bg-zinc-900 text-white shadow-md' : 'border-zinc-200 bg-white text-zinc-900' }}"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-sm">{{ $ticketType->name }}</p>
                                        <p class="mt-1 text-xs {{ $isSelected ? 'text-zinc-300' : 'text-zinc-500' }}">
                                            {{ $ticketType->description ?: 'Standard admittance pass' }}
                                        </p>
                                    </div>
                                    <span class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold {{ $isSelected ? 'bg-white/20 text-white' : $this->availabilityBadgeClass($ticketType) }}">
                                        {{ $this->availabilityBadge($ticketType) }}
                                    </span>
                                </div>

                                <div class="mt-4 flex items-baseline justify-between">
                                    <span class="text-lg font-black">
                                        GHS {{ number_format((float) $ticketType->price, 2) }}
                                    </span>
                                    <span class="text-[11px] {{ $isSelected ? 'text-zinc-300' : 'text-zinc-500' }}">
                                        {{ $ticketType->available_quantity }} left
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @error('ticketTypeId')
                        <p class="text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror

                    @if ($subdivisions->isNotEmpty())
                        <div class="pt-4 border-t border-zinc-100">
                            <label for="landingSubdivision" class="block text-xs font-semibold text-zinc-700 mb-1">
                                Select your {{ $organization->subdivision_label }}
                            </label>
                            <select
                                id="landingSubdivision"
                                wire:model.live="subdivisionId"
                                class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-xs shadow-xs focus:border-zinc-900 focus:outline-hidden"
                            >
                                <option value="">No {{ strtolower($organization->subdivision_label) }} / general admission</option>
                                @foreach ($subdivisions as $subdivision)
                                    <option value="{{ $subdivision->id }}">{{ $subdivision->name }}</option>
                                @endforeach
                            </select>
                            @error('subdivisionId')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <!-- Quantity Controls -->
                    <div class="pt-4 border-t border-zinc-100 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-semibold text-zinc-900">Ticket Quantity</span>
                            <p class="text-[11px] text-zinc-500">Maximum 20 passes per checkout</p>
                        </div>

                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                wire:click="decrementQuantity"
                                class="size-8 rounded-lg border border-zinc-200 bg-white text-sm font-bold text-zinc-700 hover:bg-zinc-50 flex items-center justify-center cursor-pointer transition press-scale"
                            >
                                -
                            </button>
                            <span class="font-bold text-sm min-w-6 text-center text-zinc-900">{{ $quantity }}</span>
                            <button
                                type="button"
                                wire:click="incrementQuantity"
                                class="size-8 rounded-lg border border-zinc-200 bg-white text-sm font-bold text-zinc-700 hover:bg-zinc-50 flex items-center justify-center cursor-pointer transition press-scale"
                            >
                                +
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Speakers & Guests Grid -->
                @if ($speakers->isNotEmpty())
                    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 space-y-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Distinguished guests</p>
                                <h2 class="mt-1 text-xl font-bold tracking-tight text-zinc-900">Featured Speakers</h2>
                            </div>
                            <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-semibold text-zinc-700">
                                {{ $speakers->count() }} Profiles
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach ($speakers as $speaker)
                                <div class="rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 flex flex-col justify-between space-y-3">
                                    <div class="flex items-start gap-3">
                                        <div class="size-12 rounded-full bg-zinc-200 flex items-center justify-center font-bold text-sm text-zinc-700 shrink-0 overflow-hidden">
                                            @if ($speaker->photoUrl())
                                                <img src="{{ $speaker->photoUrl() }}" alt="{{ $speaker->name }}" class="size-full object-cover">
                                            @else
                                                {{ substr($speaker->name, 0, 1) }}
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h3 class="text-xs font-bold text-zinc-900 truncate">{{ $speaker->name }}</h3>
                                            <p class="text-[11px] text-zinc-600 truncate">{{ $speaker->title ?? $speaker->title_role }}</p>
                                        </div>
                                    </div>

                                    @if ($speaker->topic)
                                        <div class="rounded-lg bg-amber-500/10 border border-amber-500/20 px-2.5 py-1 text-[11px] font-medium text-amber-800">
                                            Topic: {{ $speaker->topic }}
                                        </div>
                                    @endif

                                    @if ($speaker->bio)
                                        <button
                                            type="button"
                                            wire:click="viewSpeaker({{ $speaker->id }})"
                                            class="text-[11px] font-semibold text-zinc-700 hover:text-zinc-900 text-left cursor-pointer press-scale"
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
                    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 space-y-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Program outline</p>
                            <h2 class="mt-1 text-xl font-bold tracking-tight text-zinc-900">Program Schedule</h2>
                        </div>

                        <div x-data="{ tab: '{{ $scheduleDays->keys()->first() }}' }" class="space-y-4">
                            @if ($scheduleDays->count() > 1)
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($scheduleDays as $dayLabel => $daySessions)
                                        <button
                                            type="button"
                                            @click="tab = '{{ $dayLabel }}'"
                                            :class="tab === '{{ $dayLabel }}' ? 'bg-zinc-900 text-white' : 'border border-zinc-200 bg-white text-zinc-600'"
                                            class="rounded-full px-3 py-1 text-xs font-semibold transition cursor-pointer"
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
                                            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                                <div class="flex items-start gap-3">
                                                    <div class="min-w-20 font-mono text-xs font-bold text-zinc-500 pt-0.5">
                                                        {{ $session->start_time ?? ($session->starts_at?->format('H:i') ?? 'TBA') }}
                                                    </div>
                                                    <div>
                                                        <h4 class="text-xs sm:text-sm font-bold text-zinc-900">
                                                            {{ $session->session_title ?? $session->title }}
                                                        </h4>
                                                        <p class="text-[11px] text-zinc-600 mt-0.5">
                                                            @if ($session->speaker_name)
                                                                <span class="text-amber-800 font-medium">Speaker: {{ $session->speaker_name }}</span>
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
                    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 space-y-5">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Clarifications & Help</p>
                                <h2 class="mt-1 text-xl font-bold tracking-tight text-zinc-900">FAQs</h2>
                            </div>
                            <div class="w-full sm:w-64">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="faqSearch"
                                    placeholder="Search FAQs..."
                                    class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-xs text-zinc-900 focus:border-zinc-900 focus:outline-hidden"
                                />
                            </div>
                        </div>

                        <div class="space-y-3">
                            @foreach ($faqs as $faq)
                                <details class="group rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 cursor-pointer">
                                    <summary class="text-xs sm:text-sm font-bold text-zinc-900 flex items-center justify-between list-none">
                                        <span>{{ $faq->question }}</span>
                                        <span class="text-xs text-zinc-400 group-open:rotate-180 transition-transform">▼</span>
                                    </summary>
                                    <p class="text-xs text-zinc-600 mt-3 leading-relaxed border-t border-zinc-200 pt-3">
                                        {{ $faq->answer }}
                                    </p>
                                </details>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Additional Event Information Overview -->
                @if ($formFields->isNotEmpty())
                    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Attendee requirements</p>
                            <h2 class="mt-1 text-xl font-bold tracking-tight text-zinc-900">Event Information</h2>
                        </div>
                        <div class="grid gap-2.5 sm:grid-cols-2">
                            @foreach ($formFields as $field)
                                <div class="rounded-2xl border border-zinc-200 bg-zinc-50/50 p-3.5">
                                    <p class="text-xs font-bold text-zinc-900">
                                        {{ $field->label }}
                                        @if ($field->is_required)
                                            <span class="text-rose-500">*</span>
                                        @endif
                                    </p>
                                    @if ($field->placeholder)
                                        <p class="text-[11px] text-zinc-500 mt-0.5">{{ $field->placeholder }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>

            <!-- Order Summary Sticky Card (Right Rail) -->
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm space-y-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Order Summary</p>

                    @if ($selectedTicketType)
                        <div class="flex items-start justify-between gap-4 border-b border-zinc-100 pb-4">
                            <div>
                                <p class="font-bold text-zinc-900">{{ $selectedTicketType->name }}</p>
                                <p class="text-xs text-zinc-500 mt-0.5">GHS {{ number_format((float) $selectedTicketType->price, 2) }} x {{ $quantity }}</p>
                            </div>
                            <p class="font-bold text-zinc-900">GHS {{ number_format($subtotal, 2) }}</p>
                        </div>

                        <dl class="space-y-2 text-xs">
                            <div class="flex justify-between text-zinc-600">
                                <dt>Ticket Subtotal</dt>
                                <dd class="font-medium">GHS {{ number_format($breakdown['net'], 2) }}</dd>
                            </div>
                            @if ($breakdown['platform_fee'] > 0)
                                <div class="flex justify-between text-zinc-600">
                                    <dt>Platform Fee</dt>
                                    <dd class="font-medium">GHS {{ number_format($breakdown['platform_fee'], 2) }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between text-zinc-600">
                                <dt>Processing Fee</dt>
                                <dd class="font-medium">GHS {{ number_format($breakdown['processing_fee'], 2) }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-zinc-200 pt-3 text-sm font-bold text-zinc-900">
                                <dt>Total Payable</dt>
                                <dd class="text-base text-zinc-900">GHS {{ number_format($breakdown['gross'], 2) }}</dd>
                            </div>
                        </dl>

                        <button
                            type="button"
                            wire:click="openCheckout"
                            class="w-full cursor-pointer rounded-xl bg-zinc-900 px-4 py-3 text-sm font-semibold text-white shadow-xs hover:bg-zinc-800 transition press-scale text-center"
                        >
                            Proceed to Checkout →
                        </button>
                    @else
                        <div class="py-8 text-center text-zinc-400 text-xs">
                            Select a ticket tier to review checkout pricing.
                        </div>
                    @endif
                </div>
            </aside>
        </div>
    </div>

    <!-- Slide-over / Modal Checkout Drawer -->
    @if ($checkoutOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/60 p-4 backdrop-blur-xs overflow-y-auto">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl sm:p-8 space-y-5 my-8 max-h-[90vh] overflow-y-auto">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-zinc-900">Attendee Checkout</h3>
                        <p class="text-xs text-zinc-500 mt-0.5">{{ $selectedTicketType?->name }} • {{ $quantity }} ticket(s)</p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeCheckout"
                        class="size-8 rounded-full border border-zinc-200 text-zinc-500 hover:text-zinc-900 flex items-center justify-center transition cursor-pointer press-scale"
                    >
                        ✕
                    </button>
                </div>

                @if ($flashMessage)
                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3 text-xs text-emerald-800">
                        {{ $flashMessage }}
                    </div>
                @endif

                @error('payment')
                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 text-xs text-rose-800">
                        {{ $message }}
                    </div>
                @enderror

                <form wire:submit="startCheckout" class="space-y-4">
                    <div>
                        <label for="customerName" class="block text-xs font-semibold text-zinc-700 mb-1">Full Name</label>
                        <input
                            id="customerName"
                            type="text"
                            wire:model="customerName"
                            class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-xs shadow-xs focus:border-zinc-900 focus:outline-hidden"
                            placeholder="Kwame Mensah"
                        />
                        @error('customerName') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="customerEmail" class="block text-xs font-semibold text-zinc-700 mb-1">Email Address</label>
                            <input
                                id="customerEmail"
                                type="email"
                                wire:model="customerEmail"
                                class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-xs shadow-xs focus:border-zinc-900 focus:outline-hidden"
                                placeholder="kwame@example.com"
                            />
                            @error('customerEmail') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="customerPhone" class="block text-xs font-semibold text-zinc-700 mb-1">Mobile Phone (MoMo)</label>
                            <input
                                id="customerPhone"
                                type="tel"
                                wire:model="customerPhone"
                                class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-xs shadow-xs focus:border-zinc-900 focus:outline-hidden"
                                placeholder="0244123456"
                            />
                            @error('customerPhone') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if ($subdivisions->isNotEmpty())
                        <div>
                            <label for="subdivisionId" class="block text-xs font-semibold text-zinc-700 mb-1">
                                {{ $organization->subdivision_label ?: 'Campus / Branch' }}
                            </label>
                            <select
                                id="subdivisionId"
                                wire:model="subdivisionId"
                                class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-xs shadow-xs focus:border-zinc-900 focus:outline-hidden"
                            >
                                <option value="">-- Select an option --</option>
                                @foreach ($subdivisions as $sub)
                                    <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                                @endforeach
                            </select>
                            @error('subdivisionId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <!-- Dynamic Attendee Questionnaire Injection -->
                    @if ($formFields->isNotEmpty())
                        <div class="border-t border-zinc-200 pt-4 space-y-4">
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-600">Additional Information</h4>
                                <p class="text-[11px] text-zinc-500 mt-0.5">Please provide the details requested by the organizer.</p>
                            </div>

                            <div class="space-y-3.5">
                                @foreach ($formFields as $field)
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-700 mb-1">
                                            {{ $field->label }}
                                            @if ($field->is_required)
                                                <span class="text-rose-500">*</span>
                                            @endif
                                        </label>

                                        @if ($field->help_text)
                                            <p class="text-[11px] text-zinc-500 mb-1.5">{{ $field->help_text }}</p>
                                        @endif

                                        @if ($field->field_type === 'textarea')
                                            <textarea
                                                wire:model="customFieldResponses.{{ $field->field_key }}"
                                                rows="2"
                                                placeholder="{{ $field->placeholder ?: 'Type your response here...' }}"
                                                class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-xs shadow-xs focus:border-zinc-900 focus:outline-hidden"
                                            ></textarea>
                                        @elseif ($field->field_type === 'select')
                                            <select
                                                wire:model="customFieldResponses.{{ $field->field_key }}"
                                                class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-xs shadow-xs focus:border-zinc-900 focus:outline-hidden"
                                            >
                                                <option value="">{{ $field->placeholder ?: '-- Select an option --' }}</option>
                                                @foreach ($field->options ?? [] as $opt)
                                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($field->field_type === 'radio')
                                            <div class="space-y-1.5 pt-0.5">
                                                @foreach ($field->options ?? [] as $opt)
                                                    <label class="flex items-center gap-2 text-xs text-zinc-700 cursor-pointer">
                                                        <input
                                                            type="radio"
                                                            wire:model="customFieldResponses.{{ $field->field_key }}"
                                                            value="{{ $opt }}"
                                                            class="text-zinc-900 focus:ring-zinc-900"
                                                        />
                                                        <span>{{ $opt }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif ($field->field_type === 'checkbox')
                                            <label class="flex items-start gap-2 text-xs text-zinc-700 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    wire:model="customFieldResponses.{{ $field->field_key }}"
                                                    class="size-4 rounded text-zinc-900 focus:ring-zinc-900 mt-0.5 cursor-pointer"
                                                />
                                                <span>{{ $field->placeholder ?: 'Yes, I confirm / accept.' }}</span>
                                            </label>
                                        @else
                                            <input
                                                type="text"
                                                wire:model="customFieldResponses.{{ $field->field_key }}"
                                                placeholder="{{ $field->placeholder ?: 'Type your response...' }}"
                                                class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-xs shadow-xs focus:border-zinc-900 focus:outline-hidden"
                                            />
                                        @endif

                                        @error('customFieldResponses.' . $field->field_key)
                                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                        @enderror
                                        @error('formResponses.' . $field->field_key)
                                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="rounded-2xl bg-zinc-50 p-4 border border-zinc-200">
                        <div class="flex justify-between items-center text-sm font-bold text-zinc-900">
                            <span>Total Payable:</span>
                            <span>GHS {{ number_format($breakdown['gross'], 2) }}</span>
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full cursor-pointer rounded-xl bg-zinc-900 py-3 px-4 text-xs font-semibold text-white shadow-xs hover:bg-zinc-800 transition press-scale"
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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/60 p-4 backdrop-blur-xs">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl sm:p-8 space-y-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="size-14 rounded-full bg-zinc-100 flex items-center justify-center font-bold text-lg text-zinc-700 overflow-hidden shrink-0">
                            @if ($viewingSpeaker->photoUrl())
                                <img src="{{ $viewingSpeaker->photoUrl() }}" alt="{{ $viewingSpeaker->name }}" class="size-full object-cover">
                            @else
                                {{ substr($viewingSpeaker->name, 0, 1) }}
                            @endif
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-zinc-900">{{ $viewingSpeaker->name }}</h3>
                            <p class="text-xs text-zinc-600">{{ $viewingSpeaker->title ?? $viewingSpeaker->title_role }}</p>
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click="closeSpeakerModal"
                        class="size-8 rounded-full border border-zinc-200 text-zinc-500 hover:text-zinc-900 flex items-center justify-center transition press-scale cursor-pointer"
                    >
                        ✕
                    </button>
                </div>

                @if ($viewingSpeaker->topic)
                    <div class="rounded-xl bg-amber-500/10 border border-amber-500/20 p-3 text-xs font-semibold text-amber-800">
                        Featured Session Topic: {{ $viewingSpeaker->topic }}
                    </div>
                @endif

                @if ($viewingSpeaker->bio)
                    <div class="border-t border-zinc-100 pt-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-500 mb-2">Biography</h4>
                        <p class="text-xs sm:text-sm text-zinc-600 leading-relaxed whitespace-pre-line">
                            {{ $viewingSpeaker->bio }}
                        </p>
                    </div>
                @endif

                <div class="pt-2 text-right">
                    <button
                        type="button"
                        wire:click="closeSpeakerModal"
                        class="px-4 py-2 rounded-xl bg-zinc-900 text-white text-xs font-semibold hover:bg-zinc-800 transition press-scale cursor-pointer"
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
