@php
    $bespoke = $organization->bespokeSettings();
    $galleryConfig = (array) ($bespoke['gallery'] ?? []);
    $galleryHeading = (string) ($galleryConfig['heading'] ?? 'Moments & Highlights');
    $galleryLead = (string) ($galleryConfig['lead'] ?? 'Capturing the vibrant energy, keynote sessions, and electric connections.');
    $galleryUrl = $organization->pageUrl('gallery');

    // Only the organisation's own photos (featured event plus organisation-wide). Never stock imagery: a church
    // site must not show photos or captions that are not theirs.
    $galleryItems = array_slice(\App\Support\EventGallery::forEvent($organization, $event ?? null), 0, 6);
@endphp

@if ($galleryItems !== [])
<section id="gallery" class="relative mx-auto w-full max-w-6xl px-4 py-16 sm:px-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-8">
        <div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-mono font-bold uppercase tracking-wider bg-[var(--dedicated-accent)]/10 text-[var(--dedicated-accent)] border border-[var(--dedicated-accent)]/20">
                Visual Showcase
            </span>
            <h2 class="font-serif text-2xl sm:text-3xl font-bold tracking-tight text-[var(--text-primary)] mt-2">
                {{ $galleryHeading }}
            </h2>
            <p class="text-xs sm:text-sm text-[var(--text-secondary)] max-w-xl mt-1">
                {{ $galleryLead }}
            </p>
        </div>

        <a
            href="{{ $galleryUrl }}"
            class="inline-flex items-center gap-1.5 text-xs font-bold text-[var(--dedicated-accent)] hover:underline group"
        >
            <span>Explore Full Gallery</span>
            <span class="transition-transform group-hover:translate-x-0.5">→</span>
        </a>
    </div>

    <!-- Gallery Grid with Micro-Interactions -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        @foreach ($galleryItems as $item)
            <a
                href="{{ $galleryUrl }}"
                class="group relative aspect-4/3 overflow-hidden rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-2xs transition-all duration-300 hover:shadow-lg hover:translate-y-[-2px]"
            >
                <x-media-image
                    :value="$item['url']"
                    preset="card"
                    :alt="$item['title']"
                    sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
                    class="h-full w-full object-cover transition-transform duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:scale-108"
                />

                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-60 transition-opacity duration-300 group-hover:opacity-90"></div>

                <div class="absolute top-2.5 right-2.5">
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-mono font-bold uppercase tracking-wider bg-black/60 text-white backdrop-blur-md border border-white/10">
                        {{ $item['tag'] ?? 'Moment' }}
                    </span>
                </div>

                <div class="absolute inset-x-0 bottom-0 p-3.5 transform transition-transform duration-300 group-hover:translate-y-0">
                    <h4 class="text-xs font-bold text-white truncate drop-shadow-xs">{{ $item['title'] }}</h4>
                </div>
            </a>
        @endforeach
    </div>
</section>

@endif
