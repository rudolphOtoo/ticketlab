@php($event = $attendees->first()?->ticketType?->event)

<div class="ticket-print-root min-h-screen bg-zinc-100 pb-12">
    <header class="ticket-print-brand border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <div class="flex items-center gap-3">
                @if ($organization->logoUrl())
                    <img src="{{ $organization->logoUrl() }}" alt="{{ $organization->name }}" class="h-10 w-10 rounded-full object-cover">
                @else
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-900 text-sm font-semibold text-white">
                        {{ $organization->initials() }}
                    </div>
                @endif
                <div>
                    <p class="text-xs font-medium uppercase tracking-[0.2em] text-zinc-500">Digital pass</p>
                    <p class="text-sm font-semibold text-zinc-900">{{ $organization->name }}</p>
                </div>
            </div>

            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $isPaid ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                {{ $isPaid ? 'Confirmed' : 'Awaiting payment' }}
            </span>
        </div>
    </header>

    <main class="ticket-print-main mx-auto max-w-3xl px-4 pt-6 sm:px-6">
        <div class="ticket-pass overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-sm">
            <div class="bg-gradient-to-br from-zinc-900 via-zinc-800 to-emerald-900 px-6 py-8 text-white sm:px-8">
                <p class="text-xs font-medium uppercase tracking-[0.25em] text-emerald-200">{{ $isPaid ? 'You are in' : 'Payment pending' }}</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ $event?->title ?? 'Event ticket' }}</h1>
                @if ($event)
                    <p class="mt-2 text-sm text-zinc-300">
                        {{ $venue->startAt->format('D, j M Y · g:i A') }}
                        @if ($venue->place())
                            · {{ $venue->place() }}
                        @endif
                        @if ($venue->zone)
                            · {{ $organization->subdivision_label }}: {{ $venue->zone->name }}
                        @endif
                        @if ($venue->mapUrl)
                            · <a href="{{ $venue->mapUrl }}" target="_blank" rel="noopener noreferrer" class="underline">Map</a>
                        @endif
                    </p>
                @endif
                <p class="mt-4 inline-flex rounded-full bg-white/10 px-3 py-1 font-mono text-xs tracking-wider text-white">
                    {{ $order->reference }}
                </p>
            </div>

            <div class="p-6 sm:p-8">
                @if (! $isPaid)
                    <div class="rounded-2xl bg-amber-50 p-5 text-sm leading-6 text-amber-900">
                        <p class="font-semibold">We have not received payment confirmation yet.</p>
                        <p class="mt-1">If you just completed payment, your pass will appear automatically once Paystack confirms the transaction. You can safely refresh this page.</p>
                    </div>
                @elseif ($attendees->isEmpty())
                    <div class="rounded-2xl bg-zinc-50 p-5 text-sm text-zinc-600">
                        Your payment is confirmed, but your passes are still being generated. Please refresh in a moment.
                    </div>
                @else
                    @if ($zoneMessage)
                        <p class="mb-4 rounded-2xl bg-emerald-50 p-4 text-sm font-medium text-emerald-900" data-zone-message>{{ $zoneMessage }}</p>
                    @endif

                    @if ($zoneChoices !== [])
                        <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-950" data-choose-zone>
                            <p class="font-semibold">Choose your {{ strtolower($organization->subdivision_label) }}</p>
                            <p class="mt-1 text-amber-900/80">Your {{ $attendees->count() === 1 ? 'pass needs' : 'passes need' }} a {{ strtolower($organization->subdivision_label) }} before the gate can admit you. Pick the one you will attend. <strong>You can only choose once.</strong></p>
                            <ul class="mt-4 grid gap-2">
                                @foreach ($zoneChoices as $choice)
                                    <li>
                                        <button
                                            type="button"
                                            wire:click="chooseZone({{ $choice['id'] }})"
                                            wire:confirm="Attend {{ $choice['name'] }}? This cannot be changed later."
                                            class="w-full cursor-pointer rounded-xl border border-amber-300 bg-white px-4 py-3 text-left hover:border-amber-500"
                                        >
                                            <span class="block font-semibold">{{ $choice['name'] }}</span>
                                            <span class="block text-xs text-zinc-600">{{ $choice['venue']->startAt->format('D, j M Y · g:i A') }}@if ($choice['venue']->place()) · {{ $choice['venue']->place() }}@endif</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <section class="no-print mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-950" data-your-ticket>
                        <p class="text-base font-semibold">This page is your ticket</p>
                        <p class="mt-1 text-emerald-900/80">Show the QR {{ $attendees->count() === 1 ? 'code' : 'codes' }} below at the gate, from this page or the PDF. Save the link or the PDF now; the email is only a backup copy.</p>

                        <div class="mt-4 flex flex-col gap-2 sm:flex-row" x-data="{ copied: false }">
                            <input
                                type="text"
                                readonly
                                value="{{ $ticketUrl }}"
                                aria-label="Your ticket link"
                                class="min-w-0 flex-1 rounded-xl border border-emerald-300 bg-white px-3 py-2.5 font-mono text-xs text-zinc-800"
                                x-ref="link"
                                x-on:focus="$event.target.select()"
                                data-ticket-link
                            >
                            <button
                                type="button"
                                class="cursor-pointer rounded-xl border border-emerald-400 bg-white px-4 py-2.5 font-semibold text-emerald-900 hover:bg-emerald-100"
                                x-on:click="navigator.clipboard?.writeText($refs.link.value).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                x-text="copied ? 'Copied' : 'Copy link'"
                            >Copy link</button>
                            <a href="{{ route('public.tickets.pdf', ['order' => $order->reference]) }}" download class="rounded-xl bg-emerald-700 px-4 py-2.5 text-center font-semibold text-white hover:bg-emerald-800">
                                Download PDF
                            </a>
                        </div>

                        <div class="mt-4 flex flex-col gap-2 border-t border-emerald-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-emerald-900/80" data-email-status="{{ $emailStatus['state'] }}">
                                @switch($emailStatus['state'])
                                    @case('sent')
                                        A copy was emailed to <strong>{{ $order->customer_email }}</strong>{{ $emailStatus['at'] ? ' on '.$emailStatus['at']->format('j M, g:i A') : '' }}.
                                        @break
                                    @case('sending')
                                        A copy is on its way to <strong>{{ $order->customer_email }}</strong>.
                                        @break
                                    @case('tomorrow')
                                        Today's email limit is reached, so the copy to <strong>{{ $order->customer_email }}</strong> goes out after midnight. Your ticket works now, here and in the PDF.
                                        @break
                                    @case('failed')
                                        We couldn't email a copy to <strong>{{ $order->customer_email }}</strong>. Your ticket works here and in the PDF.
                                        @break
                                    @default
                                        Your ticket works here and in the PDF.
                                @endswitch
                            </p>
                            <button
                                type="button"
                                wire:click="resendTicket"
                                wire:loading.attr="disabled"
                                class="cursor-pointer whitespace-nowrap rounded-xl border border-emerald-400 bg-white px-4 py-2.5 font-semibold text-emerald-900 hover:bg-emerald-100 disabled:opacity-60"
                            >
                                Resend my ticket
                            </button>
                        </div>

                        @if ($resendMessage)
                            <p class="mt-3 font-medium" role="status" data-resend-message>{{ $resendMessage }}</p>
                        @endif
                    </section>

                    <div class="space-y-5">
                        @foreach ($attendees as $attendee)
                            <article class="ticket-card overflow-hidden rounded-2xl border border-zinc-200">
                                <div class="grid gap-5 p-5 sm:grid-cols-[1fr_auto] sm:items-center">
                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-[0.2em] text-zinc-500">Ticket holder</p>
                                        <h2 class="mt-1 text-xl font-semibold tracking-tight text-zinc-900">{{ $attendee->name }}</h2>
                                        <p class="mt-1 text-sm text-zinc-600">{{ $attendee->email }}</p>

                                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                            <div>
                                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">Ticket tier</dt>
                                                <dd class="mt-0.5 font-medium text-zinc-900">{{ $attendee->ticketType?->name ?? 'General admission' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $organization->subdivision_label }}</dt>
                                                <dd class="mt-0.5 font-medium text-zinc-900">{{ $attendee->subdivision?->name ?? 'General admission' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">Pass ID</dt>
                                                <dd class="mt-0.5 break-all font-mono text-xs text-zinc-500">{{ $attendee->qr_code_hash }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">Issued</dt>
                                                <dd class="mt-0.5 font-medium text-zinc-900">{{ $order->paid_at?->format('j M Y · g:i A') ?? 'Pending' }}</dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <div class="mx-auto w-48 rounded-2xl border border-zinc-200 bg-white p-3">
                                        <div class="aspect-square w-full [&>svg]:h-full [&>svg]:w-full">
                                            {!! $qrCodes[$attendee->id] ?? '' !!}
                                        </div>
                                        <p class="mt-2 text-center text-[10px] font-medium uppercase tracking-[0.2em] text-zinc-500">Scan at the gate</p>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <div class="no-print mt-6 grid gap-3 sm:grid-cols-2">
                        <a href="{{ route('public.tickets.pdf', ['order' => $order->reference]) }}" download class="rounded-xl bg-zinc-900 px-4 py-3 text-center text-sm font-semibold text-white transition hover:bg-zinc-700">
                            Save as PDF
                        </a>
                        <button type="button" onclick="sharePass()" class="rounded-xl border border-zinc-300 px-4 py-3 text-sm font-semibold text-zinc-800 transition hover:bg-zinc-50">
                            Add to mobile pass
                        </button>
                    </div>

                    <p class="no-print mt-4 text-center text-xs leading-5 text-zinc-500">
                        Present this QR code at the entrance. Each pass is unique and can only be scanned once.
                    </p>
                @endif
            </div>
        </div>

        <div class="no-print mt-6 text-center">
            <a href="{{ route('home') }}" class="text-sm font-medium text-zinc-600 underline underline-offset-4 hover:text-zinc-900">
                Return to home
            </a>
        </div>
    </main>

    <script>
        function sharePass() {
            const shareData = {
                title: {{ \Illuminate\Support\Js::from($event?->title ?? 'My event ticket') }},
                text: {{ \Illuminate\Support\Js::from('My ticket pass for '.($event?->title ?? 'the event').' - '.$order->reference) }},
                url: window.location.href,
            };

            if (navigator.share) {
                navigator.share(shareData).catch(() => {});
                return;
            }

            window.print();
        }
    </script>

    <style>
        @media print {
            /* No page margin means the browser has nowhere to print its URL, date and page-number headers. */
            @page { size: A4 portrait; margin: 0; }

            html, body { background: #fff !important; }

            .no-print { display: none !important; }

            /* Keep the dark pass header, badges and pills instead of letting the browser strip backgrounds. */
            .ticket-print-root, .ticket-print-root * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .ticket-print-root {
                display: flex;
                min-height: 100vh;
                flex-direction: column;
                justify-content: center;
                padding: 12mm 0 !important;
                background: #fff !important;
            }

            .ticket-print-brand { border: 0 !important; }
            .ticket-print-main { width: 100%; max-width: 170mm; padding-top: 0 !important; }
            .ticket-pass { box-shadow: none !important; }
            .ticket-card { break-inside: avoid; }
        }
    </style>
</div>
