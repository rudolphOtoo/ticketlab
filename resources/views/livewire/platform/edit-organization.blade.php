@php
    $inputClass = 'w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs font-medium text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden';
    $labelClass = 'mb-1 block text-[11px] font-semibold text-[var(--text-secondary)]';
    $sectionClass = 'space-y-4 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5';
    $zoneWord = trim($organization->subdivision_label) !== '' ? $organization->subdivision_label : 'Zone';
@endphp

<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="{{ route('platform.organizations') }}" wire:navigate class="text-xs text-[var(--text-secondary)] underline">← Organisations</a>
        <h1 class="mt-2 text-xl font-bold tracking-tight sm:text-2xl">{{ $organization->name }}</h1>
        <p class="mt-0.5 text-xs text-[var(--text-secondary)]">Web address <span class="font-mono">/{{ $organization->slug }}</span> (not editable here: it is printed on tickets and shared links).</p>
    </div>

    @if ($flashMessage)
        <div class="flex items-center justify-between rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-xs font-medium text-emerald-800 dark:text-emerald-300">
            <span>{{ $flashMessage }}</span>
            <button type="button" wire:click="$set('flashMessage', null)" class="cursor-pointer">&times;</button>
        </div>
    @endif

    <form wire:submit="save" class="{{ $sectionClass }}">
        <h2 class="text-sm font-bold">Identity</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="org-name" class="{{ $labelClass }}">Name</label>
                <input id="org-name" type="text" wire:model="name" class="{{ $inputClass }}" />
                @error('name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="org-label" class="{{ $labelClass }}">What they call a subdivision</label>
                <input id="org-label" type="text" wire:model="subdivisionLabel" class="{{ $inputClass }}" />
                @error('subdivisionLabel') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="org-email" class="{{ $labelClass }}">Contact email</label>
                <input id="org-email" type="email" wire:model="contactEmail" class="{{ $inputClass }}" />
                @error('contactEmail') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="org-phone" class="{{ $labelClass }}">Contact phone</label>
                <input id="org-phone" type="tel" wire:model="contactPhone" class="{{ $inputClass }}" />
                @error('contactPhone') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2 space-y-2" data-logo-field>
                <span class="{{ $labelClass }}">Logo</span>
                @if ($this->pendingImage('logo'))
                    <div class="flex items-center gap-3">
                        <x-media-image :value="$this->pendingImage('logo')" preset="logo" alt="New logo (save to use it)" class="size-12 rounded-lg border border-[var(--border-hairline)] object-contain" data-logo-preview />
                        <span class="text-[11px] text-[var(--text-tertiary)]">New logo. Save to use it.</span>
                    </div>
                @elseif ($organization->logo_url)
                    <div class="flex items-center gap-3">
                        <x-media-image :value="$organization->logo_url" preset="logo" :alt="$organization->name . ' logo'" class="size-12 rounded-lg border border-[var(--border-hairline)] object-contain" />
                        <button type="button" wire:click="removeLogo" wire:confirm="Remove the logo?" class="cursor-pointer text-xs font-semibold text-rose-600">Remove</button>
                    </div>
                @endif
                <x-image-upload field="logo" id="org-logo-file" label="Upload logo" />
                <p class="text-[11px] text-[var(--text-tertiary)]">Or paste an https link:</p>
                <input id="org-logo" type="url" wire:model="logoUrl" placeholder="https://…" aria-label="Logo link" class="{{ $inputClass }}" />
                @error('logoUrl') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="org-color" class="{{ $labelClass }}">Brand colour</label>
                <input id="org-color" type="color" wire:model="brandColor" class="h-9 w-full cursor-pointer rounded-xl border border-[var(--border-hairline)]" />
                @error('brandColor') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="org-tz" class="{{ $labelClass }}">Timezone</label>
                <input id="org-tz" type="text" wire:model="timezone" class="{{ $inputClass }}" />
                @error('timezone') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
        </div>
        <button type="submit" class="cursor-pointer rounded-xl bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-[var(--color-primary-fg)] hover:opacity-90">Save</button>
    </form>

    <section class="{{ $sectionClass }}">
        <h2 class="text-sm font-bold">{{ \Illuminate\Support\Str::plural($zoneWord) }}</h2>
        <ul class="divide-y divide-[var(--border-hairline)] text-xs" data-zone-list>
            @forelse ($zones as $zone)
                <li class="flex items-center justify-between py-2">
                    <span class="font-medium">{{ $zone->name }}</span>
                    @if ($subaccounts->forSubdivision($zone) !== null)
                        <span class="text-[var(--status-success)]">Payout account verified</span>
                    @else
                        <span class="text-[var(--status-warning)]">No verified payout account</span>
                    @endif
                </li>
            @empty
                <li class="py-2 text-[var(--text-secondary)]">None yet.</li>
            @endforelse
        </ul>

        <form wire:submit="addZone" class="grid gap-3 border-t border-[var(--border-hairline)] pt-4 sm:grid-cols-[1fr_1fr_auto]">
            <div>
                <label for="new-zone" class="{{ $labelClass }}">New {{ strtolower($zoneWord) }}</label>
                <input id="new-zone" type="text" wire:model="newZoneName" class="{{ $inputClass }}" />
                @error('newZoneName') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="new-zone-acct" class="{{ $labelClass }}">Paystack subaccount (optional)</label>
                <input id="new-zone-acct" type="text" wire:model="newZoneSubaccount" placeholder="ACCT_…" class="{{ $inputClass }} font-mono" />
                @error('newZoneSubaccount') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full cursor-pointer rounded-xl border border-[var(--border-hairline)] px-3 py-2 text-xs font-semibold">Add</button>
            </div>
        </form>
        <p class="text-[11px] text-[var(--text-tertiary)]">Renaming {{ strtolower(\Illuminate\Support\Str::plural($zoneWord)) }} and changing payout accounts is done by the organisation admin under {{ $zoneWord }} Payouts.</p>
    </section>

    <section class="{{ $sectionClass }}" data-organization-status>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-bold">Status</h2>
            <x-ui.badge :color="match ($organization->status) { \App\Enums\OrganizationStatus::Active => 'emerald', \App\Enums\OrganizationStatus::Suspended => 'amber', default => 'zinc' }" pill size="sm">{{ $organization->status->label() }}</x-ui.badge>
        </div>
        <p class="text-xs text-[var(--text-secondary)]">
            Suspending makes its public pages unavailable, stops sales and checkout, signs its users out and keeps them out, and the scheduler skips it. Archiving does the same and hides it from the organisation list. Nothing is deleted, ticket links keep working, and either can be undone.
        </p>
        <div>
            <label for="status-reason" class="{{ $labelClass }}">Reason (required, kept in the audit trail)</label>
            <textarea id="status-reason" wire:model="statusReason" rows="2" maxlength="1000" class="{{ $inputClass }}"></textarea>
            @error('statusReason') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
        </div>
        <div class="flex flex-wrap gap-2 text-xs font-semibold">
            @unless ($organization->isActive())
                <button type="button" wire:click="changeStatus('active')" class="cursor-pointer rounded-xl bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-700">Reactivate</button>
            @endunless
            @unless ($organization->status === \App\Enums\OrganizationStatus::Suspended)
                <button type="button" wire:click="changeStatus('suspended')" wire:confirm="Suspend {{ $organization->name }}? Its pages go offline, sales stop and its users are signed out." class="cursor-pointer rounded-xl border border-amber-500/40 px-4 py-2 text-amber-700 hover:bg-amber-500/10 dark:text-amber-300">Suspend</button>
            @endunless
            @unless ($organization->status === \App\Enums\OrganizationStatus::Archived)
                <button type="button" wire:click="changeStatus('archived')" wire:confirm="Archive {{ $organization->name }}? Like suspending, and it is hidden from the organisation list." class="cursor-pointer rounded-xl border border-[var(--border-hairline)] px-4 py-2 text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)]">Archive</button>
            @endunless
        </div>

        <div class="space-y-3 border-t border-[var(--border-hairline)] pt-4" data-delete-organization>
            <h3 class="text-xs font-bold text-rose-600">Delete organisation</h3>
            @if ($deleteBlocker !== null)
                <p class="text-xs text-[var(--text-secondary)]" data-delete-blocked>{{ $deleteBlocker }}</p>
            @else
                <p class="text-xs text-[var(--text-secondary)]">It has no orders or payments. Deleting removes its {{ strtolower(\Illuminate\Support\Str::plural($zoneWord)) }}, users, events and images for good. Its web address stays reserved, so no other organisation can take it. This can't be undone.</p>
                <form wire:submit="deleteOrganization" class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="delete-reason" class="{{ $labelClass }}">Reason (required)</label>
                        <input id="delete-reason" type="text" wire:model="deleteReason" maxlength="1000" class="{{ $inputClass }}" />
                        @error('deleteReason') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="delete-confirmation" class="{{ $labelClass }}">Type <strong class="select-all font-mono text-[var(--text-primary)]">{{ $organization->slug }}</strong> to confirm</label>
                        <input id="delete-confirmation" type="text" wire:model="deleteConfirmation" autocomplete="off" class="{{ $inputClass }} font-mono" />
                        @error('deleteConfirmation') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="cursor-pointer rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-700">Delete {{ $organization->name }}</button>
                    </div>
                </form>
            @endif
        </div>

        @if ($history->isNotEmpty())
            <div class="space-y-2 border-t border-[var(--border-hairline)] pt-4">
                <h3 class="text-xs font-bold">History</h3>
                <ul class="space-y-2 text-xs" data-platform-audit>
                    @foreach ($history as $entry)
                        <li>
                            <span class="font-semibold">{{ \Illuminate\Support\Str::of($entry->action)->after('organization.')->headline() }}</span>
                            by {{ $entry->user?->name ?? 'a removed user' }} · {{ $entry->created_at->format('M j, Y · g:i A') }}
                            <span class="block text-[var(--text-secondary)]">{{ $entry->reason }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
</div>
