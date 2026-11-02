<x-layouts::auth :title="__('Email verification')">
    <div class="mt-4 flex flex-col gap-6">
        <p class="text-center text-xs text-[var(--text-secondary)]">
            {{ __('Please verify your email address by clicking on the link we just emailed to you.') }}
        </p>

        @if (session('status') == 'verification-link-sent')
            <p class="text-center text-xs font-medium text-[var(--status-success)]">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </p>
        @endif

        <div class="flex flex-col items-center justify-between gap-3">
            <form method="POST" action="{{ route('verification.send') }}" class="w-full">
                @csrf
                <button
                    type="submit"
                    class="w-full cursor-pointer rounded-lg bg-[var(--accent-brand)] py-2.5 px-4 text-sm font-medium text-[var(--accent-brand-fg)] shadow-xs transition hover:opacity-90 active:scale-[0.98]"
                >
                    {{ __('Resend verification email') }}
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="text-xs text-[var(--text-secondary)] hover:text-[var(--text-primary)] underline underline-offset-4 cursor-pointer transition"
                    data-test="logout-button"
                >
                    {{ __('Log out') }}
                </button>
            </form>
        </div>
    </div>
</x-layouts::auth>
