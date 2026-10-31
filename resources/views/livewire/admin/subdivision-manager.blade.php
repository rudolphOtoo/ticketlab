@php
    $inputClass = 'w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-input)] px-3.5 py-2 text-xs font-medium text-[var(--text-primary)] shadow-[var(--shadow-inset)] focus:border-[var(--border-focused)] focus:outline-hidden focus:ring-2 focus:ring-[var(--border-focused)]/20';
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Subdivisions</h1>
            <p class="mt-0.5 text-xs text-[var(--text-secondary)] sm:text-sm">
                Manage operational regional subdivisions, campuses, or zones for {{ $actor->organization?->name ?? 'your organization' }}.
            </p>
        </div>

        <button type="button" wire:click="openCreateModal" class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-[var(--color-primary-fg)] shadow-xs transition hover:opacity-95 press-scale">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4" /></svg>
            <span>Add Subdivision</span>
        </button>
    </div>

    @if ($flashMessage)
        <div class="flex items-center justify-between rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-xs font-medium text-emerald-800 dark:text-emerald-300">
            <span>{{ $flashMessage }}</span>
            <button type="button" wire:click="$set('flashMessage', null)" class="cursor-pointer">&times;</button>
        </div>
    @endif

    <input
        type="search"
        wire:model.live.debounce.250ms="search"
        placeholder="Search subdivisions..."
        class="w-full max-w-sm rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3.5 py-2 text-xs shadow-xs placeholder-[var(--text-tertiary)] focus:border-[var(--text-primary)] focus:outline-hidden"
    />

    <div class="overflow-x-auto rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-xs">
        <table class="w-full min-w-[640px] text-left text-xs">
            <thead class="border-b border-[var(--border-hairline)] text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                <tr>
                    <th class="px-4 py-3 font-semibold">Subdivision Name</th>
                    <th class="px-4 py-3 font-semibold">Slug Identifier</th>
                    <th class="px-4 py-3 font-semibold">Paystack Subaccount</th>
                    <th class="px-4 py-3 font-semibold">Registered Passes</th>
                    <th class="px-4 py-3 text-right font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subdivisions as $sub)
                    <tr wire:key="sub-{{ $sub->id }}" class="border-b border-[var(--border-hairline)] last:border-b-0 hover:bg-[var(--bg-subtle)] transition">
                        <td class="px-4 py-3 font-semibold text-[var(--text-primary)]">
                            {{ $sub->name }}
                        </td>
                        <td class="px-4 py-3 font-mono text-[11px] text-[var(--text-secondary)]">
                            {{ $sub->slug }}
                        </td>
                        <td class="px-4 py-3 font-mono text-[11px] text-[var(--text-secondary)]">
                            {{ $sub->paystack_subaccount_code ?: 'Default Org Account' }}
                        </td>
                        <td class="px-4 py-3 text-[var(--text-secondary)]">
                            {{ number_format($sub->attendees_count) }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <button type="button" wire:click="editSubdivision({{ $sub->id }})" class="cursor-pointer rounded-lg px-2.5 py-1 font-semibold text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]">Edit</button>
                                <button
                                    type="button"
                                    wire:click="deleteSubdivision({{ $sub->id }})"
                                    wire:confirm="Delete subdivision '{{ $sub->name }}'? Staff assigned to it will be unassigned."
                                    class="cursor-pointer rounded-lg px-2.5 py-1 font-semibold text-rose-600 hover:bg-rose-500/10"
                                >Delete</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-[var(--text-secondary)]">
                            {{ $search !== '' ? 'No subdivisions match your search.' : 'No subdivisions created yet. Add your first subdivision.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $subdivisions->links() }}

    @if ($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs sm:p-6" @keydown.window.escape="$wire.closeModal()">
            <div class="w-full max-w-md space-y-5 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-2xl animate-physical-modal sm:p-7">
                <div class="flex items-center justify-between border-b border-[var(--border-hairline)] pb-3">
                    <div>
                        <h2 class="text-base font-bold sm:text-lg">{{ $editingSubdivisionId ? 'Edit Subdivision' : 'Add Subdivision' }}</h2>
                        <p class="mt-0.5 text-xs text-[var(--text-secondary)]">Create regional chapters, campuses or gate zones.</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="cursor-pointer rounded-lg p-1.5 text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)]" aria-label="Close modal">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label for="sub-name" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Subdivision Name <span class="text-rose-500">*</span></label>
                        <input id="sub-name" type="text" wire:model.live.debounce.300ms="name" placeholder="e.g. Accra Central or Main Arena" class="{{ $inputClass }}" />
                        @error('name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="sub-slug" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Slug Identifier <span class="text-rose-500">*</span></label>
                        <input id="sub-slug" type="text" wire:model="slug" placeholder="e.g. accra-central" class="{{ $inputClass }}" />
                        @error('slug') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="sub-paystack" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Paystack Subaccount Code <span class="text-[var(--text-tertiary)] font-normal">(Optional)</span></label>
                        <input id="sub-paystack" type="text" wire:model="paystackSubaccountCode" placeholder="ACCT_xxxxxxxxx" class="{{ $inputClass }}" />
                        <p class="mt-1 text-[11px] text-[var(--text-tertiary)]">For automatic split payment settlement to this subdivision's bank account.</p>
                        @error('paystackSubaccountCode') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-[var(--border-hairline)] pt-4">
                        <button type="button" wire:click="closeModal" class="cursor-pointer rounded-xl border border-[var(--border-hairline)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] press-scale">Cancel</button>
                        <button type="submit" class="cursor-pointer rounded-xl bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-[var(--color-primary-fg)] shadow-xs press-scale">
                            {{ $editingSubdivisionId ? 'Save Changes' : 'Create Subdivision' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

