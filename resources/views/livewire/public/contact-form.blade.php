@php
    $inputClass = 'w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2.5 text-sm text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden';
@endphp

<div class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-xs sm:p-8">
    @if ($sent)
        <div class="space-y-3 py-6 text-center">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600">✓</div>
            <h2 class="text-lg font-bold">Message sent</h2>
            <p class="text-sm text-[var(--text-secondary)]">Thank you for reaching out. We will reply to your email shortly.</p>
            <button type="button" wire:click="$set('sent', false)" class="cursor-pointer text-xs font-semibold text-[var(--text-secondary)] hover:text-[var(--text-primary)]">Send another message</button>
        </div>
    @else
        <form wire:submit="submit" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="contact-name" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Name</label>
                    <input id="contact-name" type="text" wire:model="name" autocomplete="name" class="{{ $inputClass }}" />
                    @error('name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="contact-email" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Email</label>
                    <input id="contact-email" type="email" wire:model="email" autocomplete="email" class="{{ $inputClass }}" />
                    @error('email') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="contact-subject" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Subject</label>
                <input id="contact-subject" type="text" wire:model="subject" class="{{ $inputClass }}" />
                @error('subject') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="contact-message" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Message</label>
                <textarea id="contact-message" rows="6" wire:model="message" class="{{ $inputClass }}"></textarea>
                @error('message') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label for="contact-website">Website</label>
                <input id="contact-website" type="text" wire:model="website" tabindex="-1" autocomplete="off" />
            </div>
            <button type="submit" wire:loading.attr="disabled" class="w-full cursor-pointer rounded-xl bg-[var(--dedicated-accent,var(--color-primary))] px-5 py-3 text-sm font-bold text-white shadow-xs transition hover:opacity-90 press-scale disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">Send Message</span>
                <span wire:loading wire:target="submit">Sending…</span>
            </button>
        </form>
    @endif
</div>
