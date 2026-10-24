@props([
    'sidebar' => false,
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-zinc-900 dark:bg-white text-white dark:text-zinc-900">
        <x-app-logo-icon class="size-5 fill-current text-white dark:text-zinc-900" />
    </div>
    <span class="font-semibold text-zinc-900 dark:text-white truncate">{{ config('app.name', 'TicketLab') }}</span>
</div>
