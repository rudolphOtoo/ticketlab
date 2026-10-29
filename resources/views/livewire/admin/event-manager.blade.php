@php
    $inputClass = 'w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs font-medium text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden [color-scheme:light] dark:[color-scheme:dark]';
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Events</h1>
            <p class="mt-0.5 text-xs text-[var(--text-secondary)] sm:text-sm">Create, publish and schedule events. The active event scopes every admin screen.</p>
        </div>

        <button type="button" wire:click="openCreateModal" class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-[var(--color-primary-fg)] shadow-xs transition hover:opacity-95 press-scale">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4" /></svg>
            <span>New Event</span>
        </button>
    </div>

    @if ($flashMessage)
        <div class="flex items-center justify-between rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-xs font-medium text-emerald-800 dark:text-emerald-300">
            <span>{{ $flashMessage }}</span>
            <button type="button" wire:click="$set('flashMessage', null)" class="cursor-pointer">&times;</button>
        </div>
    @endif

    @if ($events->isEmpty())
        <div class="rounded-3xl border border-dashed border-[var(--border-hairline)] bg-[var(--bg-surface)] p-12 text-center">
            <h3 class="text-sm font-semibold">No events yet</h3>
            <p class="mx-auto mt-1 max-w-sm text-xs text-[var(--text-secondary)]">Create your first event to unlock ticket tiers, the customizer, the roster and broadcasts.</p>
            <button type="button" wire:click="openCreateModal" class="mt-5 inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-[var(--color-primary-fg)] shadow-xs press-scale">+ Create First Event</button>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($events as $event)
                @php $isActive = $event->id === $activeEventId; @endphp
                <article wire:key="event-{{ $event->id }}" @class([
                    'flex flex-col overflow-hidden rounded-2xl border bg-[var(--bg-surface)] shadow-xs transition',
                    'border-[var(--text-primary)] ring-1 ring-[var(--text-primary)]' => $isActive,
                    'border-[var(--border-hairline)]' => !$isActive,
                ])>
                    <div class="relative aspect-[16/7] bg-[var(--bg-subtle)]">
                        @if ($event->bannerUrl())
                            <img src="{{ $event->bannerUrl() }}" alt="" class="size-full object-cover" loading="lazy" />
                        @endif
                        <div class="absolute left-3 top-3 flex gap-1.5">
                            <x-ui.badge :color="$event->status === 'published' ? 'emerald' : 'amber'" pill size="sm">{{ ucfirst($event->status) }}</x-ui.badge>
                            @unless ($event->is_public)
                                <x-ui.badge color="zinc" pill size="sm">Unlisted</x-ui.badge>
                            @endunless
                        </div>
                    </div>

                    <div class="flex flex-1 flex-col gap-3 p-4">
                        <div>
                            <h3 class="text-sm font-bold leading-tight">{{ $event->title }}</h3>
                            <p class="mt-0.5 text-[11px] text-[var(--text-secondary)]">{{ $event->start_at->format('D, M j Y · g:i A') }}{{ $event->venue ? ' · ' . $event->venue : '' }}</p>
                            <p class="mt-1 font-mono text-[10px] text-[var(--text-tertiary)]">/{{ $event->slug }}</p>
                        </div>

                        <p class="text-[11px] text-[var(--text-secondary)]">
                            {{ $event->ticket_types_count }} {{ \Illuminate\Support\Str::plural('tier', $event->ticket_types_count) }} · {{ number_format((int) $event->capacity_total) }} capacity
                        </p>

                        <div class="mt-auto flex flex-wrap items-center gap-1.5 border-t border-[var(--border-hairline)] pt-3 text-[11px] font-semibold">
                            @if ($isActive)
                                <span class="rounded-lg bg-[var(--text-primary)] px-2.5 py-1 text-[var(--bg-canvas)]">Active</span>
                            @else
                                <button type="button" wire:click="activate({{ $event->id }})" class="cursor-pointer rounded-lg border border-[var(--border-hairline)] px-2.5 py-1 hover:bg-[var(--bg-subtle)]">Set Active</button>
                            @endif
                            <button type="button" wire:click="editEvent({{ $event->id }})" class="cursor-pointer rounded-lg px-2.5 py-1 text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]">Edit</button>
                            <button type="button" wire:click="togglePublished({{ $event->id }})" class="cursor-pointer rounded-lg px-2.5 py-1 text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]">
                                {{ $event->status === 'published' ? 'Unpublish' : 'Publish' }}
                            </button>
                            <button type="button" wire:click="deleteEvent({{ $event->id }})" wire:confirm="Delete '{{ $event->title }}'? This cannot be undone." class="ml-auto cursor-pointer rounded-lg px-2.5 py-1 text-rose-600 hover:bg-rose-500/10">Delete</button>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if ($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4 backdrop-blur-xs sm:p-6" @keydown.window.escape="$wire.closeModal()">
            <div class="my-auto w-full max-w-2xl space-y-5 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-2xl animate-physical-modal sm:p-7">
                <div class="flex items-center justify-between border-b border-[var(--border-hairline)] pb-3">
                    <div>
                        <h2 class="text-base font-bold sm:text-lg">{{ $editingEventId ? 'Edit Event' : 'Create Event' }}</h2>
                        <p class="mt-0.5 text-xs text-[var(--text-secondary)]">Published + listed events appear in the public directory.</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="cursor-pointer rounded-lg p-1.5 text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)]">&times;</button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="event-title" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Title <span class="text-rose-500">*</span></label>
                            <input id="event-title" type="text" wire:model.live.debounce.300ms="title" class="{{ $inputClass }}" />
                            @error('title') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="event-slug" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">URL Slug <span class="text-rose-500">*</span></label>
                            <input id="event-slug" type="text" wire:model.blur="slug" class="{{ $inputClass }} font-mono" />
                            @error('slug') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="event-venue" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Venue</label>
                        <input id="event-venue" type="text" wire:model="venue" placeholder="e.g. Perez Dome, Dzorwulu, Accra" class="{{ $inputClass }}" />
                        @error('venue') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="event-description" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Description</label>
                        <textarea id="event-description" rows="3" wire:model="description" class="{{ $inputClass }}"></textarea>
                        @error('description') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="event-start" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Starts <span class="text-rose-500">*</span></label>
                            <input id="event-start" type="datetime-local" wire:model="start_at" class="{{ $inputClass }}" />
                            @error('start_at') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="event-end" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Ends <span class="text-rose-500">*</span></label>
                            <input id="event-end" type="datetime-local" wire:model="end_at" class="{{ $inputClass }}" />
                            @error('end_at') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="event-banner" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Banner Image <span class="font-normal text-[var(--text-tertiary)]">(JPG, PNG or WebP · max 4 MB)</span></label>
                        <div class="flex items-center gap-3">
                            <div class="aspect-[16/7] w-40 shrink-0 overflow-hidden rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)]">
                                @if ($banner && in_array($banner->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true))
                                    <img src="{{ $banner->temporaryUrl() }}" alt="" class="size-full object-cover" />
                                @elseif ($existingBannerUrl)
                                    <img src="{{ $existingBannerUrl }}" alt="" class="size-full object-cover" />
                                @endif
                            </div>
                            <input id="event-banner" type="file" accept="image/jpeg,image/png,image/webp" wire:model="banner" class="text-xs text-[var(--text-secondary)] file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-[var(--bg-subtle)] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[var(--text-primary)]" />
                        </div>
                        <div wire:loading wire:target="banner" class="mt-1 text-[11px] text-[var(--text-tertiary)]">Uploading…</div>
                        @error('banner') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="event-status" class="mb-1 block text-xs font-semibold text-[var(--text-secondary)]">Status</label>
                            <select id="event-status" wire:model="status" class="{{ $inputClass }} cursor-pointer">
                                <option value="draft">Draft (hidden)</option>
                                <option value="published">Published</option>
                            </select>
                            @error('status') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                        <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-[var(--border-hairline)] p-3">
                            <input type="checkbox" wire:model="is_public" class="mt-0.5 size-4 rounded-md" />
                            <span class="text-xs">
                                <span class="font-semibold">List in public directory</span>
                                <span class="block text-[11px] text-[var(--text-secondary)]">Unlisted events stay reachable by direct link.</span>
                            </span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-[var(--border-hairline)] pt-4">
                        <button type="button" wire:click="closeModal" class="cursor-pointer rounded-xl border border-[var(--border-hairline)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] press-scale">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" class="cursor-pointer rounded-xl bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-[var(--color-primary-fg)] shadow-xs press-scale disabled:opacity-60">
                            {{ $editingEventId ? 'Save Changes' : 'Create Event' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
