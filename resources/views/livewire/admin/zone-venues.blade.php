@php
    $inputClass = 'w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs font-medium text-[var(--text-primary)] shadow-xs focus:border-[var(--text-primary)] focus:outline-hidden';
    $labelClass = 'block text-[11px] font-semibold text-[var(--text-secondary)] mb-1';
@endphp

<div class="space-y-6">
    <div>
        <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ $label }} Venues</h1>
        <p class="mt-0.5 text-xs text-[var(--text-secondary)] sm:text-sm">
            @if ($isZoneAdmin)
                Where and when your {{ strtolower($label) }} meets for {{ $event?->title ?? 'this event' }}. Buyers see this when choosing a {{ strtolower($label) }}, and it is printed on their passes.
            @else
                Where and when each {{ strtolower($label) }} meets for {{ $event?->title ?? 'this event' }}. Leave a field blank to use the event's own venue and time.
            @endif
        </p>
    </div>

    @if ($flashMessage)
        <div class="flex items-center justify-between rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-xs font-medium text-emerald-800 dark:text-emerald-300">
            <span>{{ $flashMessage }}</span>
            <button type="button" wire:click="$set('flashMessage', null)" class="cursor-pointer">&times;</button>
        </div>
    @endif

    @if ($event === null)
        <p class="rounded-2xl border border-dashed border-[var(--border-hairline)] p-6 text-center text-xs text-[var(--text-secondary)]">Create an event first.</p>
    @elseif ($zones->isEmpty())
        <p class="rounded-2xl border border-dashed border-[var(--border-hairline)] p-6 text-center text-xs text-[var(--text-secondary)]">No {{ strtolower(\Illuminate\Support\Str::plural($label)) }} yet.</p>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($zones as $zone)
                <form wire:submit="save({{ $zone->id }})" class="space-y-3 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4" wire:key="zone-venue-{{ $zone->id }}">
                    <h2 class="text-sm font-bold">{{ $zone->name }}</h2>

                    <div>
                        <label for="venue-{{ $zone->id }}" class="{{ $labelClass }}">Venue</label>
                        <input id="venue-{{ $zone->id }}" type="text" wire:model="forms.{{ $zone->id }}.venue" placeholder="{{ $event->venue ?? 'e.g. Main Auditorium' }}" class="{{ $inputClass }}" />
                        @error("forms.{$zone->id}.venue") <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="address-{{ $zone->id }}" class="{{ $labelClass }}">Address</label>
                        <input id="address-{{ $zone->id }}" type="text" wire:model="forms.{{ $zone->id }}.address" placeholder="Street, town" class="{{ $inputClass }}" />
                        @error("forms.{$zone->id}.address") <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="map-{{ $zone->id }}" class="{{ $labelClass }}">Map link</label>
                        <input id="map-{{ $zone->id }}" type="url" wire:model="forms.{{ $zone->id }}.map_url" placeholder="https://maps.google.com/…" class="{{ $inputClass }}" />
                        @error("forms.{$zone->id}.map_url") <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label for="start-{{ $zone->id }}" class="{{ $labelClass }}">Starts (optional)</label>
                            <input id="start-{{ $zone->id }}" type="datetime-local" wire:model="forms.{{ $zone->id }}.start_at" class="{{ $inputClass }}" />
                            @error("forms.{$zone->id}.start_at") <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="end-{{ $zone->id }}" class="{{ $labelClass }}">Ends (optional)</label>
                            <input id="end-{{ $zone->id }}" type="datetime-local" wire:model="forms.{{ $zone->id }}.end_at" class="{{ $inputClass }}" />
                            @error("forms.{$zone->id}.end_at") <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <button type="submit" class="w-full cursor-pointer rounded-xl bg-[var(--text-primary)] py-2 text-xs font-semibold text-[var(--bg-canvas)] hover:opacity-90">Save {{ $zone->name }}</button>
                </form>
            @endforeach
        </div>
    @endif
</div>
