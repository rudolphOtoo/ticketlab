@props([
    'name',
    'title' => null,
    'description' => null,
    'maxWidth' => '2xl',
])

@php
$maxWidthClass = match ($maxWidth) {
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    '3xl' => 'sm:max-w-3xl',
    '4xl' => 'sm:max-w-4xl',
    'full' => 'sm:max-w-full sm:m-4',
    default => 'sm:max-w-2xl',
};
@endphp

<div
    x-data="{ show: false }"
    x-on:open-modal.window="$event.detail === '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail === '{{ $name }}' ? show = false : null"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
    style="display: none;"
>
    {{-- Backdrop --}}
    <div
        x-show="show"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
        x-on:click="show = false"
        aria-hidden="true"
    ></div>

    {{-- Dialog Box --}}
    <div
        x-show="show"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        class="relative mb-6 transform overflow-hidden rounded-box bg-[var(--bg-surface)] border border-[var(--border-hairline)] shadow-xl transition-all sm:mx-auto sm:w-full {{ $maxWidthClass }}"
    >
        @if(filled($title) || filled($description))
            <div class="px-6 pt-6 pb-4 border-b border-[var(--border-hairline)] flex items-start justify-between">
                <div>
                    @if(filled($title))
                        <h3 class="text-lg font-semibold text-[var(--text-primary)] tracking-tight">
                            {{ $title }}
                        </h3>
                    @endif
                    @if(filled($description))
                        <p class="text-xs sm:text-sm text-[var(--text-secondary)] mt-1">
                            {{ $description }}
                        </p>
                    @endif
                </div>

                <button
                    type="button"
                    x-on:click="show = false"
                    class="rounded-md p-1.5 text-[var(--text-tertiary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition-colors"
                >
                    <span class="sr-only">Close</span>
                    <svg class="size-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        @endif

        <div class="p-6">
            {{ $slot }}
        </div>

        @if(isset($footer))
            <div class="px-6 py-4 bg-[var(--bg-subtle)]/50 border-t border-[var(--border-hairline)] flex items-center justify-end gap-3">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
