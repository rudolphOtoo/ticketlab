@php
    $inputClass = 'w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] outline-hidden transition focus:border-[var(--border-focused)] focus:bg-[var(--bg-surface)] focus:ring-1 focus:ring-[var(--border-focused)]';
@endphp

<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Welcome to TicketLab')"
        :description="__('Set a password to manage :org.', ['org' => $user->organization?->name ?? __('your organisation')])"
    />

    <form wire:submit="accept" class="flex flex-col gap-5">
        <div class="flex flex-col gap-2">
            <span class="text-xs font-medium text-[var(--text-secondary)]">{{ __('Email') }}</span>
            <span class="text-sm font-medium" data-invited-email>{{ $user->email }}</span>
        </div>

        <div class="flex flex-col gap-2">
            <label for="password" class="text-xs font-medium text-[var(--text-secondary)]">{{ __('Password') }}</label>
            <input id="password" type="password" wire:model="password" required autocomplete="new-password" class="{{ $inputClass }}" />
            @error('password') <p class="text-xs text-[var(--status-danger)]">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-2">
            <label for="password_confirmation" class="text-xs font-medium text-[var(--text-secondary)]">{{ __('Confirm password') }}</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" required autocomplete="new-password" class="{{ $inputClass }}" />
        </div>

        <button type="submit" class="w-full cursor-pointer rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-[var(--color-primary-fg)] hover:opacity-90">
            {{ __('Set password and continue') }}
        </button>
    </form>
</div>
