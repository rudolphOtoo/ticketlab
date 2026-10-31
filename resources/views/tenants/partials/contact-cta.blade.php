<section class="mx-auto w-full max-w-5xl px-4 pb-20 sm:px-6">
    <div class="flex flex-col items-center justify-between gap-5 rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-8 text-center shadow-xs sm:flex-row sm:text-left">
        <div class="space-y-1">
            <h2 class="text-lg font-bold">Partner with us or ask a question</h2>
            <p class="text-sm text-[var(--text-secondary)]">Sponsorships, group bookings and media enquiries are welcome.</p>
        </div>
        @php
            $isMultiTenant = !App\Models\PlatformSetting::isDedicatedMode();
            $partnershipUrl = $isMultiTenant ? route('public.tenant.page', ['org_slug' => $organization->slug, 'page' => 'partnership']) : route('dedicated.partnership');
            $contactUrl = $isMultiTenant ? route('public.tenant.page', ['org_slug' => $organization->slug, 'page' => 'contact']) : route('dedicated.contact');
        @endphp
        <div class="flex shrink-0 gap-2">
            <a href="{{ $partnershipUrl }}" class="rounded-xl border border-[var(--border-hairline)] px-5 py-2.5 text-xs font-bold transition hover:bg-[var(--bg-subtle)] press-scale">Partnership</a>
            <a href="{{ $contactUrl }}" class="rounded-xl bg-[var(--dedicated-accent)] px-5 py-2.5 text-xs font-bold text-white transition hover:opacity-90 press-scale">Contact Us</a>
        </div>
    </div>
</section>
