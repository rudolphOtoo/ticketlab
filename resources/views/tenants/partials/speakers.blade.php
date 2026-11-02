@php
    $speakerList = ($event?->speakers ?? collect())->values();
    $speakerData = $speakerList->map(static fn($speaker): array => [
        'name' => $speaker->name,
        'role' => $speaker->title_role,
        'topic' => $speaker->topic,
        'bio' => $speaker->bio,
        'photo' => $speaker->photoUrl(),
        'initial' => mb_substr($speaker->name, 0, 1),
    ])->all();
@endphp

@if ($speakerList->isNotEmpty())
    <section id="speakers" class="mx-auto w-full max-w-6xl space-y-10 px-4 py-20 sm:px-6" x-data="{ active: null, speakers: @js($speakerData) }" x-on:keydown.escape.window="active = null">
        @include('tenants.partials.section-heading', ['eyebrow' => 'Featured Contributors', 'heading' => 'Distinguished Speakers'])

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($speakerList as $speaker)
                <button
                    type="button"
                    x-on:click="active = {{ $loop->index }}"
                    class="group flex cursor-pointer items-center gap-4 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 text-left shadow-2xs transition hover:-translate-y-0.5 hover:border-[var(--dedicated-accent)] hover:shadow-lg"
                >
                    <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[var(--bg-subtle)] text-lg font-bold text-[var(--text-secondary)]">
                        @if ($speaker->photoUrl())
                            <img src="{{ $speaker->photoUrl() }}" alt="{{ $speaker->name }}" class="size-full object-cover" loading="lazy" />
                        @else
                            {{ mb_substr($speaker->name, 0, 1) }}
                        @endif
                    </div>
                    <div class="min-w-0 space-y-1">
                        <h3 class="truncate text-sm font-bold">{{ $speaker->name }}</h3>
                        @if ($speaker->title_role)
                            <span class="inline-block max-w-full truncate rounded-full bg-[var(--bg-subtle)] px-2 py-0.5 text-[10px] font-semibold text-[var(--text-secondary)]">{{ $speaker->title_role }}</span>
                        @endif
                        @if ($speaker->topic)
                            <p class="line-clamp-1 text-xs text-[var(--dedicated-accent)]">{{ $speaker->topic }}</p>
                        @endif
                    </div>
                </button>
            @endforeach
        </div>

        {{-- Spring bio drawer: slides in from the right with an overshoot-free spring curve. --}}
        <template x-teleport="body">
            <div x-show="active !== null" class="fixed inset-0 z-[60]" style="display: none;" role="dialog" aria-modal="true">
                <div x-show="active !== null" x-transition.opacity.duration.300ms class="absolute inset-0 bg-black/50 backdrop-blur-xs" x-on:click="active = null"></div>
                <aside
                    x-show="active !== null"
                    x-transition:enter="transition-transform duration-500 ease-[cubic-bezier(0.32,0.72,0,1)]"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition-transform duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                    class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col overflow-y-auto border-l border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-2xl sm:rounded-l-3xl"
                >
                    <template x-if="active !== null">
                        <div class="space-y-5">
                            <div class="flex justify-end">
                                <button type="button" x-on:click="active = null" class="cursor-pointer rounded-full p-2 text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)]" aria-label="Close">
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                            <div class="mx-auto flex size-32 items-center justify-center overflow-hidden rounded-full bg-[var(--bg-subtle)] text-4xl font-bold text-[var(--text-secondary)]">
                                <template x-if="speakers[active].photo"><img :src="speakers[active].photo" :alt="speakers[active].name" class="size-full object-cover" /></template>
                                <template x-if="!speakers[active].photo"><span x-text="speakers[active].initial"></span></template>
                            </div>
                            <div class="space-y-1 text-center">
                                <h3 class="text-xl font-bold" x-text="speakers[active].name"></h3>
                                <p class="text-sm text-[var(--text-secondary)]" x-text="speakers[active].role"></p>
                            </div>
                            <template x-if="speakers[active].topic">
                                <div class="rounded-xl bg-[var(--bg-subtle)] p-4">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--text-tertiary)]">Speaking on</span>
                                    <p class="mt-1 text-sm font-semibold text-[var(--dedicated-accent)]" x-text="speakers[active].topic"></p>
                                </div>
                            </template>
                            <p class="whitespace-pre-line text-sm leading-relaxed text-[var(--text-secondary)]" x-text="speakers[active].bio || 'Biography coming soon.'"></p>
                        </div>
                    </template>
                </aside>
            </div>
        </template>
    </section>
@endif
