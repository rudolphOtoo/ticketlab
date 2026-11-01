<div class="space-y-6 max-w-7xl mx-auto" x-data="marketingStudio({
    publicUrl: @js($publicUrl),
    qrEndpoint: @js(route('api.qr')),
    initialQrDataUri: @js($initialQrPngDataUri),
    headline: @entangle('flyerHeadline'),
    tagline: @entangle('flyerTagline'),
    venue: @entangle('flyerVenue'),
    date: @entangle('flyerDate'),
    badge: @entangle('badgeText'),
    accentColor: @entangle('accentColor'),
    aspectRatio: @entangle('selectedAspectRatio'),
    showQr: @entangle('showQrCode'),
    qrFg: @entangle('qrFgColor'),
    qrBg: @entangle('qrBgColor'),
    featuredSpeakerId: @entangle('featuredSpeakerId'),
    background: @entangle('backgroundUrl'),
    speakers: @js($speakers->mapWithKeys(fn ($spk) => [$spk->id => ['name' => $spk->name, 'title' => $spk->title_role, 'topic' => $spk->topic]])),
    priceText: @js($lowestTicketPrice !== null ? 'Tickets from GHS ' . number_format((float) $lowestTicketPrice, 2) : 'Free Admission'),
    orgName: @js($organization?->name ?? 'TicketLab')
})">

    <!-- Studio Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[var(--border-hairline)] pb-5">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-[var(--text-primary)]">Marketing Studio</h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1">
                Create social media flyers and download printable QR passes for your event.
            </p>
        </div>

        <div class="flex items-center gap-3">
            @if ($event)
                <a href="{{ $publicUrl }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-2 text-xs font-medium text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                    View Public Page
                </a>
            @endif
            <button type="button" @click="downloadFlyer()" id="export-png" class="cursor-pointer inline-flex items-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-[var(--color-primary-fg)] shadow-xs hover:opacity-90 transition press-scale">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                Export PNG Flyer
            </button>
        </div>
    </div>

    <!-- Studio Workspace Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start" x-data="{ activeTab: 'flyer' }">
            
            <!-- Controls Sidebar (Left 5 Cols) -->
            <div class="lg:col-span-5 space-y-5">
                
                <!-- Tab Switcher -->
                <div class="flex rounded-xl bg-[var(--bg-subtle)] p-1 border border-[var(--border-hairline)] text-xs font-medium">
                    <button type="button" @click="activeTab = 'flyer'" :class="activeTab === 'flyer' ? 'bg-[var(--bg-surface)] text-[var(--text-primary)] shadow-xs font-semibold' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'" class="flex-1 py-2 rounded-lg transition text-center cursor-pointer flex items-center justify-center gap-1.5 press-scale">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        <span>Social Media Flyer</span>
                    </button>
                    <button type="button" @click="activeTab = 'qr'" :class="activeTab === 'qr' ? 'bg-[var(--bg-surface)] text-[var(--text-primary)] shadow-xs font-semibold' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'" class="flex-1 py-2 rounded-lg transition text-center cursor-pointer flex items-center justify-center gap-1.5 press-scale">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                        <span>Vector Link QR Code</span>
                    </button>
                </div>

                <!-- TAB 1: FLYER GENERATOR CONTROLS -->
                <div x-show="activeTab === 'flyer'" class="space-y-5">
                    
                    <!-- Aspect Ratio Switcher -->
                    <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 space-y-3">
                        <label class="text-xs font-semibold text-[var(--text-secondary)]">Aspect Ratio Presets</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" wire:click="setAspectRatio('1:1')" class="cursor-pointer flex flex-col items-center justify-center p-3 rounded-xl border text-center transition {{ $selectedAspectRatio === '1:1' ? 'border-[var(--accent-brand)] bg-[var(--accent-brand)]/10 text-[var(--text-primary)] font-semibold' : 'border-[var(--border-hairline)] text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)]' }}">
                                <div class="size-6 border-2 border-current rounded-xs mb-1.5"></div>
                                <span class="text-xs font-medium">1:1 Square</span>
                                <span class="text-[10px] text-[var(--text-tertiary)]">Instagram / FB</span>
                            </button>

                            <button type="button" wire:click="setAspectRatio('9:16')" class="cursor-pointer flex flex-col items-center justify-center p-3 rounded-xl border text-center transition {{ $selectedAspectRatio === '9:16' ? 'border-[var(--accent-brand)] bg-[var(--accent-brand)]/10 text-[var(--text-primary)] font-semibold' : 'border-[var(--border-hairline)] text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)]' }}">
                                <div class="w-4 h-6 border-2 border-current rounded-xs mb-1.5"></div>
                                <span class="text-xs font-medium">9:16 Story</span>
                                <span class="text-[10px] text-[var(--text-tertiary)]">WhatsApp / TikTok</span>
                            </button>

                            <button type="button" wire:click="setAspectRatio('16:9')" class="cursor-pointer flex flex-col items-center justify-center p-3 rounded-xl border text-center transition {{ $selectedAspectRatio === '16:9' ? 'border-[var(--accent-brand)] bg-[var(--accent-brand)]/10 text-[var(--text-primary)] font-semibold' : 'border-[var(--border-hairline)] text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)]' }}">
                                <div class="w-6 h-3.5 border-2 border-current rounded-xs mb-1.5 mt-1.5"></div>
                                <span class="text-xs font-medium">16:9 Banner</span>
                                <span class="text-[10px] text-[var(--text-tertiary)]">X / Website Banner</span>
                            </button>
                        </div>
                    </div>

                    <!-- Theme & Brand Palette -->
                    <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 space-y-3">
                        <label class="text-xs font-semibold text-[var(--text-secondary)]">Theme Palette & Accents</label>
                        <div class="flex items-center gap-2">
                            @foreach (['amethyst' => '#7c3aed', 'emerald' => '#059669', 'amber' => '#d97706', 'rose' => '#e11d48', 'slate' => '#0f172a'] as $preset => $hex)
                                <button type="button" wire:click="setPreset('{{ $preset }}')" class="size-7 rounded-full transition-transform {{ $themePreset === $preset ? 'ring-2 ring-offset-2 ring-[var(--text-primary)] scale-110' : 'hover:scale-105' }}" style="background-color: {{ $hex }};" title="{{ ucfirst($preset) }}"></button>
                            @endforeach
                            <input type="color" wire:model.live="accentColor" class="size-7 rounded-full cursor-pointer border-0 bg-transparent p-0" title="Custom Hex">
                        </div>
                    </div>

                    <!-- Custom Background Image -->
                    <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <label for="flyer-background" class="text-xs font-semibold text-[var(--text-secondary)]">Event Background Image</label>
                            <span class="text-[10px] text-[var(--text-tertiary)]">Auto-framed hero</span>
                        </div>
                        <input id="flyer-background" type="file" accept="image/jpeg,image/png,image/webp" @change="onBackgroundSelected($event)" class="block w-full text-xs text-[var(--text-secondary)] file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-[var(--bg-subtle)] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[var(--text-primary)]">
                        <div x-show="hasBackground" class="flex items-center justify-between pt-1">
                            <span class="text-[11px] text-emerald-500 font-medium flex items-center gap-1">
                                <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                Image active
                            </span>
                            <button type="button" @click="clearBackground()" class="cursor-pointer text-[11px] font-semibold text-rose-500 hover:text-rose-400 hover:underline">Remove background</button>
                        </div>
                    </div>

                    <!-- Flyer Copy & Details -->
                    <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 space-y-4">
                        <label class="text-xs font-semibold text-[var(--text-secondary)]">Flyer Information</label>
                        
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-[var(--text-secondary)]">Main Headline</label>
                            <input type="text" x-model="headline" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--accent-brand)] transition">
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-medium text-[var(--text-secondary)]">Tagline / Subtitle</label>
                            <input type="text" x-model="tagline" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--accent-brand)] transition">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-[var(--text-secondary)]">Venue / Location</label>
                                <input type="text" x-model="venue" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--accent-brand)] transition">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-[var(--text-secondary)]">Date & Time</label>
                                <input type="text" x-model="date" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--accent-brand)] transition">
                            </div>
                        </div>

                        @if ($speakers->isNotEmpty())
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-[var(--text-secondary)]">Featured Speaker Badge</label>
                                <select wire:model.live="featuredSpeakerId" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--accent-brand)] transition">
                                    <option value="">None (Generic Event Flyer)</option>
                                    @foreach ($speakers as $spk)
                                        <option value="{{ $spk->id }}">{{ $spk->name }} ({{ $spk->title ?? $spk->title_role ?? 'Guest' }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="flex items-center justify-between pt-2 border-t border-[var(--border-hairline)]">
                            <span class="text-xs text-[var(--text-secondary)]">Show Public Ticket QR Code</span>
                            <input type="checkbox" x-model="showQr" class="size-4 rounded-sm border-[var(--border-hairline)] text-[var(--accent-brand)]">
                        </div>
                    </div>
                </div>

                <!-- TAB 2: VECTOR LINK QR CODE STUDIO -->
                <div x-show="activeTab === 'qr'" class="space-y-5" style="display: none;">
                    
                    <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 space-y-4">
                        <label class="text-xs font-semibold text-[var(--text-secondary)]">Public Event URL Pass</label>
                        
                        <div class="flex items-center gap-2">
                            <input type="text" readonly value="{{ $publicUrl }}" class="flex-1 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3 py-2 text-xs font-mono text-[var(--text-primary)] truncate">
                            <button type="button" @click="navigator.clipboard.writeText('{{ $publicUrl }}'); alert('Link copied!');" class="cursor-pointer rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-2 text-xs font-medium hover:bg-[var(--bg-subtle)] transition">
                                Copy
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-2">
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-[var(--text-secondary)]">Foreground Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="qrFg" @input="debouncedLoadQr()" class="size-8 rounded-lg cursor-pointer border-0 bg-transparent p-0">
                                    <span class="text-xs font-mono" x-text="qrFg"></span>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-[var(--text-secondary)]">Background Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="qrBg" @input="debouncedLoadQr()" class="size-8 rounded-lg cursor-pointer border-0 bg-transparent p-0">
                                    <span class="text-xs font-mono" x-text="qrBg"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Reset to Classic Black & White and Quick Contrast Presets -->
                        <div class="space-y-2 pt-1">
                            <label class="text-[11px] font-semibold text-[var(--text-secondary)]">Instant Contrast Presets</label>
                            <div class="flex flex-wrap gap-1.5">
                                <button type="button" @click="setQrColors('#121316', '#ffffff')" class="cursor-pointer text-[11px] px-2.5 py-1 rounded-md border border-[var(--border-hairline)] bg-[var(--bg-subtle)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-surface)] transition">
                                    Classic
                                </button>
                                <button type="button" @click="setQrColors('#7c3aed', '#ffffff')" class="cursor-pointer text-[11px] px-2.5 py-1 rounded-md border border-purple-500/30 bg-purple-500/10 text-purple-600 dark:text-purple-400 hover:bg-purple-500/20 transition">
                                    Amethyst
                                </button>
                                <button type="button" @click="setQrColors('#059669', '#ffffff')" class="cursor-pointer text-[11px] px-2.5 py-1 rounded-md border border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20 transition">
                                    Emerald
                                </button>
                                <button type="button" @click="setQrColors('#e11d48', '#ffffff')" class="cursor-pointer text-[11px] px-2.5 py-1 rounded-md border border-rose-500/30 bg-rose-500/10 text-rose-600 dark:text-rose-400 hover:bg-rose-500/20 transition">
                                    Rose
                                </button>
                                <button type="button" @click="setQrColors('#ffffff', '#090a0f')" class="cursor-pointer text-[11px] px-2.5 py-1 rounded-md border border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800 transition">
                                    Dark Mode
                                </button>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-[var(--border-hairline)] flex items-center gap-3">
                            <button type="button" @click="downloadQrPng()" class="flex-1 cursor-pointer rounded-lg bg-[var(--text-primary)] text-[var(--bg-canvas)] py-2.5 px-3 text-xs font-semibold hover:opacity-90 transition text-center">
                                Download PNG Pass
                            </button>
                            <button type="button" @click="downloadQrSvg()" class="flex-1 cursor-pointer rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] py-2.5 px-3 text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition text-center">
                                Download SVG Pass
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Preview Canvas Pane (Right 7 Cols - wire:ignore shields canvas from DOM wiping) -->
            <div class="lg:col-span-7 space-y-4" wire:ignore>
                
                <div class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-md flex flex-col items-center justify-center min-h-[520px]">
                    
                    <!-- Flyer Mode Canvas -->
                    <div x-show="activeTab === 'flyer'" class="flex flex-col items-center w-full">
                        <div class="relative max-w-full rounded-2xl overflow-hidden shadow-2xl border border-[var(--border-hairline)]">
                            <canvas id="flyerCanvas" class="max-w-full h-auto max-h-[580px] block"></canvas>
                        </div>
                        <p class="text-[11px] text-[var(--text-tertiary)] mt-3">
                            Rendered via High-DPI HTML5 Canvas • Direct 1-Click Export
                        </p>
                    </div>

                    <!-- QR Mode Canvas -->
                    <div x-show="activeTab === 'qr'" class="flex flex-col items-center space-y-4" style="display: none;">
                        <div class="p-6 rounded-2xl bg-white shadow-xl border border-[var(--border-hairline)]">
                            <canvas id="qrStandaloneCanvas" class="size-64"></canvas>
                        </div>
                        <div class="text-center">
                            <h4 class="text-sm font-bold text-[var(--text-primary)]">{{ $event?->title ?? 'Sample Event' }}</h4>
                            <p class="text-xs text-[var(--text-secondary)] mt-0.5">High-Resolution Vector QR Pass</p>
                        </div>
                    </div>

                </div>

            </div>

        </div>
</div>

<!-- Flyer & QR Engine: real scannable QR codes come from /api/qr; everything is drawn same-origin so exports never taint the canvas. -->
<script>
function marketingStudio(config) {
    const FLYER_SIZES = { '1:1': [1080, 1080], '9:16': [1080, 1920], '16:9': [1920, 1080] };

    return {
        ...config,
        canvas: null,
        qrCanvas: null,
        qrImage: null,
        backgroundImage: null,
        backgroundBlobUrl: null,
        hasBackground: Boolean(config.background),
        _qrDebounce: null,

        init() {
            this.$nextTick(async () => {
                this.canvas = document.getElementById('flyerCanvas');
                this.qrCanvas = document.getElementById('qrStandaloneCanvas');
                if (this.initialQrDataUri) {
                    this.qrImage = await this.loadImage(this.initialQrDataUri);
                    this.renderQrOnly();
                }
                this.loadBackground();
                this.loadQr();
            });

            ['headline', 'tagline', 'venue', 'date', 'badge', 'accentColor', 'aspectRatio', 'showQr', 'featuredSpeakerId']
                .forEach((key) => this.$watch(key, () => this.renderFlyer()));
            ['qrFg', 'qrBg'].forEach((key) => this.$watch(key, () => this.debouncedLoadQr()));
            this.$watch('activeTab', (tab) => {
                if (tab === 'qr') {
                    this.$nextTick(() => this.renderQrOnly());
                } else {
                    this.$nextTick(() => this.renderFlyer());
                }
            });
            this.$watch('background', () => this.loadBackground());
        },

        onBackgroundSelected(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            if (this.backgroundBlobUrl) {
                URL.revokeObjectURL(this.backgroundBlobUrl);
            }
            this.backgroundBlobUrl = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => {
                this.backgroundImage = img;
                this.hasBackground = true;
                this.renderFlyer();
            };
            img.src = this.backgroundBlobUrl;
        },

        clearBackground() {
            if (this.backgroundBlobUrl) {
                URL.revokeObjectURL(this.backgroundBlobUrl);
                this.backgroundBlobUrl = null;
            }
            this.backgroundImage = null;
            this.hasBackground = false;
            const input = document.getElementById('flyer-background');
            if (input) input.value = '';
            if (this.$wire && typeof this.$wire.removeBackground === 'function') {
                this.$wire.removeBackground();
            }
            this.renderFlyer();
        },

        resetQrClassic() {
            this.setQrColors('#121316', '#ffffff');
        },

        setQrColors(fg, bg) {
            this.qrFg = fg;
            this.qrBg = bg;
            this.loadQr();
        },

        safeRoundRect(ctx, x, y, w, h, r) {
            if (typeof ctx.roundRect === 'function') {
                ctx.roundRect(x, y, w, h, r);
            } else {
                const radius = Math.min(typeof r === 'number' ? r : 0, w / 2, h / 2);
                ctx.moveTo(x + radius, y);
                ctx.arcTo(x + w, y, x + w, y + h, radius);
                ctx.arcTo(x + w, y + h, x, y + h, radius);
                ctx.arcTo(x, y + h, x, y, radius);
                ctx.arcTo(x, y, x + w, y, radius);
                ctx.closePath();
            }
        },

        debouncedLoadQr() {
            clearTimeout(this._qrDebounce);
            this._qrDebounce = setTimeout(() => this.loadQr(), 160);
        },

        qrUrl(format, size = 600) {
            const cleanFg = String(this.qrFg || '#121316').trim().replace(/^#/, '');
            const cleanBg = String(this.qrBg || '#ffffff').trim().replace(/^#/, '');
            const params = new URLSearchParams({
                content: this.publicUrl,
                format,
                size: String(size),
                fg: cleanFg,
                bg: cleanBg,
                margin: '2',
                _t: String(Date.now()),
            });

            return `${this.qrEndpoint}?${params.toString()}`;
        },

        loadImage(src) {
            return new Promise((resolve) => {
                if (!src) { resolve(null); return; }
                const image = new Image();
                image.crossOrigin = 'anonymous';
                image.onload = () => resolve(image);
                image.onerror = (e) => {
                    console.warn('Image load fallback', e);
                    resolve(null);
                };
                image.src = src;
            });
        },

        async loadQr() {
            try {
                const response = await fetch(this.qrUrl('png', 600), {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'image/png' }
                });

                if (response.ok) {
                    const blob = await response.blob();
                    const objectUrl = URL.createObjectURL(blob);
                    const img = await this.loadImage(objectUrl);
                    if (img) {
                        this.qrImage = img;
                    }
                    setTimeout(() => URL.revokeObjectURL(objectUrl), 2000);
                } else {
                    console.warn('loadQr response status:', response.status);
                }
            } catch (err) {
                console.warn('loadQr network fallback:', err);
            }

            this.renderFlyer();
            this.renderQrOnly();
        },

        async loadBackground() {
            if (this.backgroundBlobUrl) {
                return; // User has an active local preview
            }
            if (this.background) {
                this.backgroundImage = await this.loadImage(this.background);
                this.hasBackground = Boolean(this.backgroundImage);
            } else {
                this.backgroundImage = null;
                this.hasBackground = false;
            }
            this.renderFlyer();
        },

        wrapText(ctx, text, x, y, maxWidth, lineHeight, maxLines) {
            const words = String(text || '').split(/\s+/).filter(Boolean);
            const lines = [];
            let line = '';

            for (const word of words) {
                const candidate = line ? `${line} ${word}` : word;
                if (ctx.measureText(candidate).width > maxWidth && line) {
                    lines.push(line);
                    line = word;
                } else {
                    line = candidate;
                }
            }
            if (line) lines.push(line);

            if (lines.length > maxLines) {
                lines.length = maxLines;
                let last = lines[maxLines - 1];
                while (last.length > 1 && ctx.measureText(`${last}…`).width > maxWidth) last = last.slice(0, -1);
                lines[maxLines - 1] = `${last.trimEnd()}…`;
            }

            lines.forEach((text, index) => ctx.fillText(text, x, y + index * lineHeight));

            return y + lines.length * lineHeight;
        },

        drawCoverInRect(ctx, image, rx, ry, rw, rh, radius = 0) {
            if (!image || !image.width || !image.height) return;
            try {
                ctx.save();
                if (radius > 0) {
                    ctx.beginPath();
                    this.safeRoundRect(ctx, rx, ry, rw, rh, radius);
                    ctx.clip();
                } else {
                    ctx.beginPath();
                    ctx.rect(rx, ry, rw, rh);
                    ctx.clip();
                }
                const scale = Math.max(rw / image.width, rh / image.height);
                const w = image.width * scale;
                const h = image.height * scale;
                ctx.drawImage(image, rx + (rw - w) / 2, ry + (rh - h) / 2, w, h);
                ctx.restore();
            } catch (e) {
                console.warn('drawCoverInRect notice:', e);
            }
        },

        renderFlyer() {
            if (!this.canvas) return;
            try {
                const [width, height] = FLYER_SIZES[this.aspectRatio] || FLYER_SIZES['1:1'];
                const landscape = width > height;
                const isStory = height > width;

                const pad = isStory ? 96 : (landscape ? 90 : 80);
                const accent = /^#[0-9a-f]{6}$/i.test(this.accentColor || '') ? this.accentColor : '#6366f1';
                
                const qrSize = isStory ? 320 : (landscape ? 290 : 250);
                const showQr = this.showQr && this.qrImage;
                const textWidth = landscape 
                    ? (this.backgroundImage ? width * 0.52 - pad : (showQr ? width - pad * 3 - qrSize : width - pad * 2)) 
                    : (showQr && !landscape && !isStory ? width - pad * 3 - qrSize : width - pad * 2);

                this.canvas.width = width;
                this.canvas.height = height;
                const ctx = this.canvas.getContext('2d');
                ctx.textBaseline = 'alphabetic';
                ctx.textAlign = 'left';

                // 1. Base Obsidian/Basalt Gradient
                const bg = ctx.createLinearGradient(0, 0, width, height);
                bg.addColorStop(0, '#090a0f');
                bg.addColorStop(0.5, '#11121a');
                bg.addColorStop(1, '#181926');
                ctx.fillStyle = bg;
                ctx.fillRect(0, 0, width, height);

                // 2. Structured Hero Visual (Allocates dedicated zone preserving text legibility)
                if (this.backgroundImage) {
                    if (landscape) {
                        // 16:9 Landscape: Framed hero photo card on right side
                        const frameW = Math.round(width * 0.40);
                        const frameH = height - pad * 2;
                        const frameX = width - pad - frameW;
                        const frameY = pad;

                        ctx.save();
                        ctx.shadowColor = `${accent}66`;
                        ctx.shadowBlur = 30;
                        ctx.fillStyle = 'rgba(255,255,255,0.05)';
                        ctx.beginPath();
                        this.safeRoundRect(ctx, frameX, frameY, frameW, frameH, 24);
                        ctx.fill();
                        ctx.restore();

                        this.drawCoverInRect(ctx, this.backgroundImage, frameX, frameY, frameW, frameH, 24);

                        ctx.strokeStyle = 'rgba(255,255,255,0.25)';
                        ctx.lineWidth = 2;
                        ctx.beginPath();
                        this.safeRoundRect(ctx, frameX, frameY, frameW, frameH, 24);
                        ctx.stroke();
                    } else if (isStory) {
                        // 9:16 Story: Top 44% hero visual with smooth gradient falloff
                        const heroH = Math.round(height * 0.44);
                        this.drawCoverInRect(ctx, this.backgroundImage, 0, 0, width, heroH);

                        const fade = ctx.createLinearGradient(0, heroH - 220, 0, heroH);
                        fade.addColorStop(0, 'rgba(9,10,15,0)');
                        fade.addColorStop(0.7, 'rgba(9,10,15,0.85)');
                        fade.addColorStop(1, '#090a0f');
                        ctx.fillStyle = fade;
                        ctx.fillRect(0, heroH - 220, width, 220);

                        const topVignette = ctx.createLinearGradient(0, 0, 0, 180);
                        topVignette.addColorStop(0, 'rgba(9,10,15,0.8)');
                        topVignette.addColorStop(1, 'rgba(9,10,15,0)');
                        ctx.fillStyle = topVignette;
                        ctx.fillRect(0, 0, width, 180);
                    } else {
                        // 1:1 Square: Top 46% hero visual with smooth gradient falloff
                        const heroH = Math.round(height * 0.46);
                        this.drawCoverInRect(ctx, this.backgroundImage, 0, 0, width, heroH);

                        const fade = ctx.createLinearGradient(0, heroH - 170, 0, heroH);
                        fade.addColorStop(0, 'rgba(9,10,15,0)');
                        fade.addColorStop(0.7, 'rgba(9,10,15,0.88)');
                        fade.addColorStop(1, '#090a0f');
                        ctx.fillStyle = fade;
                        ctx.fillRect(0, heroH - 170, width, 170);

                        const topVignette = ctx.createLinearGradient(0, 0, 0, 140);
                        topVignette.addColorStop(0, 'rgba(9,10,15,0.75)');
                        topVignette.addColorStop(1, 'rgba(9,10,15,0)');
                        ctx.fillStyle = topVignette;
                        ctx.fillRect(0, 0, width, 140);
                    }
                } else {
                    // Ambient Brand Glow Orbs when no image
                    const orb1 = ctx.createRadialGradient(width * 0.85, height * 0.15, 60, width * 0.85, height * 0.15, width * 0.7);
                    orb1.addColorStop(0, `${accent}44`);
                    orb1.addColorStop(1, 'transparent');
                    ctx.fillStyle = orb1;
                    ctx.fillRect(0, 0, width, height);

                    if (isStory) {
                        const orb2 = ctx.createRadialGradient(width * 0.15, height * 0.7, 50, width * 0.15, height * 0.7, width * 0.6);
                        orb2.addColorStop(0, `${accent}22`);
                        orb2.addColorStop(1, 'transparent');
                        ctx.fillStyle = orb2;
                        ctx.fillRect(0, 0, width, height);
                    }
                }

                // 3. Organisation Header
                const orgFontSize = isStory ? 34 : (landscape ? 30 : 28);
                let y = isStory ? pad + 40 : (landscape ? pad + 10 : pad + 20);
                ctx.fillStyle = '#ffffff';
                ctx.font = `bold ${orgFontSize}px sans-serif`;
                try { ctx.letterSpacing = '1px'; } catch (e) {}
                y = this.wrapText(ctx, (this.orgName || '').toUpperCase(), pad, y, width - pad * 2, orgFontSize + 8, 1) + (isStory ? 24 : 14);

                // 4. Badge Pill
                const badgeFontSize = isStory ? 22 : 18;
                ctx.font = `bold ${badgeFontSize}px sans-serif`;
                const badge = (this.badge || '').slice(0, 40);
                const badgePaddingX = isStory ? 28 : 20;
                const badgeHeight = isStory ? 50 : 40;
                const badgeWidth = ctx.measureText(badge).width + (badgePaddingX * 2);
                
                ctx.fillStyle = accent;
                ctx.beginPath();
                this.safeRoundRect(ctx, pad, y, badgeWidth, badgeHeight, badgeHeight / 2);
                ctx.fill();
                ctx.fillStyle = '#ffffff';
                ctx.fillText(badge, pad + badgePaddingX, y + (isStory ? 33 : 26));
                y += badgeHeight + (isStory ? 50 : 36);

                // 5. Headline & Tagline
                const titleSize = isStory ? 104 : (landscape ? 76 : 82);
                ctx.font = `900 ${titleSize}px sans-serif`;
                ctx.fillStyle = '#ffffff';
                y = this.wrapText(ctx, this.headline, pad, y + titleSize * 0.8, textWidth, titleSize * 1.1, 3);

                const taglineSize = isStory ? 38 : (landscape ? 30 : 32);
                ctx.font = `300 ${taglineSize}px sans-serif`;
                ctx.fillStyle = '#cbd5e1';
                y = this.wrapText(ctx, this.tagline, pad, y + (isStory ? 36 : 24), textWidth, taglineSize * 1.35, 3);

                // 6. Featured Speaker Card (if selected)
                const speaker = this.speakers?.[this.featuredSpeakerId];
                const maxCardBottom = height - (isStory ? 600 : (landscape ? 350 : 420));
                if (speaker && y + 60 < maxCardBottom) {
                    const cardY = y + (isStory ? 50 : 35);
                    const cardHeight = isStory ? 190 : 150;
                    ctx.fillStyle = 'rgba(255,255,255,0.06)';
                    ctx.strokeStyle = 'rgba(255,255,255,0.18)';
                    ctx.lineWidth = 2;
                    ctx.beginPath();
                    this.safeRoundRect(ctx, pad, cardY, textWidth, cardHeight, 24);
                    ctx.fill();
                    ctx.stroke();

                    const avatarRadius = isStory ? 55 : 45;
                    const avatarCenterY = cardY + cardHeight / 2;
                    ctx.fillStyle = accent;
                    ctx.beginPath();
                    ctx.arc(pad + avatarRadius + 30, avatarCenterY, avatarRadius, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.fillStyle = '#ffffff';
                    ctx.font = `bold ${isStory ? 44 : 36}px sans-serif`;
                    ctx.textAlign = 'center';
                    ctx.fillText(speaker.name.charAt(0), pad + avatarRadius + 30, avatarCenterY + (isStory ? 16 : 13));
                    ctx.textAlign = 'left';

                    const speakerTextX = pad + (avatarRadius * 2) + 50;
                    const detailWidth = textWidth - (avatarRadius * 2) - 70;
                    ctx.font = `bold ${isStory ? 38 : 32}px sans-serif`;
                    this.wrapText(ctx, speaker.name, speakerTextX, cardY + (isStory ? 65 : 55), detailWidth, isStory ? 44 : 36, 1);
                    ctx.fillStyle = '#cbd5e1';
                    ctx.font = `${isStory ? 26 : 22}px sans-serif`;
                    this.wrapText(ctx, speaker.title || 'Keynote Speaker', speakerTextX, cardY + (isStory ? 110 : 92), detailWidth, isStory ? 32 : 26, 1);
                    if (speaker.topic) {
                        ctx.fillStyle = '#fbbf24';
                        ctx.font = `italic ${isStory ? 24 : 20}px sans-serif`;
                        this.wrapText(ctx, `Topic: ${speaker.topic}`, speakerTextX, cardY + (isStory ? 150 : 124), detailWidth, isStory ? 28 : 24, 1);
                    }
                }

                // 7. Footer: Date, Venue, Price, and QR Pass
                if (isStory) {
                    const bottom = height - pad - 40;
                    const qrY = bottom - qrSize;
                    const qrX = width - pad - qrSize;

                    const footerTextWidth = width - pad * 2 - qrSize - 40;
                    ctx.fillStyle = '#ffffff';
                    ctx.font = 'bold 38px sans-serif';
                    this.wrapText(ctx, this.date, pad, qrY + 30, footerTextWidth, 46, 1);

                    ctx.fillStyle = '#94a3b8';
                    ctx.font = '30px sans-serif';
                    this.wrapText(ctx, this.venue, pad, qrY + 84, footerTextWidth, 38, 2);

                    const priceFontSize = 28;
                    ctx.font = `bold ${priceFontSize}px sans-serif`;
                    const priceWidth = ctx.measureText(this.priceText).width + 48;
                    ctx.fillStyle = 'rgba(255,255,255,0.12)';
                    ctx.beginPath();
                    this.safeRoundRect(ctx, pad, qrY + 160, priceWidth, 60, 16);
                    ctx.fill();
                    ctx.fillStyle = '#38bdf8';
                    this.wrapText(ctx, this.priceText, pad + 24, qrY + 202, priceWidth - 48, 30, 1);

                    if (showQr) {
                        ctx.fillStyle = '#ffffff';
                        ctx.beginPath();
                        this.safeRoundRect(ctx, qrX - 16, qrY - 16, qrSize + 32, qrSize + 32, 24);
                        ctx.fill();
                        ctx.imageSmoothingEnabled = false;
                        ctx.drawImage(this.qrImage, qrX, qrY, qrSize, qrSize);
                        ctx.imageSmoothingEnabled = true;

                        ctx.fillStyle = '#94a3b8';
                        ctx.font = 'bold 20px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.fillText('SCAN FOR TICKETS', qrX + qrSize / 2, qrY - 28);
                        ctx.textAlign = 'left';
                    }
                } else {
                    const footerWidth = showQr && !landscape ? width - pad * 3 - qrSize : textWidth;
                    const bottom = height - pad;
                    ctx.font = '26px sans-serif';
                    const venueLines = Math.min(2, Math.ceil(ctx.measureText(this.venue || '').width / footerWidth) || 1);
                    const footerTop = bottom - 64 - venueLines * 34 - 48;

                    ctx.fillStyle = '#ffffff';
                    ctx.font = 'bold 32px sans-serif';
                    this.wrapText(ctx, this.date, pad, footerTop, footerWidth, 38, 1);
                    ctx.fillStyle = '#94a3b8';
                    ctx.font = '26px sans-serif';
                    this.wrapText(ctx, this.venue, pad, footerTop + 46, footerWidth, 34, 2);

                    ctx.font = 'bold 22px sans-serif';
                    const priceWidth = Math.min(footerWidth, ctx.measureText(this.priceText).width + 40);
                    ctx.fillStyle = 'rgba(255,255,255,0.1)';
                    ctx.beginPath();
                    this.safeRoundRect(ctx, pad, bottom - 48, priceWidth, 48, 12);
                    ctx.fill();
                    ctx.fillStyle = '#38bdf8';
                    this.wrapText(ctx, this.priceText, pad + 20, bottom - 16, priceWidth - 40, 26, 1);

                    if (showQr) {
                        const qrX = width - qrSize - pad;
                        const qrY = height - qrSize - pad;
                        ctx.fillStyle = '#ffffff';
                        ctx.beginPath();
                        this.safeRoundRect(ctx, qrX - 14, qrY - 14, qrSize + 28, qrSize + 28, 20);
                        ctx.fill();
                        ctx.imageSmoothingEnabled = false;
                        ctx.drawImage(this.qrImage, qrX, qrY, qrSize, qrSize);
                        ctx.imageSmoothingEnabled = true;

                        ctx.fillStyle = '#94a3b8';
                        ctx.font = 'bold 16px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.fillText('SCAN FOR TICKETS', qrX + qrSize / 2, qrY - 26);
                        ctx.textAlign = 'left';
                    }
                }
            } catch (err) {
                console.error('Flyer render caught exception:', err);
            }
        },

        renderQrOnly() {
            if (!this.qrCanvas) {
                this.qrCanvas = document.getElementById('qrStandaloneCanvas');
            }
            if (!this.qrCanvas) return;
            const size = 512;
            this.qrCanvas.width = size;
            this.qrCanvas.height = size;
            const ctx = this.qrCanvas.getContext('2d');
            ctx.fillStyle = this.qrBg || '#ffffff';
            ctx.fillRect(0, 0, size, size);
            if (this.qrImage) {
                ctx.imageSmoothingEnabled = false;
                ctx.drawImage(this.qrImage, 0, 0, size, size);
            } else {
                ctx.fillStyle = this.qrFg || '#121316';
                ctx.font = '16px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('Generating QR...', size / 2, size / 2);
                ctx.textAlign = 'left';
            }
        },

        slug() {
            return (this.headline || 'event').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'event';
        },

        triggerDownload(href, filename) {
            const link = document.createElement('a');
            link.download = filename;
            link.href = href;
            document.body.appendChild(link);
            link.click();
            link.remove();
        },

        downloadFlyer() {
            if (!this.canvas) return;
            this.triggerDownload(this.canvas.toDataURL('image/png'), `${this.slug()}-${this.aspectRatio.replace(':', 'x')}.png`);
        },

        downloadQrPng() {
            this.renderQrOnly();
            if (!this.qrCanvas) {
                this.qrCanvas = document.getElementById('qrStandaloneCanvas');
            }
            if (!this.qrCanvas) return;
            const dataUrl = this.qrCanvas.toDataURL('image/png');
            this.triggerDownload(dataUrl, `${this.slug()}-pass-qr.png`);
        },

        async downloadQrSvg() {
            try {
                const response = await fetch(this.qrUrl('svg', 1024), { credentials: 'same-origin' });
                if (response.ok) {
                    const blob = await response.blob();
                    const url = URL.createObjectURL(blob);
                    this.triggerDownload(url, `${this.slug()}-pass-qr.svg`);
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                } else {
                    this.downloadQrPng();
                }
            } catch (err) {
                console.warn('SVG download fallback:', err);
                this.downloadQrPng();
            }
        },
    };
}
</script>
