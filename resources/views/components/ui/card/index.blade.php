@props([
    'title' => null,
    'description' => null,
    'badge' => null,
    'actions' => null,
    'padding' => 'p-5 sm:p-6',
])

<div {{ $attributes->merge([
    'class' => "bg-[var(--bg-surface)] border border-[var(--border-hairline)] rounded-box shadow-xs transition-shadow duration-200"
]) }}>
    @if(filled($title) || filled($description) || filled($actions) || filled($badge) || isset($header))
        <div class="px-5 pt-5 sm:px-6 sm:pt-6 pb-4 border-b border-[var(--border-hairline)] flex items-start justify-between gap-4">
            @if(isset($header))
                {{ $header }}
            @else
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        @if(filled($title))
                            <h3 class="text-base font-semibold text-[var(--text-primary)] tracking-tight">
                                {{ $title }}
                            </h3>
                        @endif
                        @if(filled($badge))
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-[var(--bg-subtle)] text-[var(--text-secondary)]">
                                {{ $badge }}
                            </span>
                        @endif
                    </div>
                    @if(filled($description))
                        <p class="text-xs sm:text-sm text-[var(--text-secondary)]">
                            {{ $description }}
                        </p>
                    @endif
                </div>

                @if(filled($actions) || isset($actionsSlot))
                    <div class="flex items-center gap-2">
                        {{ $actions ?? $actionsSlot }}
                    </div>
                @endif
            @endif
        </div>
    @endif

    <div class="{{ $padding }}">
        {{ $slot }}
    </div>

    @if(isset($footer))
        <div class="px-5 py-3 sm:px-6 bg-[var(--bg-subtle)]/50 border-t border-[var(--border-hairline)] rounded-b-box flex items-center justify-between">
            {{ $footer }}
        </div>
    @endif
</div>
