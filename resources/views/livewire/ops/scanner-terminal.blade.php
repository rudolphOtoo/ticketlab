<div class="flex flex-col items-center max-w-lg mx-auto gap-6"
     x-data="{
        audioCtx: null,
        html5QrCode: null,
        isScanning: false,
        cameraError: null,
        cameraStatusText: 'Initializing Camera...',
        cooldown: false,
        init() {
            this.initAudio();
            this.$nextTick(() => {
                this.startCamera();
            });
        },
        destroy() {
            this.stopCamera();
        },
        initAudio() {
            if (!this.audioCtx) {
                this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
        },
        async startCamera() {
            if (typeof window.Html5Qrcode === 'undefined') {
                this.cameraStatusText = 'Camera engine loading...';
                setTimeout(() => this.startCamera(), 300);
                return;
            }
            try {
                this.html5QrCode = new window.Html5Qrcode('qr-reader');
                const config = {
                    fps: 10,
                    qrbox: { width: 220, height: 220 },
                    aspectRatio: 1.0,
                };
                await this.html5QrCode.start(
                    { facingMode: 'environment' },
                    config,
                    (decodedText) => this.onScan(decodedText),
                    () => {}
                );
                this.isScanning = true;
                this.cameraError = null;
            } catch (err) {
                this.isScanning = false;
                this.cameraError = err.message || 'Camera offline or permission denied';
                this.cameraStatusText = 'Camera Offline (Simulators Active)';
            }
        },
        async stopCamera() {
            if (this.html5QrCode && this.isScanning) {
                try {
                    await this.html5QrCode.stop();
                    this.isScanning = false;
                } catch (e) {}
            }
        },
        onScan(text) {
            if (this.cooldown) return;
            this.cooldown = true;
            $wire.validatePass(text);
            setTimeout(() => {
                this.cooldown = false;
            }, 1200);
        },
        playChime(freq1, freq2) {
            this.initAudio();
            const now = this.audioCtx.currentTime;
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();
            osc.connect(gain);
            gain.connect(this.audioCtx.destination);
            osc.frequency.setValueAtTime(freq1, now);
            osc.frequency.exponentialRampToValueAtTime(freq2, now + 0.12);
            gain.gain.setValueAtTime(0.15, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
            osc.start(now);
            osc.stop(now + 0.35);
            if (navigator.vibrate) navigator.vibrate(120);
        },
        playBuzzer() {
            this.initAudio();
            const now = this.audioCtx.currentTime;
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();
            osc.type = 'sawtooth';
            osc.connect(gain);
            gain.connect(this.audioCtx.destination);
            osc.frequency.setValueAtTime(220, now);
            gain.gain.setValueAtTime(0.2, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.4);
            osc.start(now);
            osc.stop(now + 0.4);
            if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
        }
     }"
     x-on:play-success-sound.window="playChime(880, 1046)"
     x-on:play-warning-sound.window="playChime(554, 440)"
     x-on:play-error-sound.window="playBuzzer()">

    <!-- Top Telemetry Bar -->
    <div class="flex w-full items-center justify-between rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-4 py-3 shadow-xs">
        <div class="flex items-center gap-2.5">
            <span class="size-2 rounded-full bg-[var(--status-success)] animate-pulse"></span>
            <div class="flex flex-col">
                <span class="text-xs font-semibold text-[var(--text-primary)]">Gate 04 · Door Scanner</span>
                <span class="text-[10px] text-[var(--text-secondary)]">{{ $activeSubdivision?->name ?? 'Main Entrance' }}</span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--bg-subtle)] px-2.5 py-1 text-xs font-mono font-medium text-[var(--text-primary)]">
                <span>{{ $totalCheckedIn }}</span>
                <span class="text-[var(--text-tertiary)]">/ {{ $totalExpected }}</span>
                <span class="text-[10px] text-[var(--status-success)] font-semibold">({{ $turnoutPercent }}%)</span>
            </span>
        </div>
    </div>

    <!-- Camera Viewfinder Box -->
    <div class="relative w-full aspect-square max-h-[380px] rounded-2xl border-2 border-[var(--border-hairline)] bg-zinc-950 overflow-hidden shadow-md flex items-center justify-center">
        <!-- Live Video Element / Viewfinder Canvas -->
        <div id="qr-reader" class="w-full h-full object-cover">
            <div class="absolute inset-0 flex flex-col items-center justify-center text-zinc-400 gap-3 px-6 text-center" x-show="!isScanning">
                <svg class="size-12 text-zinc-600" :class="{ 'animate-pulse': !cameraError }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.25" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" />
                </svg>
                <span class="text-xs font-mono tracking-wider text-zinc-400 uppercase" x-text="cameraStatusText">Optical Viewfinder Ready</span>
                <template x-if="cameraError">
                    <button type="button" @click="startCamera()" class="mt-2 text-[11px] rounded bg-zinc-800 text-zinc-300 px-3 py-1 hover:bg-zinc-700 transition">
                        Retry Camera Access
                    </button>
                </template>
            </div>
        </div>

        <!-- 4-Corner Targeting Brackets -->
        <div class="pointer-events-none absolute inset-10 flex flex-col justify-between">
            <div class="flex justify-between">
                <div class="size-6 border-t-2 border-l-2 border-white/80 rounded-tl-sm"></div>
                <div class="size-6 border-t-2 border-r-2 border-white/80 rounded-tr-sm"></div>
            </div>
            <!-- Center Animated Scan Laser -->
            <div class="h-0.5 w-full bg-gradient-to-r from-transparent via-[var(--status-success)] to-transparent opacity-75 shadow-xs animate-bounce"></div>
            <div class="flex justify-between">
                <div class="size-6 border-b-2 border-l-2 border-white/80 rounded-bl-sm"></div>
                <div class="size-6 border-b-2 border-r-2 border-white/80 rounded-br-sm"></div>
            </div>
        </div>

        <!-- Top Guide Pill -->
        <div class="absolute top-4 inset-x-0 flex justify-center pointer-events-none">
            <span class="rounded-full bg-black/60 backdrop-blur-md px-3 py-1 text-[11px] font-medium text-white/90 border border-white/10">
                Point at attendee ticket QR code
            </span>
        </div>
    </div>

    <!-- Dynamic Scan Result Feedback Card -->
    @if ($scanState !== 'idle' && $scanResult)
        <div class="w-full rounded-2xl border p-5 shadow-sm transition-all duration-200
            {{ $scanState === 'success' ? 'border-[var(--status-success)] bg-[var(--status-success-bg)] text-[var(--status-success)]' : '' }}
            {{ $scanState === 'duplicate' ? 'border-[var(--status-warning)] bg-[var(--status-warning-bg)] text-[var(--status-warning)]' : '' }}
            {{ $scanState === 'invalid' ? 'border-[var(--status-danger)] bg-[var(--status-danger-bg)] text-[var(--status-danger)]' : '' }}
        ">
            <div class="flex items-center justify-between pb-3 border-b border-current/15">
                <span class="inline-flex items-center gap-1.5 text-xs font-bold tracking-wider uppercase">
                    @if ($scanState === 'success')
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                        [ VERIFIED & ADMITTED ]
                    @elseif ($scanState === 'duplicate')
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        [ DUPLICATE SCAN ]
                    @else
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        [ INVALID CREDENTIAL ]
                    @endif
                </span>

                <button type="button" wire:click="resetScanner" class="cursor-pointer text-xs font-semibold opacity-70 hover:opacity-100">
                    &times; Dismiss
                </button>
            </div>

            <div class="pt-3 flex flex-col gap-2">
                @if (isset($scanResult['name']))
                    <h3 class="text-xl font-bold tracking-tight text-[var(--text-primary)]">{{ $scanResult['name'] }}</h3>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="rounded-md bg-white/70 dark:bg-black/40 px-2 py-0.5 font-medium border border-current/20">
                            {{ $scanResult['tier'] }}
                        </span>
                        <span class="text-[var(--text-secondary)]">Subdivision: {{ $scanResult['subdivision'] ?? $scanResult['zone'] }}</span>
                        <span class="ml-auto font-mono text-[11px] text-[var(--text-tertiary)]">{{ $scanResult['scanned_at'] }}</span>
                    </div>
                @endif
                <p class="text-xs opacity-90 pt-1">{{ $scanResult['message'] }}</p>
            </div>

            <div class="mt-4 pt-3 border-t border-current/15">
                <button type="button" wire:click="resetScanner" class="w-full cursor-pointer rounded-lg bg-[var(--text-primary)] py-2 text-xs font-semibold text-[var(--bg-canvas)] shadow-xs hover:opacity-90 transition">
                    Scan Next Guest &rarr;
                </button>
            </div>
        </div>
    @endif

    <!-- Manual Search Fallback Input -->
    <div class="w-full flex items-center gap-2">
        <input type="text"
               wire:model="manualSearch"
               placeholder="Or enter attendee phone / QR hash..."
               class="flex-1 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3.5 py-2.5 text-xs text-[var(--text-primary)] placeholder-[var(--text-tertiary)] focus:border-[var(--border-focused)] focus:ring-1 focus:ring-[var(--border-focused)] outline-hidden transition"
        />
        <button type="button"
                wire:click="validatePass(manualSearch)"
                class="cursor-pointer rounded-lg bg-[var(--accent-brand)] px-4 py-2.5 text-xs font-medium text-[var(--accent-brand-fg)] hover:opacity-90 transition">
            Verify
        </button>
    </div>
</div>
