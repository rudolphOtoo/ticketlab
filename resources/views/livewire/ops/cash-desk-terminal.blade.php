<div class="flex flex-col gap-4 sm:gap-5" x-data="{
    playCashRegisterSound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const now = ctx.currentTime;
            
            // Cheerful register chime chord (E5, G#5, B5)
            [659.25, 830.61, 987.77].forEach((freq, idx) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, now + (idx * 0.06));
                
                gain.gain.setValueAtTime(0, now + (idx * 0.06));
                gain.gain.linearRampToValueAtTime(0.18, now + (idx * 0.06) + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.001, now + (idx * 0.06) + 0.4);
                
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now + (idx * 0.06));
                osc.stop(now + (idx * 0.06) + 0.45);
            });
            
            if (navigator.vibrate) {
                navigator.vibrate([60, 40, 90]);
            }
        } catch(e) {}
    }
}" x-on:pass-issued.window="playCashRegisterSound()">

    <!-- Page Header & Terminal Status -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex flex-col gap-0.5">
            <span class="text-[10px] sm:text-xs font-semibold tracking-wider uppercase text-[var(--text-tertiary)]">Point of Sale · Fast Register</span>
            <h1 class="text-lg sm:text-xl md:text-2xl font-bold tracking-tight text-[var(--text-primary)]">Walk-In Cash Desk</h1>
            <p class="text-xs sm:text-sm text-[var(--text-secondary)]">Instant gate registration, physical cash tender math, and pass printing.</p>
        </div>

        <div class="flex items-center gap-2">
            <div class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-2.5 py-1 text-xs">
                <span class="size-2 rounded-full bg-[var(--status-success)] animate-pulse"></span>
                <span class="font-medium text-[var(--text-primary)]">Terminal 01</span>
                <span class="text-[var(--text-tertiary)]">· Main Arena</span>
            </div>
            @if (auth()->user()?->isAdmin())
                <a href="{{ url('/admin/sessions') }}" class="cursor-pointer inline-flex items-center gap-1.5 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-2.5 py-1 text-xs font-medium text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-hover)] transition" wire:navigate>
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                    </svg>
                    Ledger View
                </a>
            @endif
        </div>
    </div>

    <!-- Main Register Grid (2 cols on tablet/desktop, 1 on mobile) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-5">

        <!-- Left Column: Tier Selection & Attendee Info (7 cols on desktop) -->
        <div class="lg:col-span-7 flex flex-col gap-4 sm:gap-5">
            
            <!-- Step 1: Ticket Tier Selection -->
            <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3.5 sm:p-4.5 shadow-xs flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] sm:text-xs font-semibold tracking-wider uppercase text-[var(--text-tertiary)]">1. Select Ticket Tier</span>
                    <span class="text-[11px] text-[var(--text-secondary)]">Event: <strong class="text-[var(--text-primary)]">{{ $event ? $event->title : 'Summit 2026' }}</strong></span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    @forelse($tiers as $tier)
                        @php $isSelected = $selectedTierId === $tier->id; @endphp
                        <button type="button"
                                wire:click="selectTier({{ $tier->id }})"
                                class="cursor-pointer relative flex flex-col justify-between text-left rounded-xl p-3 sm:p-3.5 border transition-all text-xs active:scale-[0.98] {{ $isSelected ? 'border-[var(--text-primary)] bg-[var(--bg-subtle)] ring-1 ring-[var(--text-primary)] shadow-xs' : 'border-[var(--border-hairline)] bg-[var(--bg-surface)] hover:bg-[var(--bg-subtle)] hover:border-[var(--text-secondary)]' }}">
                            
                            <div class="flex items-start justify-between gap-1">
                                <span class="font-semibold text-xs tracking-tight text-[var(--text-primary)]">{{ $tier->name }}</span>
                                @if($isSelected)
                                    <span class="size-4 rounded-full bg-[var(--text-primary)] text-[var(--bg-canvas)] flex items-center justify-center shrink-0">
                                        <svg class="size-2.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                    </span>
                                @endif
                            </div>

                            <div class="mt-3 flex flex-col gap-0.5">
                                <span class="text-base sm:text-lg font-bold font-mono tracking-tight text-[var(--text-primary)]">GHS {{ number_format((float) $tier->price, 2) }}</span>
                                <span class="text-[10px] text-[var(--text-tertiary)]">{{ $tier->available_quantity }} passes remaining</span>
                            </div>
                        </button>
                    @empty
                        <div class="col-span-3 text-center py-6 text-xs text-[var(--text-secondary)]">No ticket tiers found for this event.</div>
                    @endforelse
                </div>

                <!-- Quantity Stepper -->
                <div class="flex items-center justify-between pt-2 border-t border-[var(--border-hairline)] text-xs">
                    <span class="font-medium text-[var(--text-secondary)]">Number of Passes:</span>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="decrementQuantity" class="cursor-pointer size-7 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] flex items-center justify-center text-sm font-semibold hover:bg-[var(--bg-hover)] transition disabled:opacity-40" {{ $quantity <= 1 ? 'disabled' : '' }}>-</button>
                        <span class="font-mono text-sm font-bold px-2">{{ $quantity }}</span>
                        <button type="button" wire:click="incrementQuantity" class="cursor-pointer size-7 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] flex items-center justify-center text-sm font-semibold hover:bg-[var(--bg-hover)] transition disabled:opacity-40" {{ $quantity >= 10 ? 'disabled' : '' }}>+</button>
                    </div>
                </div>
            </div>

            <!-- Step 2: Customer Accreditation (Optional / Rapid) -->
            <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3.5 sm:p-4.5 shadow-xs flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] sm:text-xs font-semibold tracking-wider uppercase text-[var(--text-tertiary)]">2. Attendee Accreditation</span>
                    <span class="text-[10px] text-[var(--text-tertiary)]">Optional for instant walk-ins</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-[11px] font-medium text-[var(--text-secondary)]">Attendee Full Name</label>
                        <input type="text"
                               wire:model.live="attendeeName"
                               placeholder="e.g. Kwame Antwi (or leave empty)"
                               class="rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-2 text-xs text-[var(--text-primary)] placeholder-[var(--text-tertiary)] focus:border-[var(--text-primary)] focus:outline-hidden transition" />
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-[11px] font-medium text-[var(--text-secondary)]">Ghana Mobile Phone (WhatsApp / SMS)</label>
                        <input type="tel"
                               wire:model.live="attendeePhone"
                               placeholder="024 123 4567"
                               class="rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-2 text-xs text-[var(--text-primary)] placeholder-[var(--text-tertiary)] focus:border-[var(--text-primary)] focus:outline-hidden transition" />
                    </div>
                </div>
            </div>

            <!-- Recent Walk-In Ledger -->
            <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-3.5 sm:p-4.5 shadow-xs flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] sm:text-xs font-semibold tracking-wider uppercase text-[var(--text-tertiary)]">Terminal Cash History</span>
                    <span class="text-[10px] text-[var(--text-tertiary)]">Last 3 cash admissions</span>
                </div>

                <div class="flex flex-col divide-y divide-[var(--border-hairline)] text-xs">
                    @foreach($recentCashSales as $sale)
                        <div class="flex items-center justify-between py-2">
                            <div class="flex items-center gap-2.5">
                                <span class="font-mono text-[10px] bg-[var(--bg-subtle)] px-1.5 py-0.5 rounded border border-[var(--border-hairline)] text-[var(--text-secondary)]">{{ $sale['pass_code'] }}</span>
                                <div class="flex flex-col">
                                    <span class="font-medium text-[var(--text-primary)]">{{ $sale['name'] }}</span>
                                    <span class="text-[10px] text-[var(--text-tertiary)]">{{ $sale['tier'] }} (×{{ $sale['qty'] }})</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-mono font-semibold text-[var(--text-primary)]">GHS {{ number_format((float) $sale['amount'], 2) }}</span>
                                <span class="text-[10px] text-[var(--text-tertiary)]">{{ $sale['time'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

        <!-- Right Column: Cash Tender Calculator & CTA (5 cols on desktop) -->
        <div class="lg:col-span-5 flex flex-col gap-4">
            
            <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 sm:p-5 shadow-xs flex flex-col gap-4 sticky top-4">
                <span class="text-[10px] sm:text-xs font-semibold tracking-wider uppercase text-[var(--text-tertiary)]">Cash Tender Register</span>

                <!-- Grand Total Readout -->
                <div class="rounded-xl bg-[var(--bg-subtle)] border border-[var(--border-hairline)] p-3.5 flex flex-col gap-1 text-center">
                    <span class="text-xs text-[var(--text-secondary)]">Amount Due to Collect</span>
                    <span class="text-3xl sm:text-4xl font-extrabold font-mono tracking-tight text-[var(--text-primary)]">
                        GHS {{ number_format($total, 2) }}
                    </span>
                    <span class="text-[10px] text-[var(--text-tertiary)]">{{ $quantity }} × pass selected</span>
                </div>

                <!-- Quick Tender Presets (Banknotes in GH¢) -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-medium text-[var(--text-secondary)]">Quick Cash Banknotes</label>
                    <div class="grid grid-cols-4 gap-1.5">
                        <button type="button"
                                wire:click="updateTenderToExact"
                                class="cursor-pointer rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] py-2 text-center text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] active:scale-95 transition">
                            Exact
                        </button>
                        <button type="button"
                                wire:click="setPresetTender(100)"
                                class="cursor-pointer rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] py-2 text-center text-xs font-mono font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] active:scale-95 transition">
                            ¢100
                        </button>
                        <button type="button"
                                wire:click="setPresetTender(200)"
                                class="cursor-pointer rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] py-2 text-center text-xs font-mono font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] active:scale-95 transition">
                            ¢200
                        </button>
                        <button type="button"
                                wire:click="setPresetTender(500)"
                                class="cursor-pointer rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] py-2 text-center text-xs font-mono font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] active:scale-95 transition">
                            ¢500
                        </button>
                    </div>
                </div>

                <!-- Custom Tender Input -->
                <div class="flex flex-col gap-1">
                    <label class="text-[11px] font-medium text-[var(--text-secondary)]">Cash Handed by Buyer (GHS)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 font-mono text-xs font-bold text-[var(--text-secondary)]">GHS</span>
                        <input type="number"
                               step="0.50"
                               wire:model.live="cashTendered"
                               placeholder="0.00"
                               class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] pl-12 pr-3 py-2 text-sm font-mono font-bold text-[var(--text-primary)] focus:border-[var(--text-primary)] focus:outline-hidden transition" />
                    </div>
                    @error('cashTendered')
                        <span class="text-[10px] text-[var(--status-danger)]">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Change Calculation Result -->
                @php
                    $tenderVal = (float) $cashTendered;
                    $isSufficient = $tenderVal >= $total && $total > 0;
                @endphp
                <div class="rounded-xl border p-3 flex items-center justify-between {{ $isSufficient ? 'border-[var(--status-success)] bg-[var(--status-success-bg)] text-[var(--status-success)]' : 'border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[var(--text-secondary)]' }}">
                    <div class="flex flex-col">
                        <span class="text-[10px] uppercase font-semibold tracking-wider">Change Due to Buyer</span>
                        <span class="text-xs font-medium">{{ $isSufficient ? 'Customer change cleared' : 'Awaiting cash tender' }}</span>
                    </div>
                    <span class="text-xl font-bold font-mono tracking-tight">
                        GHS {{ number_format($changeDue, 2) }}
                    </span>
                </div>

                <!-- Primary Register Action Button -->
                <button type="button"
                        wire:click="processCashSale"
                        {{ ! $isSufficient ? 'disabled' : '' }}
                        class="cursor-pointer w-full flex items-center justify-center gap-2 rounded-xl py-3 px-4 text-xs font-bold transition shadow-xs active:scale-[0.98] {{ $isSufficient ? 'bg-[var(--accent-brand)] text-[var(--accent-brand-fg)] hover:opacity-90' : 'bg-[var(--bg-subtle)] text-[var(--text-tertiary)] border border-[var(--border-hairline)] cursor-not-allowed opacity-60' }}">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v12.75m0-12.75h19.5m0 0v12.75M3.75 4.5h16.5M3.75 4.5A2.25 2.25 0 0 0 1.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25h16.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25" />
                    </svg>
                    [ 💵 Collect GHS {{ number_format($total, 2) }} & Issue Pass ]
                </button>
            </div>

        </div>

    </div>

    <!-- Pass Issued Modal / Drawer Popover -->
    @if($issuedPass)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-in fade-in duration-200">
            <div class="relative w-full max-w-sm rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 shadow-2xl flex flex-col gap-4 text-xs animate-physical-modal">
                
                <!-- Ticket Pass Mockup Header -->
                <div class="flex items-center justify-between border-b border-[var(--border-hairline)] pb-3">
                    <div class="flex items-center gap-2">
                        <span class="flex size-6 items-center justify-center rounded-md bg-[var(--status-success-bg)] text-[var(--status-success)]">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        </span>
                        <span class="font-bold text-sm text-[var(--text-primary)]">Pass Issued Successfully</span>
                    </div>
                    <button type="button" wire:click="closePassModal" class="cursor-pointer text-[var(--text-tertiary)] hover:text-[var(--text-primary)] p-1 rounded-md">✕</button>
                </div>

                <!-- Tangible Pass Card UI -->
                <div class="rounded-xl border border-dashed border-[var(--border-hairline)] bg-[var(--bg-subtle)] p-4 flex flex-col gap-3 relative overflow-hidden">
                    <!-- Notch left & right -->
                    <div class="absolute -left-2 top-1/2 -translate-y-1/2 size-4 rounded-full bg-[var(--bg-surface)] border border-[var(--border-hairline)]"></div>
                    <div class="absolute -right-2 top-1/2 -translate-y-1/2 size-4 rounded-full bg-[var(--bg-surface)] border border-[var(--border-hairline)]"></div>

                    <div class="flex items-center justify-between text-[11px] text-[var(--text-secondary)]">
                        <span class="font-medium text-[var(--text-primary)]">TicketLab Gate Pass</span>
                        <span class="font-mono font-bold text-[var(--status-success)]">PAID CASH</span>
                    </div>

                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-bold text-[var(--text-primary)]">{{ $issuedPass['attendee_name'] }}</span>
                        <span class="text-[11px] text-[var(--text-secondary)]">{{ $issuedPass['tier_name'] }} · Pass {{ $issuedPass['pass_code'] }}</span>
                    </div>

                    <!-- Clean Vector QR Code Simulation -->
                    <div class="my-1 flex justify-center py-2 bg-white rounded-lg p-2 border border-neutral-200">
                        <svg class="size-28 text-neutral-900" viewBox="0 0 100 100" fill="currentColor">
                            <!-- Outer Position Markers -->
                            <rect x="10" y="10" width="24" height="24" rx="3" />
                            <rect x="14" y="14" width="16" height="16" rx="2" fill="white" />
                            <rect x="18" y="18" width="8" height="8" rx="1" />
                            <rect x="66" y="10" width="24" height="24" rx="3" />
                            <rect x="70" y="14" width="16" height="16" rx="2" fill="white" />
                            <rect x="74" y="18" width="8" height="8" rx="1" />
                            <rect x="10" y="66" width="24" height="24" rx="3" />
                            <rect x="14" y="70" width="16" height="16" rx="2" fill="white" />
                            <rect x="18" y="74" width="8" height="8" rx="1" />
                            <!-- Clean Grid Bits -->
                            <rect x="42" y="12" width="6" height="6" />
                            <rect x="52" y="18" width="6" height="6" />
                            <rect x="42" y="28" width="6" height="6" />
                            <rect x="52" y="38" width="6" height="6" />
                            <rect x="20" y="42" width="6" height="6" />
                            <rect x="30" y="52" width="6" height="6" />
                            <rect x="44" y="48" width="12" height="12" rx="2" />
                            <rect x="62" y="46" width="6" height="6" />
                            <rect x="78" y="42" width="6" height="6" />
                            <rect x="70" y="58" width="6" height="6" />
                            <rect x="42" y="68" width="6" height="6" />
                            <rect x="52" y="78" width="6" height="6" />
                            <rect x="62" y="72" width="6" height="6" />
                            <rect x="78" y="68" width="6" height="6" />
                            <rect x="84" y="82" width="6" height="6" />
                        </svg>
                    </div>

                    <div class="flex items-center justify-between text-[10px] text-[var(--text-tertiary)] border-t border-[var(--border-hairline)] pt-2 font-mono">
                        <span>Change: GHS {{ number_format((float) $issuedPass['change_due'], 2) }}</span>
                        <span>{{ $issuedPass['issued_at'] }}</span>
                    </div>
                </div>

                <!-- Action Buttons: Print & Done -->
                <div class="flex items-center gap-2 pt-1">
                    <button type="button"
                            onclick="window.print()"
                            class="cursor-pointer flex-1 flex items-center justify-center gap-1.5 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] py-2 text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-hover)] transition">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.07-.64-2.11-1.18-3.08-1.07-1.92-2.8-3.32-4.9-3.92M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h10.5a2.25 2.25 0 0 1 2.25 2.25v1.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 10.5V9a2.25 2.25 0 0 1 2.25-2.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 18h12" /></svg>
                        Print Pass
                    </button>

                    <button type="button"
                            wire:click="closePassModal"
                            class="cursor-pointer flex-1 flex items-center justify-center gap-1.5 rounded-xl bg-[var(--text-primary)] text-[var(--bg-canvas)] py-2 text-xs font-semibold hover:opacity-90 transition">
                        Next Customer
                    </button>
                </div>

            </div>
        </div>
    @endif

</div>
