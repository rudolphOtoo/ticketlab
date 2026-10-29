@php
    $inputClass = 'w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs font-medium text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden [color-scheme:light] dark:[color-scheme:dark]';
    $roleLabels = ['admin' => 'Admin', 'staff' => 'Staff', 'usher' => 'Usher', 'org_admin' => 'Admin'];
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Staff & Ushers</h1>
            <p class="mt-0.5 text-xs text-[var(--text-secondary)] sm:text-sm">
                Grant admins, event staff and gate ushers access to {{ $actor->organization?->name ?? 'your organization' }}{{ $actor->subdivision ? ' · ' . $actor->subdivision->name : '' }}.
            </p>
        </div>

        <button type="button" wire:click="openCreateModal" class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-[var(--color-primary-fg)] shadow-xs transition hover:opacity-95 press-scale">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4" /></svg>
            <span>Add Team Member</span>
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
        placeholder="Search by name or email..."
        class="w-full max-w-sm rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3.5 py-2 text-xs shadow-xs placeholder-[var(--text-tertiary)] focus:border-[var(--text-primary)] focus:outline-hidden"
    />

    <div class="overflow-x-auto rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-xs">
        <table class="w-full min-w-[640px] text-left text-xs">
            <thead class="border-b border-[var(--border-hairline)] text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                <tr>
                    <th class="px-4 py-3 font-semibold">Member</th>
                    <th class="px-4 py-3 font-semibold">Role</th>
                    <th class="px-4 py-3 font-semibold">Zone</th>
                    <th class="px-4 py-3 text-right font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $member)
                    <tr wire:key="member-{{ $member->id }}" class="border-b border-[var(--border-hairline)] last:border-b-0">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[var(--bg-subtle)] text-[11px] font-semibold">{{ $member->initials() }}</span>
                                <div class="flex min-w-0 flex-col">
                                    <span class="truncate font-semibold">{{ $member->name }}{{ $member->id === $actor->id ? ' (you)' : '' }}</span>
                                    <span class="truncate text-[11px] text-[var(--text-secondary)]">{{ $member->email }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <x-ui.badge :color="match ($member->role) { 'usher' => 'amber', 'staff' => 'sky', default => 'violet' }" variant="outline" pill>
                                {{ $roleLabels[$member->role] ?? ucfirst((string) $member->role) }}
                            </x-ui.badge>
                        </td>
                        <td class="px-4 py-3 text-[var(--text-secondary)]">{{ $member->subdivision?->name ?? 'All zones' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <button type="button" wire:click="editUser({{ $member->id }})" class="cursor-pointer rounded-lg px-2.5 py-1 font-semibold text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]">Edit</button>
                                @if ($member->id !== $actor->id)
                                    <button
                                        type="button"
                                        wire:click="deleteUser({{ $member->id }})"
                                        wire:confirm="Remove {{ $member->name }}? They will lose access immediately."
                                        class="cursor-pointer rounded-lg px-2.5 py-1 font-semibold text-rose-600 hover:bg-rose-500/10"
                                    >Remove</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-12 text-center text-[var(--text-secondary)]">
                            {{ $search !== '' ? 'No team members match your search.' : 'No team members yet. Add your first usher or admin.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $members->links() }}

    @if ($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs sm:p-6" @keydown.window.escape="$wire.closeModal()">
            <div class="w-full max-w-lg space-y-5 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-2xl animate-physical-modal sm:p-7">
                <div class="flex items-center justify-between border-b border-[var(--border-hairline)] pb-3">
                    <div>
                        <h2 class="text-base font-bold sm:text-lg">{{ $editingUserId ? 'Edit Team Member' : 'Add Team Member' }}</h2>
                        <p class="mt-0.5 text-xs text-[var(--text-secondary)]">Role and zone decide which surfaces this person can open.</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="cursor-pointer rounded-lg p-1.5 text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)]">&times;</button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="member-name" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Full Name <span class="text-rose-500">*</span></label>
                            <input id="member-name" type="text" wire:model="name" class="{{ $inputClass }}" />
                            @error('name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="member-email" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Email <span class="text-rose-500">*</span></label>
                            <input id="member-email" type="email" wire:model="email" class="{{ $inputClass }}" />
                            @error('email') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="member-password" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">
                            Password @if (!$editingUserId)<span class="text-rose-500">*</span>@else<span class="font-normal text-[var(--text-tertiary)]">(leave blank to keep current)</span>@endif
                        </label>
                        <input id="member-password" type="password" autocomplete="new-password" wire:model="password" class="{{ $inputClass }}" />
                        @error('password') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="member-role" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Role <span class="text-rose-500">*</span></label>
                            <select id="member-role" wire:model="role" class="{{ $inputClass }} cursor-pointer" @disabled($editingUserId === $actor->id)>
                                @foreach ($roles as $roleOption)
                                    <option value="{{ $roleOption }}">{{ $roleLabels[$roleOption] }}</option>
                                @endforeach
                            </select>
                            @error('role') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="member-zone" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Zone</label>
                            <select id="member-zone" wire:model="subdivisionId" class="{{ $inputClass }} cursor-pointer" @disabled($actor->subdivision_id !== null)>
                                @if ($actor->subdivision_id === null)
                                    <option value="">All zones</option>
                                @endif
                                @foreach ($subdivisions as $zone)
                                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                @endforeach
                            </select>
                            @error('subdivisionId') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <p class="rounded-xl bg-[var(--bg-subtle)] px-3 py-2 text-[11px] leading-relaxed text-[var(--text-secondary)]">
                        <strong>Admins</strong> manage events, finances and the team. <strong>Staff</strong> run events without team access. <strong>Ushers</strong> only see the door scanner and cash desk.
                    </p>

                    <div class="flex items-center justify-end gap-3 border-t border-[var(--border-hairline)] pt-4">
                        <button type="button" wire:click="closeModal" class="cursor-pointer rounded-xl border border-[var(--border-hairline)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] press-scale">Cancel</button>
                        <button type="submit" class="cursor-pointer rounded-xl bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-[var(--color-primary-fg)] shadow-xs press-scale">
                            {{ $editingUserId ? 'Save Changes' : 'Create Account' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
