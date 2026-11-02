@php
    $faqList = ($event?->faqs ?? collect())->filter(static fn($faq) => $faq->is_published);
@endphp

@if ($faqList->isNotEmpty())
    <section id="faqs" class="mx-auto w-full max-w-3xl space-y-8 px-4 py-20 sm:px-6">
        @include('tenants.partials.section-heading', ['eyebrow' => 'Clarifications', 'heading' => 'Frequently Asked Questions'])

        <div class="space-y-3">
            @foreach ($faqList as $faq)
                <details class="group cursor-pointer rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4">
                    <summary class="flex list-none items-center justify-between gap-3 text-sm font-bold">
                        <span>{{ $faq->question }}</span>
                        <span class="text-xs text-[var(--text-tertiary)] transition-transform group-open:rotate-180">▼</span>
                    </summary>
                    <p class="mt-3 border-t border-[var(--border-hairline)] pt-3 text-xs leading-relaxed text-[var(--text-secondary)]">{{ $faq->answer }}</p>
                </details>
            @endforeach
        </div>
    </section>
@endif
