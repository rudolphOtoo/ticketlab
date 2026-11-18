@php
    $money = static fn(float|int $amount): string => 'GHS ' . number_format((float) $amount, 2);
@endphp

<div class="space-y-6">
    <div>
        <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ $label }} Reassignments</h1>
        <p class="mt-0.5 text-xs text-[var(--text-secondary)] sm:text-sm">Passes that were given a {{ strtolower($label) }} after they were paid for.</p>
    </div>

    <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-xs text-amber-900 dark:text-amber-200" data-money-note>
        <strong>Money is not moved automatically.</strong>
        Assigning a {{ strtolower($label) }} changes which gate admits the pass and how reports count it. The payment
        has already settled where it was paid (usually the main account), so pay each {{ strtolower($label) }} the
        amount below by hand.
    </div>

    @if ($totals !== [])
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3" data-zone-owed>
            @foreach ($totals as $zone => $total)
                <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4">
                    <p class="text-[11px] font-medium text-[var(--text-secondary)]">{{ $zone }} · {{ $total['orders'] }} {{ $total['orders'] === 1 ? 'order' : 'orders' }} · {{ $total['passes'] }} {{ $total['passes'] === 1 ? 'pass' : 'passes' }}</p>
                    <p class="mt-1 text-lg font-bold tabular-nums">{{ $money($total['owed']) }} <span class="text-xs font-medium text-[var(--text-secondary)]">to settle</span></p>
                </div>
            @endforeach
        </div>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)]">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-[var(--border-hairline)] text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                <tr>
                    <th class="px-4 py-3">{{ $label }}</th>
                    <th class="px-4 py-3">Order</th>
                    <th class="px-4 py-3">Buyer</th>
                    <th class="px-4 py-3 text-right">Passes</th>
                    <th class="px-4 py-3 text-right">Ticket value</th>
                    <th class="px-4 py-3">Settled to</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border-hairline)]">
                @forelse ($rows as $row)
                    <tr data-settlement-row="{{ $row['reference'] }}">
                        <td class="px-4 py-2.5 font-medium">{{ $row['zone'] }}</td>
                        <td class="px-4 py-2.5 font-mono">{{ $row['reference'] }}</td>
                        <td class="px-4 py-2.5">{{ $row['buyer'] }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums">{{ $row['passes'] }}</td>
                        <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ $money($row['amount']) }}</td>
                        <td class="px-4 py-2.5">{{ $row['settled_to'] }}{{ $row['settled_to_zone'] ? ' (already this ' . strtolower($label) . ')' : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-[var(--text-secondary)]">No passes have been reassigned.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="space-y-2">
        <h2 class="text-sm font-bold">Audit trail</h2>
        <ul class="divide-y divide-[var(--border-hairline)] rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-xs" data-audit-trail>
            @forelse ($audit as $entry)
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                    <span>
                        Pass #{{ $entry->subject_id }} → <strong>{{ $entry->details['to_zone'] ?? '—' }}</strong>
                        by {{ ($entry->details['via'] ?? null) === 'buyer' ? 'the buyer (ticket page)' : ($entry->user->name ?? 'a deleted account') }}
                    </span>
                    <span class="font-mono text-[var(--text-tertiary)]">{{ $entry->created_at->format('j M Y, g:i A') }}</span>
                </li>
            @empty
                <li class="px-4 py-6 text-center text-[var(--text-secondary)]">Nothing recorded yet.</li>
            @endforelse
        </ul>
    </div>
</div>
