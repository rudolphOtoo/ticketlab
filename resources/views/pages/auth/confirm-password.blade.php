<x-layouts::auth :title="__('Confirm password')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Confirm password')"
            :description="__('This is a secure area of the application. Please confirm your password before continuing.')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify
            options-route="passkey.confirm-options"
            submit-route="passkey.confirm"
            :label="__('Confirm with passkey')"
            :loading-label="__('Confirming...')"
            :separator="__('Or confirm with password')"
        />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-5">
            @csrf

            <div class="flex flex-col gap-2">
                <label for="password" class="text-xs font-medium text-[var(--text-secondary)]">{{ __('Password') }}</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autofocus
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-tertiary)] outline-hidden transition focus:border-[var(--border-focused)] focus:bg-[var(--bg-surface)] focus:ring-1 focus:ring-[var(--border-focused)]"
                />
                @error('password')
                    <p class="text-xs text-[var(--status-danger)]">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="w-full cursor-pointer rounded-lg bg-[var(--accent-brand)] py-2.5 px-4 text-sm font-medium text-[var(--accent-brand-fg)] shadow-xs transition hover:opacity-90 active:scale-[0.98]"
                data-test="confirm-password-button"
            >
                {{ __('Confirm') }}
            </button>
        </form>
    </div>
</x-layouts::auth>
