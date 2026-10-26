<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Appearance settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div class="flex items-center gap-2 rounded-xl bg-[var(--bg-subtle)] p-1 text-xs font-semibold w-fit">
            <button type="button"
                    onclick="window.toggleStudioTheme(event, 'light')"
                    class="cursor-pointer rounded-lg px-3 py-1.5 transition text-[var(--text-secondary)] hover:text-[var(--text-primary)]">
                Light
            </button>
            <button type="button"
                    onclick="window.toggleStudioTheme(event, 'dark')"
                    class="cursor-pointer rounded-lg px-3 py-1.5 transition text-[var(--text-secondary)] hover:text-[var(--text-primary)]">
                Dark
            </button>
        </div>
    </x-pages::settings.layout>
</section>
