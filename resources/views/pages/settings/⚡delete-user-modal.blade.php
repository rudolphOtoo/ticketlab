<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<x-ui.modal name="confirm-user-deletion" maxWidth="md">
    <div class="space-y-4">
        <div>
            <h3 class="text-base font-semibold text-[var(--text-primary)]">
                {{ __('Are you sure you want to delete your account?') }}
            </h3>
            <p class="mt-1.5 text-xs leading-relaxed text-[var(--text-secondary)]">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>
        </div>

        <form method="POST" wire:submit="deleteUser" class="space-y-4">
            <div class="flex flex-col gap-1.5">
                <label for="delete_password" class="text-xs font-medium text-[var(--text-secondary)]">{{ __('Password') }}</label>
                <input
                    id="delete_password"
                    wire:model="password"
                    type="password"
                    required
                    placeholder="••••••••"
                    class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3 py-2 text-xs text-[var(--text-primary)] placeholder-[var(--text-tertiary)] outline-hidden transition focus:border-[var(--border-focused)] focus:bg-[var(--bg-surface)]"
                />
                @error('password')
                    <p class="text-xs text-[var(--status-danger)]">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button
                    type="button"
                    x-on:click="$dispatch('close-modal', 'confirm-user-deletion')"
                    class="cursor-pointer rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3.5 py-2 text-xs font-medium text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)] transition"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="submit"
                    class="cursor-pointer rounded-lg bg-[var(--status-danger)] px-3.5 py-2 text-xs font-semibold text-white hover:opacity-90 transition"
                    data-test="confirm-delete-user-button"
                >
                    {{ __('Delete account') }}
                </button>
            </div>
        </form>
    </div>
</x-ui.modal>
