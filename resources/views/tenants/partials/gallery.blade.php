@php
    $bespoke = $organization->bespokeSettings();
    $galleryConfig = (array) ($bespoke['gallery'] ?? []);
    $galleryHeading = (string) ($galleryConfig['heading'] ?? 'Moments & Highlights');
    $galleryLead = (string) ($galleryConfig['lead'] ?? 'Capturing the vibrant energy, keynote sessions, and electric connections.');
    $isMultiTenant = !App\Models\PlatformSetting::isDedicatedMode();
    $galleryUrl = $isMultiTenant
        ? route('public.tenant.page', ['org_slug' => $organization->slug, 'page' => 'gallery'])
        : route('dedicated.gallery');

    $configuredImages = array_values(array_filter((array) ($galleryConfig['images'] ?? [])));
    $galleryItems = !empty($configuredImages)
        ? array_slice($configuredImages, 0, 6)
        : [
            [
                'url' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=800&q=80',
                'title' => 'Main Stage Keynote',
                'tag' => 'Keynote',
            ],
            [
                'url' => 'https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=800&q=80',
                'title' => 'Executive Roundtable',
                'tag' => 'Panels',
            ],
            [
                'url' => 'https://images.unsplash.com/photo-1475721027785-f74eccf877e2?auto=format&fit=crop&w=800&q=80',
                'title' => 'Acoustic Atmosphere',
                'tag' => 'Atmosphere',
            ],
            [
                'url' => 'https://images.unsplash.com/photo-1523580494863-6f3031224c94?auto=format&fit=crop&w=800&q=80',
                'title' => 'Patron Networking',
                'tag' => 'Networking',
            ],
        ];
@endphp

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
                <img
                    src="{{ $item['url'] }}"
                    alt="{{ $item['title'] }}"
                    loading="lazy"
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

