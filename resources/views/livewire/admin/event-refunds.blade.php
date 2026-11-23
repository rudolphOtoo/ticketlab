@php
    $money = static fn(float|int $amount): string => 'GHS ' . number_format((float) $amount, 2);
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('admin.events') }}" wire:navigate class="text-xs font-medium text-[var(--text-secondary)] hover:text-[var(--text-primary)]">← Events</a>
            <h1 class="mt-1 text-xl font-bold tracking-tight sm:text-2xl">Refund list: {{ $event->title }}</h1>
            <p class="mt-0.5 text-xs text-[var(--text-secondary)] sm:text-sm">
                @if ($event->isCancelled())
                    Cancelled {{ $event->cancelled_at->format('M j, Y · g:i A') }}.
                @endif
                {{ $orderCount }} paid {{ \Illuminate\Support\Str::plural('order', $orderCount) }}, {{ $money($grandTotal) }} in total.
            </p>
        </div>
        @if ($orderCount > 0)
            <button type="button" wire:click="exportCsv" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-[var(--border-hairline)] px-4 py-2 text-xs font-semibold hover:bg-[var(--bg-subtle)]">Download CSV</button>
        @endif
    </div>

    <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-xs text-amber-900 dark:text-amber-200" data-refund-note>
        <strong>Refunds are not automatic.</strong>
        Refund each order by hand: online payments from the Paystack dashboard (search the order reference), cash
        sales at the desk. The amount shown is what the buyer paid.
    </div>

    @forelse ($zones as $zone)
        <section class="space-y-2" data-refund-zone="{{ $zone['zone'] }}">
            <h2 class="flex items-baseline justify-between text-sm font-bold">
                <span>{{ $zone['zone'] }} · {{ count($zone['orders']) }} {{ \Illuminate\Support\Str::plural('order', count($zone['orders'])) }}</span>
                <span class="font-mono tabular-nums" data-zone-total>{{ $money($zone['total']) }}</span>
            </h2>
            <div class="overflow-x-auto rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)]">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-[var(--border-hairline)] text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Buyer</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Paid by</th>
                            <th class="px-4 py-3 text-right">Passes</th>
                            <th class="px-4 py-3 text-right">Amount paid</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--border-hairline)]">
                        @foreach ($zone['orders'] as $order)
                            <tr data-refund-row="{{ $order->reference }}">
                                <td class="px-4 py-2.5 font-mono">{{ $order->reference }}</td>
                                <td class="px-4 py-2.5">{{ $order->customer_name }}</td>
                                <td class="px-4 py-2.5">{{ $order->customer_email }}<br><span class="text-[var(--text-tertiary)]">{{ $order->customer_phone }}</span></td>
                                <td class="px-4 py-2.5">{{ $order->payment_method->label() }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $order->passes_count }}</td>
                                <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ $money((float) $order->gross_amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <p class="rounded-2xl border border-dashed border-[var(--border-hairline)] p-8 text-center text-xs text-[var(--text-secondary)]">No paid orders: nothing to refund.</p>
    @endforelse
</div>
