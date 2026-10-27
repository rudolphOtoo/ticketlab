<div class="space-y-6">
    <!-- Header & Action Ribbon -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-[var(--text-primary)]">
                Ticket Tiers & Pricing
            </h1>
            <p class="text-xs sm:text-sm text-[var(--text-secondary)] mt-0.5">
                Configure ticket categories, prices in GHS, capacities, perks, and active sales windows.
            </p>
        </div>

        <div class="flex items-center gap-3">
            @if ($events->count() > 1)
                <select
                    wire:model.live="selectedEventId"
                    class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-2 text-xs font-semibold text-[var(--text-primary)] shadow-xs focus:outline-hidden [color-scheme:light] dark:[color-scheme:dark] cursor-pointer"
                >
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}">{{ $event->title }}</option>
                    @endforeach
                </select>
            @endif

            <button
                type="button"
                wire:click="openCreateModal"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold hover:opacity-95 transition shadow-xs press-scale cursor-pointer"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Add Ticket Tier</span>
            </button>
        </div>
    </div>

    <!-- Flash Alert -->
    @if ($flashMessage)
        <div class="flex items-center justify-between rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-xs font-medium text-emerald-800 dark:text-emerald-300">
            <div class="flex items-center gap-2">
                <svg class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ $flashMessage }}</span>
            </div>
            <button type="button" wire:click="$set('flashMessage', null)" class="text-emerald-700 hover:text-emerald-900 dark:hover:text-emerald-100 cursor-pointer">
                &times;
            </button>
        </div>
    @endif

    <!-- Search & Quick Filters -->
    <div class="flex items-center justify-between gap-4">
        <div class="relative w-full max-w-sm">
            <input
                type="text"
                wire:model.live.debounce.250ms="search"
                placeholder="Search ticket tiers..."
                class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3.5 py-2 pl-9 text-xs text-[var(--text-primary)] placeholder-[var(--text-tertiary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden transition"
            />
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[var(--text-tertiary)]">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
        </div>

        <span class="text-xs text-[var(--text-secondary)] font-medium">
            {{ $ticketTypes->count() }} {{ \Illuminate\Support\Str::plural('tier', $ticketTypes->count()) }} configured
        </span>
    </div>

    <!-- Ticket Tiers Grid -->
    @if ($ticketTypes->isEmpty())
        <div class="rounded-3xl border border-dashed border-[var(--border-hairline)] bg-[var(--bg-surface)] p-12 text-center">
            <div class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-[var(--bg-subtle)] text-[var(--text-tertiary)]">
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>
            </div>
            <h3 class="mt-4 text-sm font-semibold text-[var(--text-primary)]">No ticket tiers found</h3>
            <p class="mt-1 text-xs text-[var(--text-secondary)] max-w-sm mx-auto">
                {{ $search !== '' ? 'No categories matched your search criteria.' : 'Create your first ticket category to allow patrons to purchase passes online.' }}
            </p>
            @if ($search === '')
                <button
                    type="button"
                    wire:click="openCreateModal"
                    class="mt-5 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold shadow-xs press-scale cursor-pointer"
                >
                    + Create First Tier
                </button>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($ticketTypes as $tier)
                @php
                    $sold = max(0, $tier->total_capacity - $tier->available_quantity);
                    $percentSold = $tier->total_capacity > 0 ? min(100, round(($sold / $tier->total_capacity) * 100)) : 0;
                @endphp
                <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 shadow-xs flex flex-col justify-between subtle-hover-glow transition relative overflow-hidden group">
                    <div class="space-y-4">
                        <!-- Top Meta -->
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span class="text-[11px] font-mono font-medium text-[var(--text-tertiary)] uppercase tracking-wider">
                                    Tier #{{ $tier->id }}
                                </span>
                                <h3 class="text-base font-bold text-[var(--text-primary)] mt-0.5">
                                    {{ $tier->name }}
                                </h3>
                            </div>

                            <button
                                type="button"
                                wire:click="toggleActive({{ $tier->id }})"
                                class="cursor-pointer inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold transition {{ $tier->is_active ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-400' }}"
                                title="Click to {{ $tier->is_active ? 'pause' : 'resume' }} sales"
                            >
                                <span class="size-1.5 rounded-full {{ $tier->is_active ? 'bg-emerald-500 animate-pulse' : 'bg-zinc-400' }}"></span>
                                <span>{{ $tier->is_active ? 'Active' : 'Paused' }}</span>
                            </button>
                        </div>

                        <!-- Price Tag -->
                        <div class="flex items-baseline gap-1">
                            <span class="text-xs font-semibold text-[var(--text-secondary)]">GHS</span>
                            <span class="text-2xl font-black text-[var(--text-primary)] tracking-tight">
                                {{ number_format((float) $tier->price, 2) }}
                            </span>
                        </div>

                        @if ($tier->description)
                            <p class="text-xs text-[var(--text-secondary)] line-clamp-2 leading-relaxed">
                                {{ $tier->description }}
                            </p>
                        @endif

                        <!-- Capacity Bar -->
                        <div class="space-y-1.5 pt-2 border-t border-[var(--border-hairline)]">
                            <div class="flex items-center justify-between text-[11px] font-medium text-[var(--text-secondary)]">
                                <span>Capacity</span>
                                <span><strong class="text-[var(--text-primary)]">{{ $sold }}</strong> / {{ $tier->total_capacity }} sold ({{ $percentSold }}%)</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-[var(--bg-subtle)]">
                                <div
                                    class="h-full rounded-full transition-all duration-300 {{ $percentSold >= 100 ? 'bg-rose-500' : ($percentSold >= 80 ? 'bg-amber-500' : 'bg-[var(--color-primary)]') }}"
                                    style="width: {{ $percentSold }}%"
                                ></div>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-[var(--text-tertiary)] pt-0.5">
                                <span>{{ $tier->available_quantity }} remaining</span>
                                <span>{{ $tier->attendees_count }} checked-in</span>
                            </div>
                        </div>

                        <!-- Perks Checklist -->
                        @if (!empty($tier->perks))
                            <div class="space-y-1.5 pt-2 border-t border-[var(--border-hairline)]">
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">Included Perks</span>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($tier->perks as $perk)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-[var(--bg-subtle)] text-[10px] font-medium text-[var(--text-secondary)]">
                                            <svg class="size-2.5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                            </svg>
                                            {{ $perk }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Bottom Action Ribbon -->
                    <div class="flex items-center justify-between pt-4 mt-4 border-t border-[var(--border-hairline)]">
                        <span class="text-[10px] text-[var(--text-tertiary)]">
                            @if ($tier->sales_end_at)
                                Closes {{ $tier->sales_end_at->format('M d, Y') }}
                            @else
                                Open indefinitely
                            @endif
                        </span>

                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                wire:click="editTicketType({{ $tier->id }})"
                                class="p-1.5 rounded-lg border border-[var(--border-hairline)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition press-scale cursor-pointer"
                                title="Edit Tier"
                            >
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>

                            @if ($sold === 0)
                                <button
                                    type="button"
                                    wire:click="deleteTicketType({{ $tier->id }})"
                                    wire:confirm="Are you sure you want to delete this ticket tier? This action cannot be undone."
                                    class="p-1.5 rounded-lg border border-[var(--border-hairline)] text-[var(--text-secondary)] hover:text-rose-600 hover:border-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition press-scale cursor-pointer"
                                    title="Delete Tier"
                                >
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Slide-Over / Modal Form -->
    @if ($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-black/50 backdrop-blur-xs">
            <div class="w-full max-w-lg rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-2xl p-6 sm:p-7 space-y-5 animate-physical-modal">
                <div class="flex items-center justify-between pb-3 border-b border-[var(--border-hairline)]">
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-[var(--text-primary)]">
                            {{ $editingTicketTypeId ? 'Edit Ticket Category' : 'Create New Ticket Category' }}
                        </h2>
                        <p class="text-xs text-[var(--text-secondary)] mt-0.5">
                            Set capacity, price, perks, and availability rules.
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="p-1.5 rounded-lg text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition cursor-pointer"
                    >
                        <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                            Tier Name <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="name"
                            wire:model="name"
                            placeholder="e.g. VIP Patron, Early Bird, General Admission"
                            class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs font-medium text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden"
                        />
                        @error('name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                            Description
                        </label>
                        <textarea
                            id="description"
                            wire:model="description"
                            rows="2"
                            placeholder="What does this pass grant access to?"
                            class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs font-medium text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden"
                        ></textarea>
                        @error('description') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <!-- Price & Capacity Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="price" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                                Gross Price (GHS) <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="price"
                                wire:model="price"
                                placeholder="0.00"
                                class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs font-bold font-mono text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden"
                            />
                            @error('price') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="total_capacity" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                                Total Capacity <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="number"
                                min="1"
                                id="total_capacity"
                                wire:model="total_capacity"
                                class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs font-bold font-mono text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden"
                            />
                            @error('total_capacity') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Sales Window Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="sales_start_at" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                                Sales Start Date
                            </label>
                            <input
                                type="datetime-local"
                                id="sales_start_at"
                                wire:model="sales_start_at"
                                class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden [color-scheme:light] dark:[color-scheme:dark]"
                            />
                            @error('sales_start_at') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="sales_end_at" class="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                                Sales End Date
                            </label>
                            <input
                                type="datetime-local"
                                id="sales_end_at"
                                wire:model="sales_end_at"
                                class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-2 text-xs text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden [color-scheme:light] dark:[color-scheme:dark]"
                            />
                            @error('sales_end_at') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Perks Array Builder -->
                    <div class="space-y-2 pt-2 border-t border-[var(--border-hairline)]">
                        <label class="block text-xs font-semibold text-[var(--text-secondary)]">
                            Perks Checklist
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                wire:model="newPerkInput"
                                wire:keydown.enter.prevent="addPerk"
                                placeholder="Type a perk and click Add (e.g. Lunch Included)"
                                class="flex-1 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-1.5 text-xs text-[var(--text-primary)] placeholder-[var(--text-tertiary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden"
                            />
                            <button
                                type="button"
                                wire:click="addPerk"
                                class="px-3 py-1.5 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-hover)] transition press-scale cursor-pointer"
                            >
                                Add Perk
                            </button>
                        </div>

                        @if (!empty($perks))
                            <div class="flex flex-wrap gap-2 pt-1">
                                @foreach ($perks as $index => $perk)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[var(--bg-subtle)] border border-[var(--border-hairline)] text-xs text-[var(--text-primary)]">
                                        <span>{{ $perk }}</span>
                                        <button
                                            type="button"
                                            wire:click="removePerk({{ $index }})"
                                            class="text-[var(--text-tertiary)] hover:text-rose-500 cursor-pointer"
                                        >
                                            &times;
                                        </button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Active Toggle -->
                    <div class="flex items-center gap-2.5 pt-2">
                        <input
                            type="checkbox"
                            id="is_active"
                            wire:model="is_active"
                            class="size-4 rounded-md border-[var(--border-hairline)] text-[var(--color-primary)] focus:ring-[var(--color-primary)] cursor-pointer"
                        />
                        <label for="is_active" class="text-xs font-semibold text-[var(--text-primary)] cursor-pointer">
                            Active for public purchasing immediately
                        </label>
                    </div>

                    <!-- Buttons -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-[var(--border-hairline)]">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2 rounded-xl border border-[var(--border-hairline)] text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] transition press-scale cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-semibold hover:opacity-95 transition shadow-xs press-scale cursor-pointer"
                        >
                            {{ $editingTicketTypeId ? 'Save Changes' : 'Create Category' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
