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
                                <h1 class="mt-3 max-w-xl text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ $event->title }}</h1>
                            </div>
                        </div>
                    @endif

                    <div class="space-y-5 p-6 sm:p-8">
                        <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-600">
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
