<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Register for staff & gate terminal access')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-4">
            @csrf

            <!-- Name -->
            <div class="flex flex-col gap-1.5">
                <label for="name" class="text-xs font-medium text-[var(--text-secondary)]">{{ __('Full Name') }}</label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name') }}"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Kwame Mensah"
                    class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-tertiary)] outline-hidden transition focus:border-[var(--border-focused)] focus:bg-[var(--bg-surface)] focus:ring-1 focus:ring-[var(--border-focused)]"
                />
                @error('name')
                    <p class="text-xs text-[var(--status-danger)]">{{ $message }}</p>
                @enderror
            </div>

            <!-- Email Address -->
            <div class="flex flex-col gap-1.5">
                <label for="email" class="text-xs font-medium text-[var(--text-secondary)]">{{ __('Email address') }}</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autocomplete="email"
                    placeholder="usher@summit.org"
                    class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-tertiary)] outline-hidden transition focus:border-[var(--border-focused)] focus:bg-[var(--bg-surface)] focus:ring-1 focus:ring-[var(--border-focused)]"
                />
                @error('email')
                    <p class="text-xs text-[var(--status-danger)]">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password -->
            <div class="flex flex-col gap-1.5">
                <label for="password" class="text-xs font-medium text-[var(--text-secondary)]">{{ __('Password') }}</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="••••••••"
                    class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-tertiary)] outline-hidden transition focus:border-[var(--border-focused)] focus:bg-[var(--bg-surface)] focus:ring-1 focus:ring-[var(--border-focused)]"
                />
                @error('password')
                    <p class="text-xs text-[var(--status-danger)]">{{ $message }}</p>
                @enderror
            </div>

            <!-- Confirm Password -->
            <div class="flex flex-col gap-1.5">
                <label for="password_confirmation" class="text-xs font-medium text-[var(--text-secondary)]">{{ __('Confirm password') }}</label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="••••••••"
                    class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-tertiary)] outline-hidden transition focus:border-[var(--border-focused)] focus:bg-[var(--bg-surface)] focus:ring-1 focus:ring-[var(--border-focused)]"
                />
                @error('password_confirmation')
                    <p class="text-xs text-[var(--status-danger)]">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-2">
                <button
                    type="submit"
                    class="w-full cursor-pointer rounded-lg bg-[var(--accent-brand)] py-2.5 px-4 text-sm font-medium text-[var(--accent-brand-fg)] shadow-xs transition hover:opacity-90 active:scale-[0.98]"
                    data-test="register-user-button"
                >
                    {{ __('Create Staff Account') }}
                </button>
            </div>
        </form>

        <div class="text-xs text-center text-[var(--text-secondary)]">
            <span>{{ __('Already have an account?') }}</span>
            <a href="{{ route('login') }}" class="font-medium text-[var(--text-primary)] underline underline-offset-4 hover:opacity-80" wire:navigate>
                {{ __('Sign in') }}
            </a>
        </div>
    </div>
</x-layouts::auth>
