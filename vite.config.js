import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',
            ],
            refresh: true,
            // Instrument Sans (SIL Open Font License), Latin subset, woff2 only, kept in the repo. bunny() emitted
            // a woff2 and a woff @font-face per weight with the same unicode-range; the later (woff) rule won, so
            // browsers downloaded both sets and the woff2 preloads were wasted (laravel-vite-plugin 3.2.x).
            fonts: [
                local('Instrument Sans', {
                    display: 'swap',
                    // Needs the optional "fontaine" package; measured layout shift from the font swap is 0.
                    optimizedFallbacks: false,
                    variants: [400, 500, 600].map((weight) => ({
                        src: `resources/fonts/instrument-sans/instrument-sans-latin-${weight}-normal.woff2`,
                        weight,
                    })),
                }),
            ],
        }),
        tailwindcss(),
    ]),
    server: {
        cors: true,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
