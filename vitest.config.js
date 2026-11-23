import { defineConfig } from 'vite-plus';

/*
 * JavaScript unit tests only (npm run test:js).
 *
 * Deliberately separate from vite.config.js: that file loads laravel-vite-plugin, which refuses to start a dev
 * server whenever CI is set, and the tests need none of it. The tests are pure functions (the checkout fee
 * formula), so they run in Node without a DOM.
 */
export default defineConfig({
    test: {
        environment: 'node',
        include: ['tests/js/**/*.test.js'],
    },
});
