<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="mt-10 pt-8 border-t border-[var(--border-hairline)] space-y-4">
    <div>
        <h3 class="text-sm font-semibold text-[var(--status-danger)]">{{ __('Delete account') }}</h3>
        <p class="text-xs text-[var(--text-secondary)] mt-0.5">{{ __('Delete your account and all of its resources') }}</p>
    </div>

    <div>
        <button
            type="button"
            x-data
            x-on:click="$dispatch('open-modal', 'confirm-user-deletion')"
            class="cursor-pointer rounded-lg border border-[var(--status-danger)]/30 bg-[var(--status-danger)]/10 px-3.5 py-2 text-xs font-semibold text-[var(--status-danger)] hover:bg-[var(--status-danger)]/20 transition"
            data-test="delete-user-button"
        >
            {{ __('Delete account') }}
        </button>
    </div>

    <livewire:pages::settings.delete-user-modal />
</section>
