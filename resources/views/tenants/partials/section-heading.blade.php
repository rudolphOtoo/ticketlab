<div class="space-y-2 text-center">
    <span class="font-mono text-xs font-bold uppercase tracking-widest text-[var(--dedicated-accent)]">{{ $eyebrow }}</span>
    <h2 class="text-2xl font-bold sm:text-3xl">{{ $heading }}</h2>
    @isset($lead)
        <p class="mx-auto max-w-2xl text-sm leading-relaxed text-[var(--text-secondary)]">{{ $lead }}</p>
    @endisset
</div>
