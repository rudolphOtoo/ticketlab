<div class="space-y-6 max-w-7xl mx-auto" x-data="marketingStudio({
    publicUrl: @js($publicUrl),
    qrEndpoint: @js(route('api.qr')),
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
            <div class="flex items-center gap-2.5">
                <h1 class="text-xl font-bold tracking-tight text-[var(--text-primary)]">Marketing Studio</h1>
                <span class="rounded-full bg-[var(--accent-brand)]/10 px-2.5 py-0.5 text-[10px] font-semibold tracking-wide uppercase text-[var(--accent-brand)]">
                    Social Flyers & Vector QR
                </span>
            </div>
            <p class="text-xs text-[var(--text-secondary)] mt-1">
                Generate high-resolution social media flyers with instant aspect-ratio switching and print-ready vector QR passes.
            </p>
        </div>

        <div class="flex items-center gap-3">
            @if ($event)
                <a href="{{ $publicUrl }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-2 text-xs font-medium text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                    View Public Page
                </a>
            @endif
            <button type="button" @click="downloadFlyer()" id="export-png" class="cursor-pointer inline-flex items-center gap-2 rounded-lg bg-[var(--accent-brand)] px-4 py-2 text-xs font-semibold text-[var(--accent-brand-fg)] shadow-xs hover:opacity-90 active:scale-95 transition">
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
                    <button type="button" @click="activeTab = 'flyer'" :class="activeTab === 'flyer' ? 'bg-[var(--bg-surface)] text-[var(--text-primary)] shadow-xs font-semibold' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'" class="flex-1 py-2 rounded-lg transition text-center cursor-pointer">
                        🎨 Social Media Flyer
                    </button>
                    <button type="button" @click="activeTab = 'qr'" :class="activeTab === 'qr' ? 'bg-[var(--bg-surface)] text-[var(--text-primary)] shadow-xs font-semibold' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'" class="flex-1 py-2 rounded-lg transition text-center cursor-pointer">
                        ⚡ Vector Link QR Code
                    </button>
                </div>

                <!-- TAB 1: FLYER GENERATOR CONTROLS -->
                <div x-show="activeTab === 'flyer'" class="space-y-5">
                    
                    <!-- Aspect Ratio Switcher -->
                    <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 space-y-3">
                        <label class="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">Aspect Ratio Presets</label>
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
                        <label class="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">Theme Palette & Accents</label>
                        <div class="flex items-center gap-2">
                            @foreach (['amethyst' => '#7c3aed', 'emerald' => '#059669', 'amber' => '#d97706', 'rose' => '#e11d48', 'slate' => '#0f172a'] as $preset => $hex)
                                <button type="button" wire:click="setPreset('{{ $preset }}')" class="size-7 rounded-full transition-transform {{ $themePreset === $preset ? 'ring-2 ring-offset-2 ring-[var(--text-primary)] scale-110' : 'hover:scale-105' }}" style="background-color: {{ $hex }};" title="{{ ucfirst($preset) }}"></button>
                            @endforeach
                            <input type="color" wire:model.live="accentColor" class="size-7 rounded-full cursor-pointer border-0 bg-transparent p-0" title="Custom Hex">
                        </div>
                    </div>

                    <!-- Custom Background Image -->
                    <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 space-y-3">
                        <label for="flyer-background" class="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">Background Image</label>
                        <input id="flyer-background" type="file" accept="image/jpeg,image/png,image/webp" wire:model="backgroundImage" class="block w-full text-xs text-[var(--text-secondary)] file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-[var(--bg-subtle)] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[var(--text-primary)]">
                        <div wire:loading wire:target="backgroundImage" class="text-[11px] text-[var(--text-tertiary)]">Uploading…</div>
                        @error('backgroundImage') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                        @if ($backgroundUrl)
                            <button type="button" wire:click="removeBackground" class="cursor-pointer text-[11px] font-semibold text-rose-600 hover:underline">Remove background</button>
                        @endif
                    </div>

                    <!-- Flyer Copy & Details -->
                    <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 space-y-4">
                        <label class="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">Flyer Information</label>
                        
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
                        <label class="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">Public Event URL Pass</label>
                        
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
                                    <input type="color" x-model="qrFg" class="size-8 rounded-lg cursor-pointer border-0 bg-transparent p-0">
                                    <span class="text-xs font-mono" x-text="qrFg"></span>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-[var(--text-secondary)]">Background Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="qrBg" class="size-8 rounded-lg cursor-pointer border-0 bg-transparent p-0">
                                    <span class="text-xs font-mono" x-text="qrBg"></span>
                                </div>
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

            <!-- Preview Canvas Pane (Right 7 Cols) -->
            <div class="lg:col-span-7 space-y-4">
                
                <div class="rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-sm flex flex-col items-center justify-center min-h-[520px]">
                    
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

        init() {
            this.$nextTick(() => {
                this.canvas = document.getElementById('flyerCanvas');
                this.qrCanvas = document.getElementById('qrStandaloneCanvas');
                this.loadBackground();
                this.loadQr();
            });

            ['headline', 'tagline', 'venue', 'date', 'badge', 'accentColor', 'aspectRatio', 'showQr', 'featuredSpeakerId']
                .forEach((key) => this.$watch(key, () => this.renderFlyer()));
            ['qrFg', 'qrBg'].forEach((key) => this.$watch(key, () => this.loadQr()));
            this.$watch('background', () => this.loadBackground());
        },

        qrUrl(format, size = 600) {
            const params = new URLSearchParams({
                content: this.publicUrl,
                format,
                size: String(size),
                fg: this.qrFg || '#121316',
                bg: this.qrBg || '#ffffff',
                margin: '2',
            });

            return `${this.qrEndpoint}?${params.toString()}`;
        },

        loadImage(src) {
            return new Promise((resolve) => {
                if (!src) { resolve(null); return; }
                const image = new Image();
                image.onload = () => resolve(image);
                image.onerror = () => resolve(null);
                image.src = src;
            });
        },

        async loadQr() {
            this.qrImage = await this.loadImage(this.qrUrl('png'));
            this.renderFlyer();
            this.renderQrOnly();
        },

        async loadBackground() {
            this.backgroundImage = await this.loadImage(this.background);
            this.renderFlyer();
        },

        /**
         * Word-wraps text into at most maxLines lines, ellipsising the last one, and returns the y below the block.
         */
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

        drawCover(ctx, image, width, height) {
            const scale = Math.max(width / image.width, height / image.height);
            const w = image.width * scale;
            const h = image.height * scale;
            ctx.drawImage(image, (width - w) / 2, (height - h) / 2, w, h);
        },

        renderFlyer() {
            if (!this.canvas) return;

            const [width, height] = FLYER_SIZES[this.aspectRatio] || FLYER_SIZES['1:1'];
            const landscape = width > height;
            const pad = landscape ? 96 : 80;
            const accent = /^#[0-9a-f]{6}$/i.test(this.accentColor || '') ? this.accentColor : '#6366f1';
            const qrSize = landscape ? 300 : 220;
            const showQr = this.showQr && this.qrImage;
            const textWidth = landscape && showQr ? width - pad * 3 - qrSize : width - pad * 2;

            this.canvas.width = width;
            this.canvas.height = height;
            const ctx = this.canvas.getContext('2d');
            ctx.textBaseline = 'alphabetic';
            ctx.textAlign = 'left';

            // Background: uploaded image (cover + scrim) or the basalt gradient.
            if (this.backgroundImage) {
                this.drawCover(ctx, this.backgroundImage, width, height);
                const scrim = ctx.createLinearGradient(0, 0, 0, height);
                scrim.addColorStop(0, 'rgba(9,10,15,0.55)');
                scrim.addColorStop(1, 'rgba(9,10,15,0.9)');
                ctx.fillStyle = scrim;
                ctx.fillRect(0, 0, width, height);
            } else {
                const bg = ctx.createLinearGradient(0, 0, width, height);
                bg.addColorStop(0, '#090a0f');
                bg.addColorStop(0.5, '#12131a');
                bg.addColorStop(1, '#181926');
                ctx.fillStyle = bg;
                ctx.fillRect(0, 0, width, height);
            }

            const orb = ctx.createRadialGradient(width * 0.8, height * 0.2, 50, width * 0.8, height * 0.2, width * 0.6);
            orb.addColorStop(0, `${accent}55`);
            orb.addColorStop(1, 'transparent');
            ctx.fillStyle = orb;
            ctx.fillRect(0, 0, width, height);

            // Organisation + badge pill.
            let y = pad + 20;
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 28px sans-serif';
            y = this.wrapText(ctx, (this.orgName || '').toUpperCase(), pad, y, width - pad * 2, 34, 1) + 10;

            ctx.font = 'bold 18px sans-serif';
            const badge = (this.badge || '').slice(0, 40);
            const badgeWidth = ctx.measureText(badge).width + 40;
            ctx.fillStyle = accent;
            ctx.beginPath();
            ctx.roundRect(pad, y, badgeWidth, 40, 20);
            ctx.fill();
            ctx.fillStyle = '#ffffff';
            ctx.fillText(badge, pad + 20, y + 27);

            // Headline + tagline, wrapped to the available column.
            const titleSize = landscape ? 76 : (height > width ? 84 : 68);
            ctx.font = `900 ${titleSize}px sans-serif`;
            ctx.fillStyle = '#ffffff';
            y = this.wrapText(ctx, this.headline, pad, y + 70 + titleSize, textWidth, titleSize * 1.08, 3);

            ctx.font = '300 30px sans-serif';
            ctx.fillStyle = '#cbd5e1';
            y = this.wrapText(ctx, this.tagline, pad, y + 24, textWidth, 40, 3);

            // Featured speaker card.
            const speaker = this.speakers?.[this.featuredSpeakerId];
            if (speaker && y + 200 < height - 300) {
                const cardY = y + 40;
                ctx.fillStyle = 'rgba(255,255,255,0.06)';
                ctx.strokeStyle = 'rgba(255,255,255,0.15)';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.roundRect(pad, cardY, textWidth, 150, 24);
                ctx.fill();
                ctx.stroke();

                ctx.fillStyle = accent;
                ctx.beginPath();
                ctx.arc(pad + 75, cardY + 75, 45, 0, Math.PI * 2);
                ctx.fill();
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 36px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(speaker.name.charAt(0), pad + 75, cardY + 88);
                ctx.textAlign = 'left';

                const detailWidth = textWidth - 190;
                ctx.font = 'bold 32px sans-serif';
                this.wrapText(ctx, speaker.name, pad + 150, cardY + 58, detailWidth, 36, 1);
                ctx.fillStyle = '#cbd5e1';
                ctx.font = '22px sans-serif';
                this.wrapText(ctx, speaker.title || 'Keynote Speaker', pad + 150, cardY + 93, detailWidth, 26, 1);
                if (speaker.topic) {
                    ctx.fillStyle = '#fbbf24';
                    ctx.font = 'italic 20px sans-serif';
                    this.wrapText(ctx, `Topic: ${speaker.topic}`, pad + 150, cardY + 124, detailWidth, 24, 1);
                }
            }

            // Footer: date, venue and price, kept clear of the QR block.
            const footerWidth = showQr && !landscape ? width - pad * 3 - qrSize : textWidth;
            const bottom = height - pad;
            ctx.font = '26px sans-serif';
            const venueLines = Math.min(2, Math.ceil(ctx.measureText(this.venue || '').width / footerWidth) || 1);
            const footerTop = bottom - 64 - venueLines * 34 - 48;

            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 30px sans-serif';
            this.wrapText(ctx, this.date, pad, footerTop, footerWidth, 36, 1);
            ctx.fillStyle = '#94a3b8';
            ctx.font = '26px sans-serif';
            this.wrapText(ctx, this.venue, pad, footerTop + 46, footerWidth, 34, 2);

            ctx.font = 'bold 22px sans-serif';
            const priceWidth = Math.min(footerWidth, ctx.measureText(this.priceText).width + 40);
            ctx.fillStyle = 'rgba(255,255,255,0.1)';
            ctx.beginPath();
            ctx.roundRect(pad, bottom - 48, priceWidth, 48, 12);
            ctx.fill();
            ctx.fillStyle = '#38bdf8';
            this.wrapText(ctx, this.priceText, pad + 20, bottom - 16, priceWidth - 40, 26, 1);

            if (showQr) {
                const qrX = width - qrSize - pad;
                const qrY = height - qrSize - pad;
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.roundRect(qrX - 14, qrY - 14, qrSize + 28, qrSize + 28, 20);
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
        },

        renderQrOnly() {
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

        async downloadQr(format) {
            const response = await fetch(this.qrUrl(format, 1024), { credentials: 'same-origin' });
            if (!response.ok) return;
            const url = URL.createObjectURL(await response.blob());
            this.triggerDownload(url, `${this.slug()}-qr.${format}`);
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        },

        downloadQrPng() { return this.downloadQr('png'); },

        downloadQrSvg() { return this.downloadQr('svg'); },
    };
}
</script>
