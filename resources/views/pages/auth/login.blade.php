<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password to access the ops terminal')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
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
                    autocomplete="email"
                    placeholder="usher@summit.org"
                    class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-tertiary)] outline-hidden transition focus:border-[var(--border-focused)] focus:bg-[var(--bg-surface)] focus:ring-1 focus:ring-[var(--border-focused)]"
                />
                @error('email')
                    <p class="text-xs text-[var(--status-danger)]">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password -->
            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between">
                    <label for="password" class="text-xs font-medium text-[var(--text-secondary)]">{{ __('Password') }}</label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-xs text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition" wire:navigate>
                            {{ __('Forgot password?') }}
                        </a>
                    @endif
                </div>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] placeholder-[var(--text-tertiary)] outline-hidden transition focus:border-[var(--border-focused)] focus:bg-[var(--bg-surface)] focus:ring-1 focus:ring-[var(--border-focused)]"
                />
                @error('password')
                    <p class="text-xs text-[var(--status-danger)]">{{ $message }}</p>
                @enderror
            </div>

            <!-- Remember Me -->
            <div class="flex items-center gap-2">
                <input
                    id="remember"
                    name="remember"
                    type="checkbox"
                    {{ old('remember') ? 'checked' : '' }}
                    class="h-4 w-4 rounded-sm border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[var(--accent-brand)] focus:ring-1 focus:ring-[var(--border-focused)]"
                />
                <label for="remember" class="text-xs text-[var(--text-secondary)]">{{ __('Remember this device') }}</label>
            </div>

            <div class="pt-2">
                <button
                    type="submit"
                    class="w-full cursor-pointer rounded-lg bg-[var(--accent-brand)] py-2.5 px-4 text-sm font-medium text-[var(--accent-brand-fg)] shadow-xs transition hover:opacity-90 active:scale-[0.98]"
                    data-test="login-button"
                >
                    {{ __('Sign In to Operations') }}
                </button>
            </div>
        </form>

        <div class="text-xs text-center text-[var(--text-secondary)]">
            <span>{{ __('Need staff access?') }}</span>
            <a href="{{ route('register') }}" class="font-medium text-[var(--text-primary)] underline underline-offset-4 hover:opacity-80" wire:navigate>
                {{ __('Register staff account') }}
            </a>
        </div>
    </div>
</x-layouts::auth>
