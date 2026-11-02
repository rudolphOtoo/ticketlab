<x-layouts::public>
    <x-floating-nav current="My Passes" />

    <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 pb-16 pt-28 sm:px-6">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold tracking-tight">Welcome back, {{ $user->name }}</h1>
            <p class="text-sm text-[var(--text-secondary)]">Your ticket passes and account settings.</p>
        </div>

        <section class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-xs">
            <div class="flex items-center justify-between border-b border-[var(--border-hairline)] px-5 py-4">
                <h2 class="text-sm font-semibold">My Passes</h2>
                <a href="{{ route('public.events.index') }}" class="text-xs font-semibold text-[var(--text-secondary)] hover:text-[var(--text-primary)]">Browse events →</a>
            </div>

            @forelse ($orders as $order)
                <a href="{{ route('public.tickets.show', ['order' => $order->reference]) }}" class="flex items-center justify-between gap-4 border-b border-[var(--border-hairline)] px-5 py-3.5 transition last:border-b-0 hover:bg-[var(--bg-subtle)]">
                    <div class="flex min-w-0 flex-col">
                        <span class="truncate font-mono text-xs font-semibold">{{ $order->reference }}</span>
                        <span class="text-[11px] text-[var(--text-secondary)]">{{ $order->attendees_count }} {{ \Illuminate\Support\Str::plural('pass', $order->attendees_count) }} · {{ $order->created_at?->format('M j, Y') }}</span>
                    </div>
                    <span class="text-[11px] font-semibold capitalize text-[var(--text-secondary)]">{{ str_replace('_', ' ', $order->payment_status->value) }}</span>
                </a>
            @empty
                <p class="px-5 py-10 text-center text-xs text-[var(--text-secondary)]">
                    {{ $user->hasVerifiedEmail() ? 'No passes yet. Tickets you buy with this email will appear here.' : 'Verify your email address to see passes bought with it.' }}
                </p>
            @endforelse
        </section>

        <a href="{{ route('profile.edit') }}" class="self-start text-xs font-semibold text-[var(--text-secondary)] hover:text-[var(--text-primary)]">Account settings →</a>
    </main>
</x-layouts::public>
