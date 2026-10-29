<div class="flex items-center gap-2">
    @if ($events->isEmpty())
        <a href="{{ route('admin.events') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-[var(--border-hairline)] px-2.5 py-1 text-xs font-medium text-[var(--text-secondary)] transition hover:text-[var(--text-primary)]">
            + Create your first event
        </a>
    @else
        <label for="active-event" class="hidden text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)] sm:block">Active event</label>
        <select
            id="active-event"
            wire:model.live="eventId"
            class="max-w-[11rem] cursor-pointer truncate rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-2.5 py-1.5 text-xs font-semibold text-[var(--text-primary)] shadow-xs focus:outline-hidden [color-scheme:light] dark:[color-scheme:dark] sm:max-w-[16rem]"
        >
            @foreach ($events as $event)
                <option value="{{ $event->id }}">{{ $event->title }}{{ $event->status === 'draft' ? ' (draft)' : '' }}</option>
            @endforeach
        </select>
        <span wire:loading class="size-1.5 animate-pulse rounded-full bg-[var(--status-success)]"></span>
    @endif
</div>
