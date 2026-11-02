@php
    $inputClass = 'w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 font-mono text-xs font-medium text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden';
    $label = $organization->subdivision_label ?: 'Zone';
    $money = static fn(float|int|string $amount): string => 'GHS ' . number_format((float) $amount, 2);
    // Orders without a zone are the main account's own sales, so they are never owed to anyone.
    $totals = ['gross' => $ledger->sum('gross'), 'routed' => $ledger->sum('routed'), 'owed' => $ledger->except('none')->sum('owed')];
@endphp

<div class="space-y-6">
    <div>
        <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ $label }} Payouts</h1>
        <p class="mt-0.5 text-xs text-[var(--text-secondary)] sm:text-sm">Connect each {{ strtolower($label) }} to its Paystack subaccount so ticket revenue settles straight to it, and reconcile what each one has received.</p>
    </div>

    <div class="flex flex-wrap items-center gap-2 text-[11px]">
        <x-ui.badge :color="$splitsEnabled ? 'emerald' : 'zinc'" pill size="sm">Split payments {{ $splitsEnabled ? 'on' : 'off' }}</x-ui.badge>
        <x-ui.badge :color="$mode === 'live' ? 'red' : 'amber'" pill size="sm">Paystack {{ $mode }} keys</x-ui.badge>
        <span class="text-[var(--text-secondary)]">
            @if ($policy === 'block')
                Checkout is refused for {{ strtolower(\Illuminate\Support\Str::plural($label)) }} without a verified subaccount.
            @else
                Sales for unconnected {{ strtolower(\Illuminate\Support\Str::plural($label)) }} settle to the main account and are listed as owed below.
            @endif
        </span>
    </div>

    @if ($flashMessage)
        <div class="flex items-center justify-between rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-xs font-medium text-emerald-800 dark:text-emerald-300">
            <span>{{ $flashMessage }}</span>
            <button type="button" wire:click="$set('flashMessage', null)" class="cursor-pointer">&times;</button>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach ([['Collected', $totals['gross']], ['Settled to subaccounts', $totals['routed']], ['Owed to ' . strtolower(\Illuminate\Support\Str::plural($label)), $totals['owed']]] as [$title, $amount])
            <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4">
                <p class="text-[11px] font-medium text-[var(--text-secondary)]">{{ $title }}</p>
                <p class="mt-1 text-lg font-bold tabular-nums">{{ $money($amount) }}</p>
            </div>
        @endforeach
    </div>

    @if ($zones->isEmpty())
        <div class="rounded-3xl border border-dashed border-[var(--border-hairline)] bg-[var(--bg-surface)] p-12 text-center">
            <h3 class="text-sm font-semibold">No {{ strtolower(\Illuminate\Support\Str::plural($label)) }} yet</h3>
            <p class="mx-auto mt-1 max-w-sm text-xs text-[var(--text-secondary)]">All sales settle to the main Paystack account.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)]">
            <table class="w-full min-w-[720px] text-left text-xs">
                <thead class="border-b border-[var(--border-hairline)] text-[11px] text-[var(--text-secondary)]">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ $label }}</th>
                        <th class="px-4 py-3 font-medium">Payout account</th>
                        <th class="px-4 py-3 text-right font-medium">Paid orders</th>
                        <th class="px-4 py-3 text-right font-medium">Settled</th>
                        <th class="px-4 py-3 text-right font-medium">Owed</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-hairline)]">
                    @foreach ($zones as $zone)
                        @php
                            $row = $ledger->get((string) $zone->id);
                            $code = $zone->paystack_subaccount_code;
                            $check = $checks[$zone->id] ?? null;
                            $verdict = $check['recognised'] ?? ($code !== null ? $subaccounts->cachedVerdict($code) : null);
                        @endphp
                        <tr wire:key="zone-{{ $zone->id }}" class="align-top">
                            <td class="px-4 py-3 font-semibold">{{ $zone->name }}</td>
                            <td class="px-4 py-3">
                                @if ($editingZoneId === $zone->id)
                                    <form wire:submit="saveZone" class="flex max-w-sm flex-col gap-2">
                                        <input type="text" wire:model="subaccountCode" placeholder="ACCT_xxxxxxxxxxxx" class="{{ $inputClass }}" autofocus />
                                        @error('subaccountCode')
                                            <p class="text-[11px] text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                        <p class="text-[10px] text-[var(--text-tertiary)]">Checked against your {{ $mode }} keys before saving. Leave empty to disconnect.</p>
                                        <div class="flex gap-2">
                                            <button type="submit" class="cursor-pointer rounded-lg bg-[var(--color-primary)] px-3 py-1.5 text-[11px] font-semibold text-[var(--color-primary-fg)] press-scale">
                                                <span wire:loading.remove wire:target="saveZone">Verify & save</span>
                                                <span wire:loading wire:target="saveZone">Checking Paystack…</span>
                                            </button>
                                            <button type="button" wire:click="cancelEdit" class="cursor-pointer rounded-lg px-3 py-1.5 text-[11px] font-semibold text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)]">Cancel</button>
                                        </div>
                                    </form>
                                @elseif ($code === null)
                                    <x-ui.badge color="amber" pill size="sm">Not connected</x-ui.badge>
                                @else
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-[11px]">{{ $code }}</span>
                                        @if ($verdict === true)
                                            <x-ui.badge color="emerald" pill size="sm">Verified</x-ui.badge>
                                        @elseif ($verdict === false)
                                            <x-ui.badge color="red" pill size="sm">Rejected</x-ui.badge>
                                        @else
                                            <x-ui.badge color="zinc" pill size="sm">Not checked</x-ui.badge>
                                        @endif
                                    </div>
                                    @if ($check !== null)
                                        <p class="mt-1 text-[11px] text-[var(--text-secondary)]">
                                            {{ $check['message'] ?? trim(($check['name'] ?? '') . ' · ' . ($check['bank'] ?? '') . ' ' . ($check['account'] ?? ''), ' ·') }}
                                        </p>
                                    @endif
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((int) ($row->orders ?? 0)) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $money($row->routed ?? 0) }}</td>
                            <td @class(['px-4 py-3 text-right tabular-nums', 'font-semibold text-amber-700 dark:text-amber-400' => (float) ($row->owed ?? 0) > 0])>{{ $money($row->owed ?? 0) }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if ($editingZoneId !== $zone->id)
                                    @if ($code !== null)
                                        <button type="button" wire:click="verifyZone({{ $zone->id }})" class="cursor-pointer rounded-lg px-2.5 py-1 text-[11px] font-semibold text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)]">
                                            <span wire:loading.remove wire:target="verifyZone({{ $zone->id }})">Verify</span>
                                            <span wire:loading wire:target="verifyZone({{ $zone->id }})">…</span>
                                        </button>
                                    @endif
                                    <button type="button" wire:click="editZone({{ $zone->id }})" class="cursor-pointer rounded-lg px-2.5 py-1 text-[11px] font-semibold hover:bg-[var(--bg-subtle)]">{{ $code === null ? 'Connect' : 'Change' }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    @if ($ledger->has('none'))
                        <tr class="text-[var(--text-secondary)]">
                            <td class="px-4 py-3">No {{ strtolower($label) }}</td>
                            <td class="px-4 py-3">Main account</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((int) $ledger->get('none')->orders) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">—</td>
                            <td class="px-4 py-3 text-right tabular-nums">—</td>
                            <td></td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <p class="text-[11px] text-[var(--text-tertiary)]">
            Settled amounts are the ticket value each {{ strtolower($label) }} receives; the platform fee and Paystack's processing fee stay with the main account. Owed amounts were collected centrally and need a manual transfer.
        </p>
    @endif
</div>
