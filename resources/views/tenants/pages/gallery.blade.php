@php
    $bespoke = $organization->bespokeSettings();
    $galleryConfig = (array) ($bespoke['gallery'] ?? []);
    $galleryHeading = (string) ($galleryConfig['heading'] ?? 'Moments & Gallery');
    $galleryLead = (string) ($galleryConfig['lead'] ?? 'Capturing the vibrant energy, keynote sessions, and electric connections.');

    $configuredImages = array_values(array_filter((array) ($galleryConfig['images'] ?? [])));
    $galleryImages = !empty($configuredImages)
        ? $configuredImages
        : [
            [
                'id' => 'demo-1',
                'url' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=1200&q=80',
                'title' => 'Main Auditorium Keynote',
                'caption' => 'Opening visionary session at the Perez Dome',
                'tag' => 'Keynote',
            ],
            [
                'id' => 'demo-2',
                'url' => 'https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=1200&q=80',
                'title' => 'Executive Leadership Summit',
                'caption' => 'Strategic roundtables and interactive discussions',
                'tag' => 'Panels',
            ],
            [
                'id' => 'demo-3',
                'url' => 'https://images.unsplash.com/photo-1475721027785-f74eccf877e2?auto=format&fit=crop&w=1200&q=80',
                'title' => 'Stage Presence & Atmosphere',
                'caption' => 'Dynamic lighting and acoustic production',
                'tag' => 'Atmosphere',
            ],
            [
                'id' => 'demo-4',
                'url' => 'https://images.unsplash.com/photo-1523580494863-6f3031224c94?auto=format&fit=crop&w=1200&q=80',
                'title' => 'VIP & Founders Networking',
                'caption' => 'Uniting creators and executives across borders',
                'tag' => 'Networking',
            ],
            [
                'id' => 'demo-5',
                'url' => 'https://images.unsplash.com/photo-1505373877841-8d25f7d46678?auto=format&fit=crop&w=1200&q=80',
                'title' => 'Interactive Workshop Pavilion',
                'caption' => 'Hands-on training and collaborative breakout tracks',
                'tag' => 'Audience',
            ],
            [
                'id' => 'demo-6',
                'url' => 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=1200&q=80',
                'title' => 'Evening Gala & Celebration',
                'caption' => 'Cultural performance and fellowship',
                'tag' => 'Atmosphere',
            ],
        ];

    $tags = array_values(array_unique(array_merge(['All'], array_column($galleryImages, 'tag'))));
@endphp

<x-layouts::dedicated :organization="$organization" :event="$event" title="{{ $galleryHeading }}">
    @include('tenants.pages.page-header', ['heading' => $galleryHeading, 'lead' => $galleryLead])

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
                    @click="activeFilter = '{{ $tag }}'"
                    class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all duration-200 cursor-pointer active:scale-[0.96]"
                    :class="activeFilter === '{{ $tag }}'
                        ? 'bg-[var(--dedicated-accent)] text-white shadow-xs'
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
                    x-show="activeFilter === 'All' || activeFilter === '{{ $item['tag'] }}'"
                    x-transition:enter="transition ease-out duration-250"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    @click="openLightbox(@js($item))"
                    class="group relative aspect-4/3 w-full overflow-hidden rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] shadow-2xs cursor-pointer transition-all duration-300 hover:shadow-xl hover:translate-y-[-3px]"
                >
                    <!-- Image -->
                    <img
                        src="{{ $item['url'] }}"
                        alt="{{ $item['title'] }}"
                        loading="lazy"
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
                            :src="activeImage.url"
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

    @include('tenants.partials.contact-cta')
</x-layouts::dedicated>

