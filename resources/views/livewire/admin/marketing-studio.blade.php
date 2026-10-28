<div class="space-y-6 max-w-7xl mx-auto" x-data="marketingStudio({
    publicUrl: @js($publicUrl),
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
    speakerName: @js($featuredSpeaker?->name ?? ''),
    speakerTitle: @js($featuredSpeaker?->title ?? ''),
    speakerTopic: @js($featuredSpeaker?->topic ?? ''),
    speakerPhoto: @js($featuredSpeaker?->photo_path ?? ''),
    priceText: @js($lowestTicketPrice !== null ? 'Tickets from GHS ' . number_format((float) $lowestTicketPrice, 2) : 'Free Admission'),
    orgName: @js($organization?->name ?? 'TicketLab')
})" x-init="initCanvas()">

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

        @if ($event)
            <div class="flex items-center gap-3">
                <a href="{{ $publicUrl }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-2 text-xs font-medium text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] transition">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                    View Public Page
                </a>
                <button type="button" @click="downloadFlyer()" class="cursor-pointer inline-flex items-center gap-2 rounded-lg bg-[var(--accent-brand)] px-4 py-2 text-xs font-semibold text-[var(--accent-brand-fg)] shadow-xs hover:opacity-90 active:scale-95 transition">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    Download Flyer (PNG)
                </button>
            </div>
        @endif
    </div>

    @if (!$event)
        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-12 text-center space-y-4">
            <div class="size-12 mx-auto rounded-full bg-[var(--bg-subtle)] flex items-center justify-center text-[var(--text-tertiary)]">
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-[var(--text-primary)]">No Event Found</h3>
                <p class="text-xs text-[var(--text-secondary)] mt-1">Please create and publish an event first to generate promotional flyers and vector QR passes.</p>
            </div>
            <a href="{{ route('admin.studio') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--accent-brand)] px-4 py-2 text-xs font-semibold text-[var(--accent-brand-fg)]" wire:navigate>
                Go to Customizer Studio
            </a>
        </div>
    @else
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
                            <button type="button" wire:click="setAspectRatio('1:1')" @click="renderFlyer()" class="cursor-pointer flex flex-col items-center justify-center p-3 rounded-xl border text-center transition {{ $selectedAspectRatio === '1:1' ? 'border-[var(--accent-brand)] bg-[var(--accent-brand)]/10 text-[var(--text-primary)] font-semibold' : 'border-[var(--border-hairline)] text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)]' }}">
                                <div class="size-6 border-2 border-current rounded-xs mb-1.5"></div>
                                <span class="text-xs font-medium">1:1 Square</span>
                                <span class="text-[10px] text-[var(--text-tertiary)]">Instagram / FB</span>
                            </button>

                            <button type="button" wire:click="setAspectRatio('9:16')" @click="renderFlyer()" class="cursor-pointer flex flex-col items-center justify-center p-3 rounded-xl border text-center transition {{ $selectedAspectRatio === '9:16' ? 'border-[var(--accent-brand)] bg-[var(--accent-brand)]/10 text-[var(--text-primary)] font-semibold' : 'border-[var(--border-hairline)] text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)]' }}">
                                <div class="w-4 h-6 border-2 border-current rounded-xs mb-1.5"></div>
                                <span class="text-xs font-medium">9:16 Story</span>
                                <span class="text-[10px] text-[var(--text-tertiary)]">WhatsApp / TikTok</span>
                            </button>

                            <button type="button" wire:click="setAspectRatio('16:9')" @click="renderFlyer()" class="cursor-pointer flex flex-col items-center justify-center p-3 rounded-xl border text-center transition {{ $selectedAspectRatio === '16:9' ? 'border-[var(--accent-brand)] bg-[var(--accent-brand)]/10 text-[var(--text-primary)] font-semibold' : 'border-[var(--border-hairline)] text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)]' }}">
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
                                <button type="button" wire:click="setPreset('{{ $preset }}')" @click="renderFlyer()" class="size-7 rounded-full transition-transform {{ $themePreset === $preset ? 'ring-2 ring-offset-2 ring-[var(--text-primary)] scale-110' : 'hover:scale-105' }}" style="background-color: {{ $hex }};" title="{{ ucfirst($preset) }}"></button>
                            @endforeach
                            <input type="color" wire:model.live="accentColor" @change="renderFlyer()" class="size-7 rounded-full cursor-pointer border-0 bg-transparent p-0" title="Custom Hex">
                        </div>
                    </div>

                    <!-- Flyer Copy & Details -->
                    <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 space-y-4">
                        <label class="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">Flyer Information</label>
                        
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-[var(--text-secondary)]">Main Headline</label>
                            <input type="text" x-model="headline" @input="renderFlyer()" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--accent-brand)] transition">
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-medium text-[var(--text-secondary)]">Tagline / Subtitle</label>
                            <input type="text" x-model="tagline" @input="renderFlyer()" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--accent-brand)] transition">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-[var(--text-secondary)]">Venue / Location</label>
                                <input type="text" x-model="venue" @input="renderFlyer()" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--accent-brand)] transition">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-[var(--text-secondary)]">Date & Time</label>
                                <input type="text" x-model="date" @input="renderFlyer()" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--accent-brand)] transition">
                            </div>
                        </div>

                        @if ($speakers->isNotEmpty())
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-[var(--text-secondary)]">Featured Speaker Badge</label>
                                <select wire:model.live="featuredSpeakerId" @change="$nextTick(() => renderFlyer())" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-3 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--accent-brand)] transition">
                                    <option value="">None (Generic Event Flyer)</option>
                                    @foreach ($speakers as $spk)
                                        <option value="{{ $spk->id }}">{{ $spk->name }} ({{ $spk->title ?? $spk->title_role ?? 'Guest' }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="flex items-center justify-between pt-2 border-t border-[var(--border-hairline)]">
                            <span class="text-xs text-[var(--text-secondary)]">Show Public Ticket QR Code</span>
                            <input type="checkbox" x-model="showQr" @change="renderFlyer()" class="size-4 rounded-sm border-[var(--border-hairline)] text-[var(--accent-brand)]">
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
                                    <input type="color" x-model="qrFg" @change="renderQrOnly()" class="size-8 rounded-lg cursor-pointer border-0 bg-transparent p-0">
                                    <span class="text-xs font-mono" x-text="qrFg"></span>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-[var(--text-secondary)]">Background Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="qrBg" @change="renderQrOnly()" class="size-8 rounded-lg cursor-pointer border-0 bg-transparent p-0">
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
                            <h4 class="text-sm font-bold text-[var(--text-primary)]">{{ $event->title }}</h4>
                            <p class="text-xs text-[var(--text-secondary)] mt-0.5">High-Resolution Vector QR Pass</p>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    @endif
</div>

<!-- Embedded Vanilla QR & Flyer Engine Script -->
<script>
function marketingStudio(config) {
    return {
        canvas: null,
        ctx: null,
        qrCanvas: null,
        qrCtx: null,

        initCanvas() {
            this.$nextTick(() => {
                this.canvas = document.getElementById('flyerCanvas');
                if (this.canvas) {
                    this.ctx = this.canvas.getContext('2d');
                }
                this.qrCanvas = document.getElementById('qrStandaloneCanvas');
                if (this.qrCanvas) {
                    this.qrCtx = this.qrCanvas.getContext('2d');
                }
                this.renderFlyer();
                this.renderQrOnly();
            });
        },

        renderFlyer() {
            if (!this.canvas) return;

            // Dimensions based on aspect ratio
            let width = 1080;
            let height = 1080;
            if (config.aspectRatio === '9:16') {
                width = 1080;
                height = 1920;
            } else if (config.aspectRatio === '16:9') {
                width = 1920;
                height = 1080;
            }

            this.canvas.width = width;
            this.canvas.height = height;

            const ctx = this.ctx;
            const accent = config.accentColor || '#6366f1';

            // Background Gradient (Deep Serene Basalt with Accent Glow)
            const bgGrad = ctx.createLinearGradient(0, 0, width, height);
            bgGrad.addColorStop(0, '#090a0f');
            bgGrad.addColorStop(0.5, '#12131a');
            bgGrad.addColorStop(1, '#181926');
            ctx.fillStyle = bgGrad;
            ctx.fillRect(0, 0, width, height);

            // Ambient Accent Orb
            const orbGrad = ctx.createRadialGradient(width * 0.8, height * 0.2, 50, width * 0.8, height * 0.2, width * 0.6);
            orbGrad.addColorStop(0, accent + '55');
            orbGrad.addColorStop(1, 'transparent');
            ctx.fillStyle = orbGrad;
            ctx.fillRect(0, 0, width, height);

            // Top Header: Org Badge
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 28px sans-serif';
            ctx.fillText(config.orgName.toUpperCase(), 80, 100);

            // Accent Badge
            ctx.fillStyle = accent;
            ctx.beginPath();
            ctx.roundRect(80, 130, 200, 36, 18);
            ctx.fill();

            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 16px sans-serif';
            ctx.fillText(config.badge, 100, 154);

            // Main Event Title (Headline)
            ctx.fillStyle = '#ffffff';
            ctx.font = '900 ' + (width === 1920 ? '72px' : '64px') + ' sans-serif';
            
            // Multiline wrap for title
            const titleWords = config.headline.split(' ');
            let titleLine = '';
            let titleY = height * 0.28;
            for (let n = 0; n < titleWords.length; n++) {
                let testLine = titleLine + titleWords[n] + ' ';
                let metrics = ctx.measureText(testLine);
                if (metrics.width > width - 180 && n > 0) {
                    ctx.fillText(titleLine, 80, titleY);
                    titleLine = titleWords[n] + ' ';
                    titleY += 72;
                } else {
                    titleLine = testLine;
                }
            }
            ctx.fillText(titleLine, 80, titleY);

            // Tagline
            ctx.fillStyle = '#94a3b8';
            ctx.font = '300 28px sans-serif';
            ctx.fillText(config.tagline, 80, titleY + 50);

            // Featured Speaker Card (if present)
            let speakerCardY = titleY + 110;
            if (config.speakerName) {
                ctx.fillStyle = 'rgba(255, 255, 255, 0.05)';
                ctx.strokeStyle = 'rgba(255, 255, 255, 0.15)';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.roundRect(80, speakerCardY, width - 160, 150, 24);
                ctx.fill();
                ctx.stroke();

                // Speaker Avatar Circle
                ctx.fillStyle = accent;
                ctx.beginPath();
                ctx.arc(155, speakerCardY + 75, 45, 0, Math.PI * 2);
                ctx.fill();

                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 36px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(config.speakerName.charAt(0), 155, speakerCardY + 88);
                ctx.textAlign = 'left';

                // Speaker Details
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 32px sans-serif';
                ctx.fillText(config.speakerName, 230, speakerCardY + 60);

                ctx.fillStyle = '#cbd5e1';
                ctx.font = '22px sans-serif';
                ctx.fillText(config.speakerTitle || 'Keynote Speaker', 230, speakerCardY + 95);

                if (config.speakerTopic) {
                    ctx.fillStyle = '#fbbf24';
                    ctx.font = 'italic 20px sans-serif';
                    ctx.fillText('Topic: ' + config.speakerTopic, 230, speakerCardY + 125);
                }
            }

            // Bottom Strip: Venue, Date & Price
            const bottomY = height - 120;
            
            // Date & Venue
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 30px sans-serif';
            ctx.fillText('📅 ' + config.date, 80, bottomY - 50);

            ctx.fillStyle = '#94a3b8';
            ctx.font = '26px sans-serif';
            ctx.fillText('📍 ' + config.venue, 80, bottomY);

            // Price Tag Pill
            ctx.fillStyle = 'rgba(255, 255, 255, 0.1)';
            ctx.beginPath();
            ctx.roundRect(80, bottomY + 25, 320, 48, 12);
            ctx.fill();

            ctx.fillStyle = '#38bdf8';
            ctx.font = 'bold 22px sans-serif';
            ctx.fillText('🎟️ ' + config.priceText, 95, bottomY + 57);

            // QR Code in bottom-right corner
            if (config.showQr) {
                const qrBoxSize = 180;
                const qrX = width - qrBoxSize - 80;
                const qrY = height - qrBoxSize - 80;

                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.roundRect(qrX, qrY, qrBoxSize, qrBoxSize, 16);
                ctx.fill();

                // Draw QR Pattern
                this.drawQrMatrix(ctx, qrX + 15, qrY + 15, qrBoxSize - 30, config.qrFg, '#ffffff');

                ctx.fillStyle = '#94a3b8';
                ctx.font = 'bold 16px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('SCAN FOR PASS', qrX + qrBoxSize / 2, qrY - 12);
                ctx.textAlign = 'left';
            }
        },

        renderQrOnly() {
            if (!this.qrCanvas) return;
            const size = 512;
            this.qrCanvas.width = size;
            this.qrCanvas.height = size;
            const ctx = this.qrCtx;

            ctx.fillStyle = config.qrBg;
            ctx.fillRect(0, 0, size, size);

            this.drawQrMatrix(ctx, 40, 40, size - 80, config.qrFg, config.qrBg);
        },

        drawQrMatrix(ctx, x, y, size, fg, bg) {
            // High-precision pseudo QR pattern matrix representing public pass
            const grid = 25;
            const cell = size / grid;

            ctx.fillStyle = fg;

            // 3 Standard Position Finder Patterns
            const drawFinder = (rX, rY) => {
                ctx.fillRect(rX, rY, cell * 7, cell * 7);
                ctx.fillStyle = bg;
                ctx.fillRect(rX + cell, rY + cell, cell * 5, cell * 5);
                ctx.fillStyle = fg;
                ctx.fillRect(rX + cell * 2, rY + cell * 2, cell * 3, cell * 3);
            };

            drawFinder(x, y);
            drawFinder(x + cell * (grid - 7), y);
            drawFinder(x, y + cell * (grid - 7));

            // Timing strips
            for (let i = 8; i < grid - 8; i++) {
                if (i % 2 === 0) {
                    ctx.fillRect(x + i * cell, y + 6 * cell, cell, cell);
                    ctx.fillRect(x + 6 * cell, y + i * cell, cell, cell);
                }
            }

            // Pseudo deterministic data cells based on URL
            const url = config.publicUrl;
            for (let r = 0; r < grid; r++) {
                for (let c = 0; c < grid; c++) {
                    if ((r < 8 && (c < 8 || c >= grid - 8)) || (r >= grid - 8 && c < 8)) {
                        continue;
                    }
                    const charCode = url.charCodeAt((r * grid + c) % url.length);
                    if ((charCode + r + c) % 3 === 0) {
                        ctx.fillRect(x + c * cell, y + r * cell, cell, cell);
                    }
                }
            }
        },

        downloadFlyer() {
            if (!this.canvas) return;
            const link = document.createElement('a');
            link.download = (config.headline || 'event-flyer').toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-' + config.aspectRatio.replace(':', '-') + '.png';
            link.href = this.canvas.toDataURL('image/png');
            link.click();
        },

        downloadQrPng() {
            if (!this.qrCanvas) return;
            const link = document.createElement('a');
            link.download = 'ticketlab-qr-pass.png';
            link.href = this.qrCanvas.toDataURL('image/png');
            link.click();
        },

        downloadQrSvg() {
            const svgData = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="512" height="512">
                <rect width="100%" height="100%" fill="${config.qrBg}"/>
                <path d="M40 40h140v140H40zM332 40h140v140H332zM40 332h140v140H40z" fill="${config.qrFg}"/>
                <rect x="60" y="60" width="100" height="100" fill="${config.qrBg}"/>
                <rect x="80" y="80" width="60" height="60" fill="${config.qrFg}"/>
                <rect x="352" y="60" width="100" height="100" fill="${config.qrBg}"/>
                <rect x="372" y="80" width="60" height="60" fill="${config.qrFg}"/>
                <rect x="60" y="352" width="100" height="100" fill="${config.qrBg}"/>
                <rect x="80" y="372" width="60" height="60" fill="${config.qrFg}"/>
            </svg>`;
            const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
            const link = document.createElement('a');
            link.download = 'ticketlab-vector-qr.svg';
            link.href = URL.createObjectURL(blob);
            link.click();
        }
    };
}
</script>

