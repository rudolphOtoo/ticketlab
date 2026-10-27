<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-[var(--text-primary)]">Marketing Studio</h1>
            <p class="mt-1 text-sm text-[var(--text-secondary)]">Create branded flyers and export vector QR codes for your events.</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">
        <aside class="space-y-4 rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-sm">
            <div>
                <label class="block text-sm font-medium text-[var(--text-secondary)]">Event</label>
                <select wire:model.live="eventId" class="mt-2 w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-2 text-sm">
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}">{{ $event->title }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-[var(--text-secondary)]">Aspect Ratio</label>
                <div class="mt-2 grid grid-cols-3 gap-2">
                    <button type="button" wire:click="$set('aspectRatio', '1:1')" class="rounded-lg px-2 py-1 text-xs font-medium {{ $aspectRatio === '1:1' ? 'bg-[var(--color-primary)] text-[var(--color-primary-fg)]' : 'border border-[var(--border-hairline)]' }}">1:1</button>
                    <button type="button" wire:click="$set('aspectRatio', '9:16')" class="rounded-lg px-2 py-1 text-xs font-medium {{ $aspectRatio === '9:16' ? 'bg-[var(--color-primary)] text-[var(--color-primary-fg)]' : 'border border-[var(--border-hairline)]' }}">9:16</button>
                    <button type="button" wire:click="$set('aspectRatio', '16:9')" class="rounded-lg px-2 py-1 text-xs font-medium {{ $aspectRatio === '16:9' ? 'bg-[var(--color-primary)] text-[var(--color-primary-fg)]' : 'border border-[var(--border-hairline)]' }}">16:9</button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-[var(--text-secondary)]">Branding Colors</label>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <input type="color" wire:model.live="primaryColor" class="h-9 w-full rounded-lg border border-[var(--border-hairline)]">
                    <input type="color" wire:model.live="secondaryColor" class="h-9 w-full rounded-lg border border-[var(--border-hairline)]">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-[var(--text-secondary)]">QR Colors</label>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <input type="color" wire:model.live="qrForeground" class="h-9 w-full rounded-lg border border-[var(--border-hairline)]">
                    <input type="color" wire:model.live="qrBackground" class="h-9 w-full rounded-lg border border-[var(--border-hairline)]">
                </div>
            </div>

            @if ($selectedEvent)
                <div class="rounded-xl border border-[var(--border-hairline)] p-3 text-sm">
                    <p class="font-medium">{{ $selectedEvent->title }}</p>
                    <p class="text-xs text-[var(--text-secondary)]">{{ $selectedEvent->venue }}</p>
                    <p class="text-xs text-[var(--text-secondary)]">{{ $selectedEvent->start_at->format('M d, Y H:i') }}</p>
                    <div class="mt-2 space-y-1">
                        <a href="{{ route('public.event.show', [$organization->slug ?? 'org', $selectedEvent->slug]) }}" target="_blank" class="text-xs text-[var(--color-primary)] underline">Event link</a>
                    </div>
                </div>
            @endif
        </aside>

        <section class="space-y-4 rounded-3xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Live Preview</h2>
                <div class="flex gap-2">
                    <button type="button" id="export-png" class="rounded-lg border border-[var(--border-hairline)] px-3 py-1.5 text-xs font-medium">Export PNG</button>
                    <button type="button" id="export-svg-qr" class="rounded-lg border border-[var(--border-hairline)] px-3 py-1.5 text-xs font-medium">Download QR SVG</button>
                    <button type="button" id="export-png-qr" class="rounded-lg border border-[var(--border-hairline)] px-3 py-1.5 text-xs font-medium">Download QR PNG</button>
                </div>
            </div>

            <div class="flex items-center justify-center rounded-2xl bg-[var(--bg-subtle)] p-6">
                <canvas id="flyer-canvas" class="max-h-[70vh] w-full max-w-[600px] rounded-xl border border-[var(--border-hairline)] bg-white shadow-lg"></canvas>
            </div>

            <div id="qr-studio" class="hidden">
                <svg id="qr-svg" xmlns="http://www.w3.org/2000/svg"></svg>
            </div>
        </section>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    function drawFlyer() {
        const canvas = document.getElementById('flyer-canvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        let width = 1080, height = 1080;
        if ('{{ $aspectRatio }}' === '9:16') { width = 1080; height = 1920; }
        if ('{{ $aspectRatio }}' === '16:9') { width = 1920; height = 1080; }
        canvas.width = width;
        canvas.height = height;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0,0,width,height);
        ctx.fillStyle = '{{ $primaryColor }}';
        ctx.fillRect(0,0,width,height*0.35);
        ctx.fillStyle = '#ffffff';
        ctx.font = Math.floor(height*0.06)+'px sans-serif';
        ctx.fillText('{{ addslashes($selectedEvent?->title ?? "Event Title") }}', 60, height*0.18);
        ctx.font = Math.floor(height*0.03)+'px sans-serif';
        ctx.fillText('{{ addslashes($selectedEvent?->venue ?? "") }}', 60, height*0.26);
        ctx.fillText('{{ $selectedEvent?->start_at?->format("M d, Y H:i") ?? "" }}', 60, height*0.31);

        // QR placeholder
        ctx.fillStyle = '#f3f4f6';
        ctx.fillRect(width*0.65, height*0.55, width*0.25, width*0.25);
        ctx.fillStyle = '#111827';
        ctx.font = Math.floor(height*0.02)+'px sans-serif';
        ctx.fillText('SCAN TO REGISTER', width*0.65, height*0.55 - 10);
    }

    function generateQR() {
        const qrDiv = document.createElement('div');
        const url = '{{ $selectedEvent ? route('public.event.show', [$organization->slug ?? 'org', $selectedEvent->slug]) : url('/') }}';
        new QRCode(qrDiv, { text: url, width: 512, height: 512, colorDark: '{{ $qrForeground }}', colorLight: '{{ $qrBackground }}', correctLevel: QRCode.CorrectLevel.H });
        setTimeout(() => {
            const img = qrDiv.querySelector('img');
            const canvas = document.getElementById('flyer-canvas');
            if (img && canvas) {
                const ctx = canvas.getContext('2d');
                const size = canvas.width * 0.25;
                ctx.drawImage(img, canvas.width - size - 60, canvas.height - size - 60, size, size);
            }
            // SVG
            const svg = document.getElementById('qr-svg');
            svg.innerHTML = '';
            svg.setAttribute('width', '512');
            svg.setAttribute('height', '512');
            const table = qrDiv.querySelector('table');
            if (table) {
                // simple svg export
                const svgNS = 'http://www.w3.org/2000/svg';
                svg.setAttribute('xmlns', svgNS);
                const qrUrl = url;
                // use qrcode to svg via data
                const qr = document.createElement('div');
                new QRCode(qr, { text: qrUrl, width: 512, height: 512, colorDark: '{{ $qrForeground }}', colorLight: '{{ $qrBackground }}', useSVG: true });
                setTimeout(() => {
                    const svgEl = qr.querySelector('svg');
                    if (svgEl) {
                        svg.outerHTML = svgEl.outerHTML;
                    }
                }, 50);
            }
        }, 100);
    }

    document.addEventListener('livewire:navigated', () => { drawFlyer(); generateQR(); });
    document.addEventListener('DOMContentLoaded', () => { drawFlyer(); generateQR(); });

    document.getElementById('export-png')?.addEventListener('click', () => {
        const canvas = document.getElementById('flyer-canvas');
        const link = document.createElement('a');
        link.download = 'event-flyer.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    });

    document.getElementById('export-svg-qr')?.addEventListener('click', () => {
        const svg = document.querySelector('#qr-svg svg') || document.getElementById('qr-svg');
        const serializer = new XMLSerializer();
        const source = '<?xml version="1.0" standalone="no"?>\r\n' + serializer.serializeToString(svg);
        const blob = new Blob([source], { type: 'image/svg+xml;charset=utf-8' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'event-qr.svg';
        link.click();
    });

    document.getElementById('export-png-qr')?.addEventListener('click', () => {
        const qrDiv = document.createElement('div');
        const url = '{{ $selectedEvent ? route('public.event.show', [$organization->slug ?? 'org', $selectedEvent->slug]) : url('/') }}';
        new QRCode(qrDiv, { text: url, width: 1024, height: 1024, colorDark: '{{ $qrForeground }}', colorLight: '{{ $qrBackground }}', correctLevel: QRCode.CorrectLevel.H });
        setTimeout(() => {
            const img = qrDiv.querySelector('img');
            if (img) {
                const link = document.createElement('a');
                link.download = 'event-qr.png';
                link.href = img.src;
                link.click();
            }
        }, 100);
    });
</script>
