@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center gap-1.5">
    <h1 class="text-xl font-semibold tracking-tight text-[var(--text-primary)]">{{ $title }}</h1>
    <p class="text-xs text-[var(--text-secondary)]">{{ $description }}</p>
</div>
