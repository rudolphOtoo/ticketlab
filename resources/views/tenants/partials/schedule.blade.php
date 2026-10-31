@php
    $days = ($event?->schedules ?? collect())->groupBy('day_label');
@endphp

@if ($days->isNotEmpty())
    <section id="schedule" class="mx-auto w-full max-w-4xl space-y-8 px-4 py-20 sm:px-6" x-data="{ day: @js($days->keys()->first()) }">
        @include('tenants.partials.section-heading', ['eyebrow' => 'Program Outline', 'heading' => 'Program Schedule'])

        @if ($days->count() > 1)
            <div class="flex flex-wrap justify-center gap-1.5" role="tablist">
                @foreach ($days->keys() as $label)
                    <button
                        type="button"
                        role="tab"
                        x-on:click="day = @js($label)"
                        :aria-selected="day === @js($label)"
                        :class="day === @js($label) ? 'bg-[var(--text-primary)] text-[var(--bg-canvas)]' : 'bg-[var(--bg-surface)] text-[var(--text-secondary)] hover:text-[var(--text-primary)]'"
                        class="cursor-pointer rounded-full border border-[var(--border-hairline)] px-4 py-1.5 text-xs font-semibold transition"
                    >{{ $label }}</button>
                @endforeach
            </div>
        @endif

        @foreach ($days as $label => $sessions)
            <div class="space-y-3" x-show="day === @js($label)" @if (!$loop->first) style="display: none;" @endif>
                @foreach ($sessions as $item)
                    <div class="flex items-start gap-4 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-2xs">
                        <div class="min-w-24 pt-0.5 font-mono text-xs font-bold text-[var(--text-tertiary)]">
                            {{ $item->start_time ?? 'TBA' }}@if ($item->end_time)<br><span class="font-normal">– {{ $item->end_time }}</span>@endif
                        </div>
                        <div class="flex-1 space-y-0.5">
                            <h3 class="text-sm font-bold">{{ $item->session_title }}</h3>
                            @if ($item->speaker_name)
                                <p class="text-xs text-[var(--dedicated-accent)]">{{ $item->speaker_name }}</p>
                            @endif
                            @if ($item->venue_hall)
                                <p class="text-[11px] text-[var(--text-tertiary)]">{{ $item->venue_hall }}</p>
                            @endif
                            @if ($item->description)
                                <p class="pt-1 text-xs text-[var(--text-secondary)]">{{ $item->description }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </section>
@endif
