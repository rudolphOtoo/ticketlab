<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
<meta name="description" content="{{ $metaDescription ?? 'TicketLab — Universal Event Ticketing & Ground Operations Platform. Fast digital passes, door scanning, and instant mobile money checkout.' }}" />

<!-- OpenGraph / Facebook -->
<meta property="og:type" content="website" />
<meta property="og:site_name" content="TicketLab" />
<meta property="og:title" content="{{ filled($title ?? null) ? $title.' - '.config('app.name', 'TicketLab') : config('app.name', 'TicketLab') }}" />
<meta property="og:description" content="{{ $metaDescription ?? 'Universal Event Ticketing & Ground Operations Platform. Fast digital passes, door scanning, and instant mobile money checkout.' }}" />
<meta property="og:url" content="{{ url()->current() }}" />

<!-- Twitter Card -->
<meta name="twitter:card" content="summary" />
<meta name="twitter:title" content="{{ filled($title ?? null) ? $title.' - '.config('app.name', 'TicketLab') : config('app.name', 'TicketLab') }}" />
<meta name="twitter:description" content="{{ $metaDescription ?? 'Universal Event Ticketing & Ground Operations Platform.' }}" />

<!-- Mobile Browser Theme Color -->
<meta name="theme-color" content="#090a0f" media="(prefers-color-scheme: dark)" />
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />

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

    function toggleStudioTheme(event, targetTheme = null, triggerEl = null) {
        const btn = triggerEl || event?.target?.closest('button') || event?.currentTarget;
        const rect = btn?.getBoundingClientRect();
        const x = rect ? Math.round(rect.left + rect.width / 2) : Math.round(event?.clientX ?? window.innerWidth / 2);
        const y = rect ? Math.round(rect.top + rect.height / 2) : Math.round(event?.clientY ?? window.innerHeight / 2);

        const isDark = document.documentElement.classList.contains('dark');
        const nextTheme = targetTheme ?? (isDark ? 'light' : 'dark');

        if (targetTheme && ((targetTheme === 'dark' && isDark) || (targetTheme === 'light' && !isDark))) {
            return;
        }

        const isGoingDark = nextTheme === 'dark';

        const commitThemeChange = () => {
            if (isGoingDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            localStorage.setItem('theme', nextTheme);
            document.dispatchEvent(new CustomEvent('theme-changed', {
                detail: { theme: nextTheme }
            }));
        };

        if (!document.startViewTransition || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            commitThemeChange();
            return;
        }

        const endRadius = Math.ceil(
            Math.hypot(
                Math.max(x, window.innerWidth - x),
                Math.max(y, window.innerHeight - y)
            ) * 1.05
        );

        const transition = document.startViewTransition(() => {
            commitThemeChange();
        });

        transition.ready.then(() => {
            if (isGoingDark) {
                // Forward: Dark expands outwards from trigger across the viewport
                document.documentElement.animate(
                    [
                        { clipPath: `circle(0px at ${x}px ${y}px)` },
                        { clipPath: `circle(${endRadius}px at ${x}px ${y}px)` }
                    ],
                    {
                        duration: 360,
                        easing: 'cubic-bezier(0.23, 1, 0.32, 1)',
                        pseudoElement: '::view-transition-new(root)',
                        fill: 'both'
                    }
                );
            } else {
                // Reverse: Dark contracts inward into the trigger, revealing light mode beneath
                document.documentElement.animate(
                    [
                        { clipPath: `circle(${endRadius}px at ${x}px ${y}px)` },
                        { clipPath: `circle(0px at ${x}px ${y}px)` }
                    ],
                    {
                        duration: 360,
                        easing: 'cubic-bezier(0.23, 1, 0.32, 1)',
                        pseudoElement: '::view-transition-old(root)',
                        fill: 'both'
                    }
                );
            }
        });
    }

    window.toggleStudioTheme = toggleStudioTheme;
    window.toggleTheme = toggleStudioTheme;
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

/* Stacking Order & Color-Scheme Guards */
/* Forward (Going Dark): New dark canvas expands on top */
html.dark::view-transition-old(root) {
    z-index: 1;
    color-scheme: light;
}
html.dark::view-transition-new(root) {
    z-index: 9999;
    color-scheme: dark;
}

/* Reverse (Going Light): Old dark canvas contracts on top, revealing light underneath */
html:not(.dark)::view-transition-old(root) {
    z-index: 9999;
    color-scheme: dark;
}
html:not(.dark)::view-transition-new(root) {
    z-index: 1;
    color-scheme: light;
}
</style>

@vite(['resources/css/app.css', 'resources/js/app.js'])
