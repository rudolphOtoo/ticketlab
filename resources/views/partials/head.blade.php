    <script>
        if (!window.Alpine) {
            const script = document.createElement('script');
            script.defer = true;
            script.src = 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js';
            script.integrity = 'sha256-NYhTz3mvNscbZ+7hO7ZuHjvQ+8g2u7D2/0Zg5jB8X0g=';
            script.crossOrigin = 'anonymous';
            document.head.appendChild(script);
        }
    </script>
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
                document.documentElement.animate(
                    [
                        { clipPath: `circle(0px at ${x}px ${y}px)` },
                        { clipPath: `circle(${endRadius}px at ${x}px ${y}px)` }
                    ],
                    {
                        duration: 360,
                        easing: 'cubic-bezier(0.23, 1, 0.32, 1)',
                        pseudoElement: '::view-transition-new(root)'
                    }
                );
            } else {
                document.documentElement.animate(
                    [
                        { clipPath: `circle(${endRadius}px at ${x}px ${y}px)` },
                        { clipPath: `circle(0px at ${x}px ${y}px)` }
                    ],
                    {
                        duration: 360,
                        easing: 'cubic-bezier(0.23, 1, 0.32, 1)',
                        pseudoElement: '::view-transition-old(root)'
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

/* ==========================================================================
   Theme-Switch Flash Fix
   Forces color-scheme on the view-transition pseudo-elements so browser-native
   controls (scrollbars, selects, inputs) don't flash OS-default styling
   during the circular clip animation between light ↔ dark mode.
   ========================================================================== */
html.dark::view-transition-new(root),
html.dark::view-transition-old(root) {
    color-scheme: dark;
}
html:not(.dark)::view-transition-new(root),
html:not(.dark)::view-transition-old(root) {
    color-scheme: light;
}
</style>

@vite(['resources/css/app.css', 'resources/js/app.js'])
