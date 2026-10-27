@props([
    'animate' => 'pulse', // pulse, shimmer, none
    'rounded' => 'rounded-md',
])

@php
$animationClasses = match($animate) {
    'shimmer' => 'relative overflow-hidden before:absolute before:inset-0 before:-translate-x-full before:animate-[shimmer_1.6s_infinite] before:bg-gradient-to-r before:from-transparent before:via-white/20 dark:before:via-white/10 before:to-transparent',
    'pulse' => 'animate-pulse',
    'none' => '',
    default => 'animate-pulse',
};
@endphp

<div
    {{ $attributes->merge([
        'class' => "bg-[var(--bg-subtle)] dark:bg-[var(--bg-hover)] {$rounded} {$animationClasses}"
    ]) }}
    aria-hidden="true"
></div>
