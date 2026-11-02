<div class="space-y-6" wire:poll.5s>
    <div>
        <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Queue Monitor</h1>
        <p class="mt-0.5 text-xs text-[var(--text-secondary)] sm:text-sm">Live delivery status for ticket emails and broadcasts. Refreshes every 5 seconds.</p>
    </div>

    @if ($flashMessage)
        <div class="flex items-center justify-between rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-xs font-medium text-emerald-800 dark:text-emerald-300">
            <span>{{ $flashMessage }}</span>
            <button type="button" wire:click="$set('flashMessage', null)" class="cursor-pointer">&times;</button>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-[var(--text-secondary)]">Pending jobs</span>
                <span class="size-2 rounded-full bg-sky-500"></span>
            </div>
            <div class="mt-2 font-mono text-2xl font-bold tabular-nums text-[var(--text-primary)]">{{ number_format($pendingCount) }}</div>
        </div>
        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-[var(--text-secondary)]">Failed jobs</span>
                <span @class(['size-2 rounded-full', 'bg-rose-500' => $failedCount > 0, 'bg-emerald-500' => $failedCount === 0])></span>
            </div>
            <div @class(['mt-2 font-mono text-2xl font-bold tabular-nums', 'text-rose-600' => $failedCount > 0, 'text-[var(--text-primary)]' => $failedCount === 0])>{{ number_format($failedCount) }}</div>
        </div>
        <div class="col-span-2 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-xs lg:col-span-1">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-[var(--text-secondary)]">Broadcast batches</span>
                <span class="size-2 rounded-full bg-violet-500"></span>
            </div>
            <div class="mt-2 font-mono text-2xl font-bold tabular-nums text-[var(--text-primary)]">{{ $broadcasts->count() }}</div>
        </div>
    </div>

    <section class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-xs">
        <h2 class="border-b border-[var(--border-hairline)] px-5 py-4 text-sm font-bold">Broadcast Batches</h2>
        @forelse ($broadcasts as $broadcast)
            @php $batch = $batches[$broadcast->id] ?? null; @endphp
            <div wire:key="batch-{{ $broadcast->id }}" class="flex flex-col gap-2 border-b border-[var(--border-hairline)] px-5 py-3.5 last:border-b-0 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="truncate text-xs font-semibold">{{ $broadcast->subject }}</p>
                    <p class="text-[11px] text-[var(--text-secondary)]">{{ $broadcast->event->title }} · {{ $broadcast->created_at?->diffForHumans() }}</p>
                </div>
                <div class="flex items-center gap-3 text-[11px]">
                    @if ($batch)
                        <span class="font-mono tabular-nums">{{ $batch->processedJobs() }}/{{ $batch->totalJobs }} sent</span>
                        @if ($batch->failedJobs > 0)
                            <span class="font-semibold text-rose-600">{{ $batch->failedJobs }} failed</span>
                            <button type="button" wire:click="retryBatch({{ $broadcast->id }})" class="cursor-pointer rounded-lg border border-[var(--border-hairline)] px-2.5 py-1 font-semibold hover:bg-[var(--bg-subtle)]">Retry failed</button>
                        @endif
                        <span class="font-semibold text-[var(--text-secondary)]">{{ $batch->cancelled() ? 'Cancelled' : ($batch->finished() ? 'Finished' : $batch->progress() . '%') }}</span>
                    @else
                        <span class="text-[var(--text-tertiary)]">Batch record pruned</span>
                    @endif
                </div>
            </div>
        @empty
            <p class="px-5 py-10 text-center text-xs text-[var(--text-secondary)]">No broadcasts have been queued yet.</p>
        @endforelse
    </section>

    @if ($isSuperAdmin)
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-xs">
                <h2 class="border-b border-[var(--border-hairline)] px-5 py-4 text-sm font-bold">Pending by Job</h2>
                @forelse ($pendingByJob as $name => $count)
                    <div class="flex items-center justify-between border-b border-[var(--border-hairline)] px-5 py-2.5 text-xs last:border-b-0">
                        <span class="font-mono">{{ $name }}</span>
                        <span class="font-mono font-semibold tabular-nums">{{ $count }}</span>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-xs text-[var(--text-secondary)]">Queue is empty.</p>
                @endforelse
            </section>

            <section class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-xs lg:col-span-2">
                <div class="flex items-center justify-between border-b border-[var(--border-hairline)] px-5 py-4">
                    <h2 class="text-sm font-bold">Failed Jobs <span class="font-normal text-[var(--text-tertiary)]">(platform-wide)</span></h2>
                    @if ($failedJobs->isNotEmpty())
                        <button type="button" wire:click="flushFailed" wire:confirm="Permanently delete every failed job?" class="cursor-pointer rounded-lg px-2.5 py-1 text-[11px] font-semibold text-rose-600 hover:bg-rose-500/10">Flush all</button>
                    @endif
                </div>
                @forelse ($failedJobs as $job)
                    <div wire:key="failed-{{ $job['uuid'] }}" class="space-y-1 border-b border-[var(--border-hairline)] px-5 py-3 last:border-b-0">
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate font-mono text-xs font-semibold">{{ $job['name'] }} <span class="font-normal text-[var(--text-tertiary)]">· {{ $job['queue'] }}</span></span>
                            <div class="flex shrink-0 items-center gap-1.5 text-[11px] font-semibold">
                                <button type="button" wire:click="retryJob('{{ $job['uuid'] }}')" class="cursor-pointer rounded-lg border border-[var(--border-hairline)] px-2.5 py-1 hover:bg-[var(--bg-subtle)]">Retry</button>
                                <button type="button" wire:click="forgetJob('{{ $job['uuid'] }}')" class="cursor-pointer rounded-lg px-2.5 py-1 text-rose-600 hover:bg-rose-500/10">Delete</button>
                            </div>
                        </div>
                        <p class="text-[11px] text-[var(--text-secondary)]">{{ $job['error'] }}</p>
                        <p class="font-mono text-[10px] text-[var(--text-tertiary)]">{{ $job['failed_at'] }}</p>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-xs text-[var(--text-secondary)]">No failed jobs. 🎉</p>
                @endforelse
            </section>
        </div>
    @endif
</div>
