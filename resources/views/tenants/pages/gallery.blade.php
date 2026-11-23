@php
    $bespoke = $organization->bespokeSettings();
    $galleryConfig = (array) ($bespoke['gallery'] ?? []);
    $galleryHeading = (string) ($galleryConfig['heading'] ?? 'Moments & Gallery');
    $galleryLead = (string) ($galleryConfig['lead'] ?? 'Capturing the vibrant energy, keynote sessions, and electric connections.');

    // Only the organisation's own photos (featured event plus organisation-wide). Never stock imagery: a church
    // site must not show photos or captions that are not theirs.
    $galleryImages = \App\Support\EventGallery::forEvent($organization, $event ?? null);

    $tags = array_values(array_unique(array_merge(['All'], array_column($galleryImages, 'tag'))));
@endphp

<x-layouts::dedicated :organization="$organization" :event="$event" title="{{ $galleryHeading }}">
    @include('tenants.pages.page-header', ['heading' => $galleryHeading, 'lead' => $galleryLead])

    @if ($galleryImages === [])
        <div class="mx-auto w-full max-w-2xl px-4 py-16 text-center sm:px-6" data-gallery-empty>
            <div class="rounded-3xl border border-dashed border-[var(--border-hairline)] bg-[var(--bg-surface)] px-6 py-12">
                <p class="text-base font-semibold text-[var(--text-primary)]">Photos coming soon</p>
                <p class="mt-2 text-sm text-[var(--text-secondary)]">{{ $organization->name }} has not shared any photos yet. Check back after the event.</p>
            </div>
        </div>
    @else
    <div
        x-data="{
            activeFilter: 'All',
            activeImage: null,
            openLightbox(img) {
                this.activeImage = img;
                document.body.style.overflow = 'hidden';
            },
            closeLightbox() {
                this.activeImage = null;
                document.body.style.overflow = '';
            }
        }"
        @keydown.window.escape="closeLightbox()"
        class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 sm:py-12 space-y-8"
    >
        <!-- Category Filter Tag Chips -->
        <div class="flex flex-wrap items-center justify-center gap-2">
            @foreach ($tags as $tag)
                <button
                    type="button"
                    @click="activeFilter = @js($tag)"
                    class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all duration-200 cursor-pointer active:scale-[0.96]"
                    :class="activeFilter === @js($tag)
                        ? 'bg-[var(--dedicated-accent)] text-[var(--dedicated-accent-fg)] shadow-xs'
                        : 'border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)]'"
                >
                    {{ $tag }}
                </button>
            @endforeach
        </div>

        <!-- Animated Responsive Image Gallery Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($galleryImages as $index => $item)
                <div
                    x-show="activeFilter === 'All' || activeFilter === @js($item['tag'])"
                    x-transition:enter="transition ease-out duration-250"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    @click="openLightbox(@js($item))"
                    class="group relative aspect-4/3 w-full overflow-hidden rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-2xs cursor-pointer transition-all duration-300 hover:shadow-xl hover:translate-y-[-3px]"
                >
                    <!-- Image -->
                    <x-media-image
                        :value="$item['url']"
                        preset="card"
                        :alt="$item['title']"
                        sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
                        class="h-full w-full object-cover transition-transform duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:scale-108"
                    />

                    <!-- Category Pill (Top Right) -->
                    <div class="absolute top-3 right-3 z-10">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider bg-black/60 text-white backdrop-blur-md border border-white/10 shadow-xs">
                            {{ $item['tag'] }}
                        </span>
                    </div>

                    <!-- Ambient Dark Gradient Overlay -->
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-60 transition-opacity duration-300 group-hover:opacity-90"></div>

                    <!-- Bottom Caption Sheet -->
                    <div class="absolute inset-x-0 bottom-0 p-5 transform transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:translate-y-0">
                        <h3 class="text-sm sm:text-base font-bold text-white tracking-tight drop-shadow-xs">
                            {{ $item['title'] }}
                        </h3>
                        @if (!empty($item['caption']))
                            <p class="text-xs text-white/80 line-clamp-2 mt-1 leading-normal">
                                {{ $item['caption'] }}
                            </p>
                        @endif
                        <div class="mt-2.5 flex items-center gap-1.5 text-[11px] font-semibold text-[var(--dedicated-accent)] opacity-0 transform translate-y-1 transition-all duration-200 group-hover:opacity-100 group-hover:translate-y-0">
                            <span>View Full Photo</span>
                            <span>↗</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Lightbox Zoom Modal (Micro-Animated) -->
        <template x-if="activeImage !== null">
            <div
                class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-black/90 backdrop-blur-md"
                @click.self="closeLightbox()"
            >
                <div
                    class="relative max-w-4xl w-full max-h-[90vh] flex flex-col rounded-3xl overflow-hidden bg-[var(--bg-surface)] border border-white/10 shadow-2xl animate-in fade-in zoom-in-95 duration-200"
                    @click.stop
                >
                    <!-- Modal Header Ribbon -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-[var(--border-hairline)] bg-[var(--bg-surface)]/80 backdrop-blur-sm">
                        <div class="flex items-center gap-3">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider bg-[var(--dedicated-accent)]/15 text-[var(--dedicated-accent)]" x-text="activeImage.tag"></span>
                            <h4 class="text-sm sm:text-base font-bold text-[var(--text-primary)]" x-text="activeImage.title"></h4>
                        </div>
                        <button
                            type="button"
                            @click="closeLightbox()"
                            class="p-1.5 rounded-xl text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition cursor-pointer"
                            aria-label="Close modal"
                        >
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Image Display -->
                    <div class="relative flex-1 overflow-hidden bg-black/30 flex items-center justify-center">
                        <img
                            :src="activeImage.src || activeImage.url"
                            :alt="activeImage.title"
                            class="max-h-[70vh] w-full object-contain select-none"
                        />
                    </div>

                    <!-- Modal Caption Footer -->
                    <div class="px-6 py-3.5 border-t border-[var(--border-hairline)] bg-[var(--bg-surface)] flex items-center justify-between text-xs text-[var(--text-secondary)]">
                        <p x-text="activeImage.caption || activeImage.title"></p>
                        <span class="font-mono text-[10px] text-[var(--text-tertiary)]">Press ESC to exit</span>
                    </div>
                </div>
            </div>
        </template>
    </div>
    @endif

    @include('tenants.partials.contact-cta')
</x-layouts::dedicated>

