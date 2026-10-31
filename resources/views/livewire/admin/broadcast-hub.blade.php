@php
    $inputClass = 'w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs font-medium text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden [color-scheme:light] dark:[color-scheme:dark]';
@endphp

<div class="space-y-6" wire:poll.5s>
    <div>
        <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Broadcast Hub</h1>
        <p class="mt-0.5 text-xs text-[var(--text-secondary)] sm:text-sm">
            Queue reminder emails to ticket holders of {{ $event?->title ?? 'the active event' }}. Each send runs as a tracked batch.
        </p>
    </div>

    @if ($flashMessage)
        <div class="flex items-center justify-between rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-xs font-medium text-emerald-800 dark:text-emerald-300">
            <span>{{ $flashMessage }}</span>
            <button type="button" wire:click="$set('flashMessage', null)" class="cursor-pointer">&times;</button>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <form wire:submit="send" class="space-y-4 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 shadow-xs lg:col-span-2">
            <h2 class="text-sm font-bold">Compose</h2>

            <div>
                <label for="broadcast-audience" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Audience</label>
                <select id="broadcast-audience" wire:model.live="audience" class="{{ $inputClass }} cursor-pointer">
                    <option value="all">All ticket holders</option>
                    <option value="pending">Not yet checked in</option>
                    <option value="checked_in">Already checked in</option>
                </select>
                <p class="mt-1 text-[11px] text-[var(--text-tertiary)]">{{ number_format($recipientCount) }} unique {{ \Illuminate\Support\Str::plural('recipient', $recipientCount) }}</p>
                @error('audience') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="broadcast-subject" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Subject <span class="text-rose-500">*</span></label>
                <input id="broadcast-subject" type="text" wire:model="subject" placeholder="e.g. Gates open at 8:00 AM tomorrow" class="{{ $inputClass }}" />
                @error('subject') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="broadcast-body" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Message <span class="text-rose-500">*</span></label>
                <textarea id="broadcast-body" rows="8" wire:model="body" class="{{ $inputClass }}" placeholder="Plain text. Event date and venue are appended automatically."></textarea>
                @error('body') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:confirm="Send this email to {{ $recipientCount }} recipients?"
                @disabled($event === null || $recipientCount === 0)
                class="w-full cursor-pointer rounded-xl bg-[var(--color-primary)] px-5 py-2.5 text-xs font-semibold text-[var(--color-primary-fg)] shadow-xs press-scale disabled:cursor-not-allowed disabled:opacity-50"
            >
                Queue Broadcast
            </button>
        </form>

        <section class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-xs lg:col-span-3">
            <div class="flex items-center justify-between border-b border-[var(--border-hairline)] px-5 py-4">
                <h2 class="text-sm font-bold">Recent Broadcasts</h2>
                <a href="{{ route('admin.queue-monitor') }}" wire:navigate class="text-xs font-semibold text-[var(--text-secondary)] hover:text-[var(--text-primary)]">Queue monitor →</a>
            </div>

            @forelse ($history as $broadcast)
                @php
                    $batch = $batches[$broadcast->id] ?? null;
                    $progress = $batch?->progress() ?? 0;
                @endphp
                <div wire:key="broadcast-{{ $broadcast->id }}" class="space-y-2 border-b border-[var(--border-hairline)] px-5 py-4 last:border-b-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-semibold">{{ $broadcast->subject }}</p>
                            <p class="text-[11px] text-[var(--text-secondary)]">
                                {{ $broadcast->event->title }} · {{ $broadcast->recipients_count }} recipients · {{ $broadcast->created_at?->diffForHumans() }}{{ $broadcast->sender ? ' · ' . $broadcast->sender->name : '' }}
                            </p>
                        </div>
                        <span class="shrink-0 text-[11px] font-semibold text-[var(--text-secondary)]">
                            @if ($batch === null)
                                Queued
                            @elseif ($batch->cancelled())
                                Cancelled
                            @elseif ($batch->finished())
                                {{ $batch->failedJobs > 0 ? 'Done · ' . $batch->failedJobs . ' failed' : 'Delivered' }}
                            @else
                                {{ $progress }}%
                            @endif
                        </span>
                    </div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-[var(--bg-subtle)]">
                        <div class="h-full rounded-full bg-[var(--status-success)] transition-[width] duration-500" style="width: {{ $batch?->finished() ? 100 : $progress }}%"></div>
                    </div>
                    @if ($batch !== null && !$batch->finished() && !$batch->cancelled())
                        <button type="button" wire:click="cancelBroadcast({{ $broadcast->id }})" class="cursor-pointer text-[11px] font-semibold text-rose-600 hover:underline">Cancel remaining</button>
                    @endif
                </div>
            @empty
                <p class="px-5 py-12 text-center text-xs text-[var(--text-secondary)]">No broadcasts yet.</p>
            @endforelse
        </section>
    </div>
</div>
