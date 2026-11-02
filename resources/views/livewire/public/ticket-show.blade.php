@php($event = $attendees->first()?->ticketType?->event)

<div class="min-h-screen bg-zinc-100 pb-12">
    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <div class="flex items-center gap-3">
                @if ($organization->logo_url)
                    <img src="{{ $organization->logo_url }}" alt="{{ $organization->name }}" class="h-10 w-10 rounded-full object-cover">
                @else
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-900 text-sm font-semibold text-white">
                        {{ \Illuminate\Support\Str::of($organization->name)->explode(' ')->map(fn ($word) => \Illuminate\Support\Str::substr($word, 0, 1))->take(2)->implode('') }}
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

    <main class="mx-auto max-w-3xl px-4 pt-6 sm:px-6">
        <div class="overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-sm">
            <div class="bg-gradient-to-br from-zinc-900 via-zinc-800 to-emerald-900 px-6 py-8 text-white sm:px-8">
                <p class="text-xs font-medium uppercase tracking-[0.25em] text-emerald-200">{{ $isPaid ? 'You are in' : 'Payment pending' }}</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ $event?->title ?? 'Event ticket' }}</h1>
                @if ($event)
                    <p class="mt-2 text-sm text-zinc-300">
                        {{ $event->start_at->format('D, j M Y · g:i A') }}
                        @if ($event->venue)
                            · {{ $event->venue }}
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
                        <button type="button" onclick="window.print()" class="rounded-xl bg-zinc-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-zinc-700">
                            Save as PDF
                        </button>
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
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .ticket-card { break-inside: avoid; }
        }
    </style>
</div>
