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
                <div class="overflow-hidden rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-sm">
                    @if ($event->banner_path)
                        <img src="{{ asset('storage/'.$event->banner_path) }}" alt="{{ $event->title }}" class="h-64 w-full object-cover sm:h-80">
                    @else
                        <div class="flex h-64 w-full items-end bg-gradient-to-br from-zinc-900 via-zinc-800 to-emerald-900 p-8 sm:h-80">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-[0.25em] text-emerald-200">You are invited</p>
                                <h1 class="mt-3 max-w-xl text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ $event->title }}</h1>
                            </div>
                        </div>
                    @endif

                    <div class="space-y-5 p-6 sm:p-8">
                        <div class="flex flex-wrap items-center gap-3 text-sm text-[var(--text-secondary)]">
                            <span class="inline-flex items-center gap-2 rounded-full border border-[var(--border-hairline)] px-3 py-1">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                {{ $event->start_at->format('D, j M Y') }}
                                @if (! $event->start_at->isSameDay($event->end_at))
                                    – {{ $event->end_at->format('D, j M Y') }}
                                @endif
                            </span>
                            @if ($event->venue)
                                <span class="inline-flex items-center gap-2 rounded-full border border-[var(--border-hairline)] px-3 py-1">{{ $event->venue }}</span>
                            @endif
                        </div>

                        <div x-data="{
                                target: new Date('{{ $event->start_date ?? $event->start_at->toIso8601String() }}').getTime(),
                                now: Date.now(),
                                days: 0, hours: 0, minutes: 0, seconds: 0,
                                init() {
                                    this.update();
                                    setInterval(() => { this.now = Date.now(); this.update(); }, 1000);
                                },
                                update() {
                                    let diff = Math.max(0, this.target - this.now);
                                    this.days = Math.floor(diff / (1000*60*60*24));
                                    diff -= this.days * (1000*60*60*24);
                                    this.hours = Math.floor(diff / (1000*60*60));
                                    diff -= this.hours * (1000*60*60);
                                    this.minutes = Math.floor(diff / (1000*60));
                                    diff -= this.minutes * (1000*60);
                                    this.seconds = Math.floor(diff / 1000);
                                }
                            }" x-init="init()" class="grid grid-cols-4 gap-3 text-center">
                            <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] p-3">
                                <div class="text-xl font-semibold" x-text="String(days).padStart(2,'0')">00</div>
                                <div class="text-xs text-[var(--text-tertiary)]">Days</div>
                            </div>
                            <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] p-3">
                                <div class="text-xl font-semibold" x-text="String(hours).padStart(2,'0')">00</div>
                                <div class="text-xs text-[var(--text-tertiary)]">Hours</div>
                            </div>
                            <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] p-3">
                                <div class="text-xl font-semibold" x-text="String(minutes).padStart(2,'0')">00</div>
                                <div class="text-xs text-[var(--text-tertiary)]">Minutes</div>
                            </div>
                            <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] p-3">
                                <div class="text-xl font-semibold" x-text="String(seconds).padStart(2,'0')">00</div>
                                <div class="text-xs text-[var(--text-tertiary)]">Seconds</div>
                            </div>
                        </div>

                        <a href="#tickets" class="inline-flex items-center justify-center rounded-xl bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-[var(--color-primary-fg)] hover:opacity-90 transition">Register Now</a>

                        <div>
                            <h2 class="text-xl font-semibold tracking-tight text-[var(--text-primary)]">About this event</h2>
                            <p class="mt-2 whitespace-pre-line text-sm leading-7 text-[var(--text-secondary)]">{{ $event->description ?: 'Join us for this special gathering. Select a ticket tier below to reserve your seat.' }}</p>
                        </div>

                        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] p-4 text-sm text-[var(--text-secondary)]">
                            <p class="font-medium text-[var(--text-primary)]">{{ $event->start_at->format('l, j F Y') }}</p>
                            <p>{{ $event->start_at->format('g:i A') }} – {{ $event->end_at->format('g:i A') }}</p>
                            @if ($event->venue)
                                <p class="mt-1">{{ $event->venue }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div id="tickets" class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-sm sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold tracking-tight text-[var(--text-primary)]">Choose your ticket</h2>
                            <p class="mt-1 text-sm text-[var(--text-secondary)]">Inventory updates live. Your seats are held for 15 minutes at checkout.</p>
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

                @if (isset($speakers) && $speakers->isNotEmpty())
                    <div class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-sm sm:p-8">
                        <h2 class="text-xl font-semibold tracking-tight text-[var(--text-primary)]">Speakers & Guests</h2>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($speakers as $speaker)
                                <div x-data="{ open: false }" class="rounded-2xl border border-[var(--border-hairline)] p-4">
                                    <button type="button" @click="open = true" class="w-full text-left">
                                        <div class="flex items-center gap-3">
                                            @if ($speaker->photo_path)
                                                <img src="{{ asset('storage/'.$speaker->photo_path) }}" alt="{{ $speaker->name }}" class="h-12 w-12 rounded-full object-cover">
                                            @else
                                                <div class="h-12 w-12 rounded-full bg-[var(--bg-subtle)] flex items-center justify-center text-sm font-semibold">{{ strtoupper(substr($speaker->name,0,2)) }}</div>
                                            @endif
                                            <div>
                                                <p class="font-medium text-[var(--text-primary)]">{{ $speaker->name }}</p>
                                                <p class="text-xs text-[var(--text-secondary)]">{{ $speaker->title_role }}</p>
                                                @if ($speaker->topic)
                                                    <p class="text-xs text-[var(--text-tertiary)]">{{ $speaker->topic }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </button>
                                    <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click="open = false">
                                        <div class="w-full max-w-md rounded-3xl bg-[var(--bg-surface)] p-6 shadow-xl" @click.stop>
                                            <div class="flex items-start justify-between">
                                                <div>
                                                    <p class="text-lg font-semibold">{{ $speaker->name }}</p>
                                                    <p class="text-sm text-[var(--text-secondary)]">{{ $speaker->title_role }}</p>
                                                </div>
                                                <button type="button" @click="open = false" class="text-sm text-[var(--text-tertiary)]">Close</button>
                                            </div>
                                            @if ($speaker->bio)
                                                <p class="mt-3 text-sm text-[var(--text-secondary)] leading-6">{{ $speaker->bio }}</p>
                                            @endif
                                            @if (is_array($speaker->social_links))
                                                <div class="mt-4 flex flex-wrap gap-2">
                                                    @foreach ($speaker->social_links as $key => $link)
                                                        @php($url = is_array($link) ? ($link['url'] ?? $link['href'] ?? null) : $link)
                                                        @if ($url)
                                                            <a href="{{ $url }}" target="_blank" class="rounded-full border border-[var(--border-hairline)] px-2 py-1 text-xs">{{ is_array($link) ? ($link['label'] ?? $key) : $key }}</a>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (isset($scheduleDays) && $scheduleDays->isNotEmpty())
                    <div class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-sm sm:p-8">
                        <h2 class="text-xl font-semibold tracking-tight text-[var(--text-primary)]">Program Schedule</h2>
                        <div x-data="{ tab: '{{ $scheduleDays->keys()->first() }}' }" class="mt-4">
                            <div class="flex flex-wrap gap-2">
                                @foreach ($scheduleDays as $dayLabel => $daySessions)
                                    <button type="button" @click="tab = '{{ $dayLabel }}'" :class="tab === '{{ $dayLabel }}' ? 'bg-[var(--color-primary)] text-[var(--color-primary-fg)]' : 'border border-[var(--border-hairline)] text-[var(--text-secondary)]'" class="rounded-full px-3 py-1 text-xs font-medium">{{ $dayLabel }}</button>
                                @endforeach
                            </div>
                            <div class="mt-4 space-y-2">
                                @foreach ($scheduleDays as $dayLabel => $daySessions)
                                    <div x-show="tab === '{{ $dayLabel }}'">
                                        @foreach ($daySessions as $session)
                                            <div class="flex items-start justify-between gap-4 rounded-2xl border border-[var(--border-hairline)] p-3">
                                                <div>
                                                    <p class="font-medium text-[var(--text-primary)]">{{ $session->session_title }}</p>
                                                    <p class="text-xs text-[var(--text-secondary)]">{{ $session->speaker_name }} @if($session->venue_hall) • {{ $session->venue_hall }} @endif</p>
                                                    @if ($session->description)
                                                        <p class="mt-1 text-xs text-[var(--text-tertiary)]">{{ $session->description }}</p>
                                                    @endif
                                                </div>
                                                <div class="text-right text-xs text-[var(--text-tertiary)]">
                                                    <p>{{ $session->start_time }}</p>
                                                    <p>{{ $session->end_time }}</p>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                @if (isset($faqs) && $faqs->isNotEmpty())
                    <div class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-sm sm:p-8">
                        <h2 class="text-xl font-semibold tracking-tight text-[var(--text-primary)]">FAQs</h2>
                        <div class="mt-3">
                            <input type="text" wire:model.live.debounce.300ms="faqSearch" placeholder="Search FAQs..." class="w-full rounded-xl border border-[var(--border-hairline)] px-3 py-2 text-sm">
                        </div>
                        <div class="mt-4 divide-y divide-[var(--border-hairline)]">
                            @foreach ($faqs as $faq)
                                <div x-data="{ open: false }" class="py-3">
                                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between text-left">
                                        <span class="font-medium text-[var(--text-primary)]">{{ $faq->question }}</span>
                                        <span class="text-[var(--text-tertiary)]" x-text="open ? '-' : '+'"></span>
                                    </button>
                                    <div x-show="open" x-collapse style="display: none;" class="mt-2 text-sm text-[var(--text-secondary)] leading-6">
                                        {!! nl2br(e($faq->answer)) !!}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                @endif

                @if (isset($formFields) && $formFields->isNotEmpty())
                    <div class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-sm sm:p-8">
                        <h2 class="text-xl font-semibold tracking-tight text-[var(--text-primary)]">Event Information</h2>
                        <div class="mt-4 space-y-3">
                            @foreach ($formFields as $field)
                                <div class="rounded-2xl border border-[var(--border-hairline)] p-3">
                                    <p class="text-sm font-medium text-[var(--text-primary)]">{{ $field->label }} @if($field->is_required)<span class="text-rose-500">*</span>@endif</p>
                                    @if ($field->description)
                                        <p class="text-xs text-[var(--text-secondary)]">{{ $field->description }}</p>
                                    @endif
                                </div>
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

                    @if (isset($formFields) && $formFields->isNotEmpty())
                        <div class="space-y-3 rounded-2xl border border-zinc-200 p-4">
                            <p class="text-sm font-medium text-zinc-900">Additional Information</p>
                            @foreach ($formFields as $field)
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700">{{ $field->label }} @if($field->is_required)<span class="text-rose-500">*</span>@endif</label>
                                    @if ($field->input_type === 'textarea')
                                        <textarea wire:model.blur="formResponses.{{ $field->field_key }}" class="mt-1.5 w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/10"></textarea>
                                    @elseif ($field->input_type === 'select' && is_array($field->options))
                                        <select wire:model.blur="formResponses.{{ $field->field_key }}" class="mt-1.5 w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/10">
                                            <option value="">Select...</option>
                                            @foreach ($field->options as $opt)
                                                <option value="{{ is_array($opt) ? ($opt['value'] ?? $opt['label']) : $opt }}">{{ is_array($opt) ? ($opt['label'] ?? $opt['value']) : $opt }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" wire:model.blur="formResponses.{{ $field->field_key }}" placeholder="{{ $field->placeholder }}" class="mt-1.5 w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/10">
                                    @endif
                                    @error('formResponses.' . $field->field_key) <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
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
