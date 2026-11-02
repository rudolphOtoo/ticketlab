@props([
    'sidebar' => false,
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2.5']) }}>
    <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-[var(--bg-subtle)] border border-[var(--border-hairline)] text-[var(--text-primary)] shadow-xs">
        <x-app-logo-icon class="size-4.5" />
    </div>
    <div class="flex flex-col">
        <span class="text-sm font-bold tracking-tight text-[var(--text-primary)] leading-tight">TicketLab</span>
        <span class="text-[10px] font-medium tracking-wider uppercase text-[var(--text-secondary)] leading-none">Studio</span>
    </div>
</div>
