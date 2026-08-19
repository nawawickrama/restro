import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // 700 is loaded because the POS leans on bold figures for
                // totals and table states.
                bunny('Instrument Sans', {
                    weights: [400, 500, 600, 700],
                }),

                // Heavy condensed caps for the public site's headlines, chosen
                // to echo the restaurant's own shopfront sign. Self-hosted like
                // everything else, so the page needs no third-party request.
                bunny('Anton', {
                    weights: [400],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
