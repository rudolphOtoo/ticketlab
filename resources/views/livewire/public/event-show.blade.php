<div class="min-h-screen bg-[var(--bg-canvas)] text-[var(--text-primary)]">
    <!-- Floating Island Nav Bar -->
    <x-floating-nav :showBack="true" :current="$event->title" />

    <header class="border-b border-[var(--border-hairline)] bg-[var(--bg-surface)] pt-16 sm:pt-20">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                @if ($organization->logo_url)
                    <img src="{{ $organization->logo_url }}" alt="{{ $organization->name }}" class="h-10 w-10 rounded-full object-cover">
                @else
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--bg-subtle)] border border-[var(--border-hairline)] text-sm font-semibold text-[var(--text-primary)]">
                        {{ \Illuminate\Support\Str::of($organization->name)->explode(' ')->map(fn ($word) => \Illuminate\Support\Str::substr($word, 0, 1))->take(2)->implode('') }}
                    </div>
                @endif
                <div>
                    <p class="text-xs font-medium uppercase tracking-[0.2em] text-[var(--text-tertiary)]">Official tickets</p>
                    <p class="text-sm font-semibold text-[var(--text-primary)]">{{ $organization->name }}</p>
                </div>
            </div>

            <span class="hidden rounded-full border border-[var(--border-hairline)] px-3 py-1 text-xs font-medium text-[var(--text-secondary)] sm:inline-flex">
                Secure checkout by Paystack
            </span>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_26rem]">
            <section class="space-y-6">
                <div class="overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-sm">
                    @if ($event->banner_path)
                        <img src="{{ asset('storage/'.$event->banner_path) }}" alt="{{ $event->title }}" class="h-64 w-full object-cover sm:h-80">
                    @else
                        <div class="flex h-64 w-full items-end bg-gradient-to-br from-zinc-900 via-zinc-800 to-emerald-900 p-8 sm:h-80">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-[0.25em] text-emerald-200">You are invited</p>
                                <h1 class="mt-3 max-w-xl text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                                    {{ $customization['headline'] ?? $event->title }}
                                </h1>
                                @if (!empty($customization['tagline']))
                                    <p class="mt-2 text-xs sm:text-sm text-zinc-300 max-w-lg">{{ $customization['tagline'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="space-y-5 p-6 sm:p-8">
                        <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-zinc-600">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-2 rounded-full bg-zinc-100 px-3 py-1">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                    {{ $event->start_at->format('D, j M Y') }}
                                    @if (! $event->start_at->isSameDay($event->end_at))
                                        – {{ $event->end_at->format('D, j M Y') }}
                                    @endif
                                </span>
                                @if ($event->venue)
                                    <span class="inline-flex items-center gap-2 rounded-full bg-zinc-100 px-3 py-1">{{ $event->venue }}</span>
                                @endif
                            </div>

                            @if (($customization['show_countdown'] ?? true) && $event->start_at)
                                <div
                                    x-data="{
                                        target: new Date('{{ $event->start_at->toIso8601String() }}').getTime(),
                                        now: new Date().getTime(),
                                        days: 0, hours: 0, minutes: 0, seconds: 0,
                                        isPast: false,
                                        init() {
                                            this.calc();
                                            setInterval(() => this.calc(), 1000);
                                        },
                                        calc() {
                                            this.now = new Date().getTime();
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
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-xs font-mono font-bold text-amber-800 dark:text-amber-200"
                                >
                                    <span class="size-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                    <span>Starts: </span>
                                    <span x-text="days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's'"></span>
                                </div>
                            @endif
                        </div>

                        <div>
                            <h2 class="text-xl font-semibold tracking-tight text-zinc-900">About this event</h2>
                            <p class="mt-2 whitespace-pre-line text-sm leading-7 text-zinc-600">{{ $event->description ?: 'Join us for this special gathering. Select a ticket tier below to reserve your seat.' }}</p>
                        </div>

                        <div class="rounded-2xl bg-zinc-50 p-4 text-sm text-zinc-600">
                            <p class="font-medium text-zinc-900">{{ $event->start_at->format('l, j F Y') }}</p>
                            <p>{{ $event->start_at->format('g:i A') }} – {{ $event->end_at->format('g:i A') }}</p>
                            @if ($event->venue)
                                <p class="mt-1">{{ $event->venue }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold tracking-tight text-zinc-900">Choose your ticket</h2>
                            <p class="mt-1 text-sm text-zinc-600">Inventory updates live. Your seats are held for 15 minutes at checkout.</p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-3">
                        @forelse ($ticketTypes as $ticketType)
                            @php($soldOut = $ticketType->available_quantity <= 0)
                            <button
                                type="button"
                                wire:key="ticket-type-{{ $ticketType->id }}"
                                wire:click="selectTicketType({{ $ticketType->id }})"
                                @disabled($soldOut)
                                class="w-full rounded-2xl border p-4 text-left transition {{ $ticketTypeId === $ticketType->id ? 'border-zinc-900 bg-zinc-900 text-white shadow-lg' : 'border-zinc-200 bg-white text-zinc-900 hover:border-zinc-400' }} {{ $soldOut ? 'cursor-not-allowed opacity-60' : '' }}"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-base font-semibold">{{ $ticketType->name }}</p>
                                        @if ($ticketType->description)
                                            <p class="mt-1 text-sm {{ $ticketTypeId === $ticketType->id ? 'text-zinc-300' : 'text-zinc-500' }}">{{ $ticketType->description }}</p>
                                        @endif
                                        <p class="mt-2 text-xs {{ $ticketTypeId === $ticketType->id ? 'text-zinc-400' : 'text-zinc-500' }}">
                                            {{ $ticketType->available_quantity }} of {{ $ticketType->total_capacity }} remaining
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-semibold">GHS {{ number_format((float) $ticketType->price, 2) }}</p>
                                        <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $this->availabilityBadgeClass($ticketType) }}">
                                            {{ $this->availabilityBadge($ticketType) }}
                                        </span>
                                    </div>
                                </div>
                            </button>
                        @empty
                            <p class="rounded-2xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500">
                                Ticket tiers have not been published for this event yet.
                            </p>
                        @endforelse
                    </div>

                    @error('ticketTypeId')
                        <p class="mt-3 text-sm font-medium text-rose-600">{{ $message }}</p>
                    @enderror

                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="subdivision" class="block text-sm font-medium text-[var(--text-secondary)]">
                                Select your {{ $organization->subdivision_label }}
                            </label>
                            <div class="relative mt-2">
                                <select
                                    id="subdivision"
                                    wire:model.live="subdivisionId"
                                    class="w-full appearance-none rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3.5 py-2.5 pr-10 text-sm font-medium text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden focus:ring-1 focus:ring-[var(--text-primary)] transition cursor-pointer [color-scheme:light] dark:[color-scheme:dark]"
                                >
                                    <option value="" class="bg-white text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">
                                        No {{ strtolower($organization->subdivision_label) }} / general admission
                                    </option>
                                    @foreach ($subdivisions as $subdivision)
                                        <option value="{{ $subdivision->id }}" class="bg-white text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">
                                            {{ $subdivision->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-[var(--text-secondary)]">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>
                            @error('subdivisionId')
                                <p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <span class="block text-sm font-medium text-[var(--text-secondary)]">Quantity</span>
                            <div class="mt-2 flex items-center gap-3">
                                <button
                                    type="button"
                                    wire:click="decrementQuantity"
                                    class="flex h-10 w-10 items-center justify-center rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-lg font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] disabled:opacity-40 transition-colors press-scale cursor-pointer"
                                    @disabled($quantity <= 1)
                                >
                                    &minus;
                                </button>
                                <input
                                    type="number"
                                    min="1"
                                    max="20"
                                    wire:model.live.debounce.250ms="quantity"
                                    class="w-20 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-2 text-center text-sm font-bold font-mono text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden focus:ring-1 focus:ring-[var(--text-primary)]"
                                >
                                <button
                                    type="button"
                                    wire:click="incrementQuantity"
                                    class="flex h-10 w-10 items-center justify-center rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-lg font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition-colors press-scale cursor-pointer"
                                >
                                    +
                                </button>
                            </div>
                            @error('quantity')
                                <p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Speakers & Guests Grid -->
                @if (($customization['show_speakers'] ?? true) && $speakers->isNotEmpty())
                    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 space-y-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-[0.2em] text-zinc-500">Distinguished guests</p>
                                <h2 class="mt-1 text-xl font-semibold tracking-tight text-zinc-900">Featured Speakers</h2>
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
                                            @if ($speaker->photo_path)
                                                <img src="{{ $speaker->photo_path }}" alt="{{ $speaker->name }}" class="size-full object-cover">
                                            @else
                                                {{ substr($speaker->name, 0, 1) }}
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h3 class="text-xs font-bold text-zinc-900 truncate">{{ $speaker->name }}</h3>
                                            <p class="text-[11px] text-zinc-600 truncate">{{ $speaker->title }}</p>
                                            @if ($speaker->organization)
                                                <p class="text-[10px] text-zinc-500 truncate">{{ $speaker->organization }}</p>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($speaker->topic)
                                        <div class="rounded-lg bg-amber-500/10 border border-amber-500/20 px-2.5 py-1 text-[11px] font-medium text-amber-800 dark:text-amber-300">
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
                @if (($customization['show_schedule'] ?? true) && $schedules->isNotEmpty())
                    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 space-y-5">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-[0.2em] text-zinc-500">Program outline</p>
                            <h2 class="mt-1 text-xl font-semibold tracking-tight text-zinc-900">Event Schedule</h2>
                        </div>

                        <div class="space-y-3">
                            @foreach ($schedules as $session)
                                <div class="rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div class="flex items-start gap-3">
                                        <div class="min-w-20 font-mono text-xs font-bold text-zinc-500 pt-0.5">
                                            {{ $session->starts_at?->format('H:i') ?? 'TBA' }}
                                        </div>
                                        <div>
                                            <h4 class="text-xs sm:text-sm font-bold text-zinc-900">{{ $session->title }}</h4>
                                            @if ($session->speaker_name)
                                                <p class="text-[11px] text-amber-700 mt-0.5">Speaker: {{ $session->speaker_name }}</p>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($session->location)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-zinc-100 text-[10px] font-semibold text-zinc-700 self-start sm:self-auto">
                                            📍 {{ $session->location }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Frequently Asked Questions Accordion -->
                @if (($customization['show_faqs'] ?? true) && $faqs->isNotEmpty())
                    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 space-y-5">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-[0.2em] text-zinc-500">Clarifications & Help</p>
                            <h2 class="mt-1 text-xl font-semibold tracking-tight text-zinc-900">Frequently Asked Questions</h2>
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
            </section>

            <aside class="lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-[0.2em] text-zinc-500">Order summary</p>

                    @if ($selectedTicketType)
                        <div class="mt-4 flex items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold text-zinc-900">{{ $selectedTicketType->name }}</p>
                                <p class="mt-1 text-sm text-zinc-500">GHS {{ number_format((float) $selectedTicketType->price, 2) }} x {{ $quantity }}</p>
                            </div>
                            <p class="font-semibold text-zinc-900">GHS {{ number_format($subtotal, 2) }}</p>
                        </div>

                        <dl class="mt-6 space-y-3 border-t border-dashed border-zinc-200 pt-4 text-sm">
                            <div class="flex items-center justify-between text-zinc-600">
                                <dt>Ticket net</dt>
                                <dd>GHS {{ number_format($breakdown['net'], 2) }}</dd>
                            </div>
                            @if ($breakdown['platform_fee'] > 0)
                                <div class="flex items-center justify-between text-zinc-600">
                                    <dt>Platform fee</dt>
                                    <dd>GHS {{ number_format($breakdown['platform_fee'], 2) }}</dd>
                                </div>
                            @endif
                            <div class="flex items-center justify-between text-zinc-600">
                                <dt>Processing fee (1.95%)</dt>
                                <dd>GHS {{ number_format($breakdown['processing_fee'], 2) }}</dd>
                            </div>
                            <div class="flex items-center justify-between border-t border-zinc-200 pt-3 text-base font-semibold text-zinc-900">
                                <dt>Total payable</dt>
                                <dd>GHS {{ number_format($breakdown['gross'], 2) }}</dd>
                            </div>
                        </dl>

                        <button
                            type="button"
                            wire:click="openCheckout"
                            class="mt-6 w-full rounded-xl bg-zinc-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-zinc-700"
                        >
                            Continue to secure checkout
                        </button>
                    @else
                        <p class="mt-4 rounded-2xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500">
                            Select a ticket tier to see your total.
                        </p>
                    @endif

                    <p class="mt-4 text-center text-xs leading-5 text-zinc-500">
                        Pay with MTN MoMo, Telecel Cash, AirtelTigo Money or a bank card. Paystack's processing fee is shown before you pay.
                    </p>
                </div>
            </aside>
        </div>
    </main>

    @if ($checkoutOpen)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-zinc-950/50 p-0 sm:items-center sm:p-6" wire:key="checkout-drawer">
            <div class="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-t-3xl bg-white p-6 shadow-2xl sm:rounded-3xl sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-[0.2em] text-zinc-500">Secure checkout</p>
                        <h2 class="mt-1 text-xl font-semibold tracking-tight text-zinc-900">Attendee details</h2>
                    </div>
                    <button type="button" wire:click="closeCheckout" class="rounded-full border border-zinc-200 px-3 py-1 text-sm text-zinc-600 hover:bg-zinc-50">
                        Close
                    </button>
                </div>

                @if ($flashMessage)
                    <div class="mt-4 rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-800">
                        {{ $flashMessage }}
                    </div>
                @endif

                @error('payment')
                    <div class="mt-4 rounded-2xl bg-rose-50 p-4 text-sm text-rose-700">{{ $message }}</div>
                @enderror

                <form wire:submit="startCheckout" class="mt-6 space-y-4">
                    <div>
                        <label for="customerName" class="block text-sm font-medium text-zinc-700">Full name</label>
                        <input id="customerName" type="text" wire:model.blur="customerName" autocomplete="name" class="mt-1.5 w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/10">
                        @error('customerName') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="customerEmail" class="block text-sm font-medium text-zinc-700">Email</label>
                            <input id="customerEmail" type="email" wire:model.blur="customerEmail" autocomplete="email" class="mt-1.5 w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/10">
                            @error('customerEmail') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="customerPhone" class="block text-sm font-medium text-zinc-700">Phone</label>
                            <input id="customerPhone" type="tel" wire:model.blur="customerPhone" autocomplete="tel" class="mt-1.5 w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/10">
                            @error('customerPhone') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Dynamic Attendee Questionnaire Injection -->
                    @if ($formFields->isNotEmpty())
                        <div class="border-t border-zinc-200 pt-4 space-y-4">
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-600">Additional Information</h3>
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
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="rounded-2xl bg-zinc-50 p-4">
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between text-zinc-600">
                                <dt>{{ $selectedTicketType?->name ?? 'Ticket' }} x {{ $quantity }}</dt>
                                <dd>GHS {{ number_format($breakdown['net'], 2) }}</dd>
                            </div>
                            @if ($breakdown['platform_fee'] > 0)
                                <div class="flex justify-between text-zinc-600">
                                    <dt>Platform fee</dt>
                                    <dd>GHS {{ number_format($breakdown['platform_fee'], 2) }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between text-zinc-600">
                                <dt>Processing fee</dt>
                                <dd>GHS {{ number_format($breakdown['processing_fee'], 2) }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-semibold text-zinc-900">
                                <dt>Total payable</dt>
                                <dd>GHS {{ number_format($breakdown['gross'], 2) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-emerald-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800" wire:loading.attr="disabled">
                        <span wire:loading.remove>Reserve seats &amp; pay with Paystack</span>
                        <span wire:loading>Reserving your seats...</span>
                    </button>

                    <p class="text-center text-xs leading-5 text-zinc-500">
                        By continuing you agree to the event terms. Your reservation expires after 15 minutes if payment is not completed.
                    </p>
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
                            @if ($viewingSpeaker->photo_path)
                                <img src="{{ $viewingSpeaker->photo_path }}" alt="{{ $viewingSpeaker->name }}" class="size-full object-cover">
                            @else
                                {{ substr($viewingSpeaker->name, 0, 1) }}
                            @endif
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-zinc-900">{{ $viewingSpeaker->name }}</h3>
                            <p class="text-xs text-zinc-600">{{ $viewingSpeaker->title }}</p>
                            @if ($viewingSpeaker->organization)
                                <p class="text-[11px] text-zinc-500">{{ $viewingSpeaker->organization }}</p>
                            @endif
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click="closeSpeakerModal"
                        class="size-8 rounded-full border border-zinc-200 text-zinc-500 hover:text-zinc-900 flex items-center justify-center transition press-scale"
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
                        class="px-4 py-2 rounded-xl bg-zinc-900 text-white text-xs font-semibold hover:bg-zinc-800 transition press-scale"
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
