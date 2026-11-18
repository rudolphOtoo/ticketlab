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
                @if ($organization->logoUrl())
                    <div class="flex items-center gap-3">
                        <img src="{{ $organization->logoUrl() }}" alt="{{ $organization->name }} logo" class="size-12 rounded-lg border border-[var(--border-hairline)] object-contain" />
                        <button type="button" wire:click="removeLogo" wire:confirm="Remove the logo?" class="cursor-pointer text-xs font-semibold text-rose-600">Remove</button>
                    </div>
                @endif
                <input id="org-logo-file" type="file" wire:model="logo" accept="image/jpeg,image/png,image/webp" class="block text-xs" aria-label="Upload logo" />
                <p class="text-[11px] text-[var(--text-tertiary)]">JPG, PNG or WebP, up to 5 MB. Or paste an https link:</p>
                @error('logo') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
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
</div>
