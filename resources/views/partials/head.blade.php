<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

<script>
    (() => {
        const stored = localStorage.getItem('theme');
        if (stored === 'light' || (!stored && window.matchMedia('(prefers-color-scheme: light)').matches)) {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
    })();
</script>

<style>
::view-transition-image-pair(root) {
    isolation: auto;
}
::view-transition-old(root),
::view-transition-new(root) {
    animation: none !important;
    mix-blend-mode: normal !important;
    display: block;
}
html.dark::view-transition-new(root) {
    z-index: 9999;
}
html.dark::view-transition-old(root) {
    z-index: 1;
}
html:not(.dark)::view-transition-old(root) {
    z-index: 9999;
}
html:not(.dark)::view-transition-new(root) {
    z-index: 1;
}
</style>

@vite(['resources/css/app.css', 'resources/js/app.js'])
