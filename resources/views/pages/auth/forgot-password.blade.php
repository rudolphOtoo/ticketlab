<x-layouts::auth :title="__('Forgot password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
            @csrf

            <!-- Email Address -->
            <div class="flex flex-col gap-2">
                <label for="email" class="text-xs font-medium text-[var(--text-secondary)]">{{ __('Email address') }}</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    placeholder="email@example.com"
                    class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-tertiary)] outline-hidden transition focus:border-[var(--border-focused)] focus:bg-[var(--bg-surface)] focus:ring-1 focus:ring-[var(--border-focused)]"
                />
                @error('email')
                    <p class="text-xs text-[var(--status-danger)]">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="w-full cursor-pointer rounded-lg bg-[var(--accent-brand)] py-2.5 px-4 text-sm font-medium text-[var(--accent-brand-fg)] shadow-xs transition hover:opacity-90 active:scale-[0.98]"
                data-test="email-password-reset-link-button"
            >
                {{ __('Email password reset link') }}
            </button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-xs text-[var(--text-secondary)]">
            <span>{{ __('Or, return to') }}</span>
            <a href="{{ route('login') }}" class="font-medium text-[var(--text-primary)] underline underline-offset-4 hover:opacity-80 transition" wire:navigate>
                {{ __('log in') }}
            </a>
        </div>
    </div>
</x-layouts::auth>
