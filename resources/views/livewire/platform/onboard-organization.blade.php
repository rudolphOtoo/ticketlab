@php
    $inputClass = 'w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs font-medium text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden';
    $labelClass = 'mb-1 block text-[11px] font-semibold text-[var(--text-secondary)]';
    $sectionClass = 'space-y-4 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5';
    $zoneWord = trim($subdivisionLabel) !== '' ? trim($subdivisionLabel) : 'Zone';
@endphp

<form wire:submit="save" class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="{{ route('platform.organizations') }}" wire:navigate class="text-xs text-[var(--text-secondary)] underline">← Organisations</a>
        <h1 class="mt-2 text-xl font-bold tracking-tight sm:text-2xl">Onboard an organisation</h1>
        <p class="mt-0.5 text-xs text-[var(--text-secondary)] sm:text-sm">Creates the organisation, its {{ strtolower(\Illuminate\Support\Str::plural($zoneWord)) }} with their Paystack payout accounts, and invites its first admin by email.</p>
    </div>

    <section class="{{ $sectionClass }}">
        <h2 class="text-sm font-bold">Organisation</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="org-name" class="{{ $labelClass }}">Name</label>
                <input id="org-name" type="text" wire:model.live.debounce.300ms="name" placeholder="e.g. Ghana Methodist Student Union" class="{{ $inputClass }}" />
                @error('name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="org-slug" class="{{ $labelClass }}">Web address</label>
                <div class="flex items-center gap-1 text-xs"><span class="text-[var(--text-tertiary)]">{{ url('/') }}/</span><input id="org-slug" type="text" wire:model.blur="slug" class="{{ $inputClass }}" /></div>
                @error('slug') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="org-label" class="{{ $labelClass }}">What they call a subdivision</label>
                <input id="org-label" type="text" wire:model.live.debounce.300ms="subdivisionLabel" placeholder="Zone, Branch, Campus…" class="{{ $inputClass }}" />
                @error('subdivisionLabel') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="org-email" class="{{ $labelClass }}">Contact email (shown to buyers)</label>
                <input id="org-email" type="email" wire:model="contactEmail" class="{{ $inputClass }}" />
                @error('contactEmail') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="org-phone" class="{{ $labelClass }}">Contact phone</label>
                <input id="org-phone" type="tel" wire:model="contactPhone" placeholder="+233 …" class="{{ $inputClass }}" />
                @error('contactPhone') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <span class="{{ $labelClass }}">Logo (optional)</span>
                <input id="org-logo-file" type="file" wire:model="logo" accept="image/jpeg,image/png,image/webp" class="block text-xs" aria-label="Upload logo" />
                @error('logo') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                <input id="org-logo" type="url" wire:model="logoUrl" placeholder="or paste an https link" aria-label="Logo link" class="mt-1.5 {{ $inputClass }}" />
                @error('logoUrl') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
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
            <p class="text-[11px] text-[var(--text-tertiary)] sm:col-span-2">Currency: GHS (Paystack Ghana).</p>
        </div>
    </section>

    <section class="{{ $sectionClass }}">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-bold">{{ \Illuminate\Support\Str::plural($zoneWord) }} and payout accounts</h2>
            <button type="button" wire:click="addZone" class="cursor-pointer rounded-lg border border-[var(--border-hairline)] px-2.5 py-1 text-xs font-semibold">+ Add {{ strtolower($zoneWord) }}</button>
        </div>
        <p class="text-[11px] text-[var(--text-secondary)]">Each {{ strtolower($zoneWord) }} receives its own ticket money through its Paystack subaccount (ACCT_…). Codes are checked with Paystack before saving. You can leave one blank for now, but that {{ strtolower($zoneWord) }} cannot sell paid tickets until it has a verified code.</p>
        @error('zones') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror

        @foreach ($zones as $index => $zone)
            <div class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]" wire:key="zone-row-{{ $index }}">
                <div>
                    <label for="zone-name-{{ $index }}" class="{{ $labelClass }}">{{ $zoneWord }} name</label>
                    <input id="zone-name-{{ $index }}" type="text" wire:model="zones.{{ $index }}.name" placeholder="e.g. Kumasi" class="{{ $inputClass }}" />
                    @error("zones.{$index}.name") <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="zone-acct-{{ $index }}" class="{{ $labelClass }}">Paystack subaccount</label>
                    <input id="zone-acct-{{ $index }}" type="text" wire:model="zones.{{ $index }}.subaccount" placeholder="ACCT_…" class="{{ $inputClass }} font-mono" />
                    @error("zones.{$index}.subaccount") <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end">
                    @if (count($zones) > 1)
                        <button type="button" wire:click="removeZone({{ $index }})" class="cursor-pointer rounded-lg px-2.5 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-500/10" aria-label="Remove {{ strtolower($zoneWord) }} {{ $index + 1 }}">Remove</button>
                    @endif
                </div>
            </div>
        @endforeach
    </section>

    <section class="{{ $sectionClass }}">
        <h2 class="text-sm font-bold">Organisation admin</h2>
        <p class="text-[11px] text-[var(--text-secondary)]">They get an email with a one-time link (valid {{ \App\Notifications\OrganizationAdminInvitation::INVITATION_DAYS }} days) to set their password. They can then invite {{ strtolower($zoneWord) }} admins and ushers.</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="admin-name" class="{{ $labelClass }}">Full name</label>
                <input id="admin-name" type="text" wire:model="adminName" class="{{ $inputClass }}" />
                @error('adminName') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="admin-email" class="{{ $labelClass }}">Email</label>
                <input id="admin-email" type="email" wire:model="adminEmail" class="{{ $inputClass }}" />
                @error('adminEmail') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <button type="submit" wire:loading.attr="disabled" class="w-full cursor-pointer rounded-xl bg-[var(--color-primary)] py-2.5 text-sm font-semibold text-[var(--color-primary-fg)] hover:opacity-90 disabled:opacity-50">
        <span wire:loading.remove wire:target="save">Create organisation and send invitation</span>
        <span wire:loading wire:target="save">Checking payout accounts with Paystack…</span>
    </button>
</form>
