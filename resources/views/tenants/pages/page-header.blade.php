<div class="mx-auto max-w-4xl space-y-3 px-4 pb-6 pt-32 text-center sm:px-6">
    <span class="font-mono text-xs font-bold uppercase tracking-widest text-[var(--dedicated-accent)]">{{ $organization->name }}</span>
    <h1 class="font-serif text-4xl font-bold tracking-tight sm:text-5xl">{{ $heading }}</h1>
    @isset($lead)
        <p class="mx-auto max-w-2xl text-sm leading-relaxed text-[var(--text-secondary)] sm:text-base">{{ $lead }}</p>
    @endisset
</div>
