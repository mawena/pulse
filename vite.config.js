import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        vuetify({ autoImport: true }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
            // Force une résolution unique d'apexcharts (build navigateur) :
            // sans cet alias, les conditions d'export node/browser font
            // embarquer la librairie deux fois (esm + ssr.esm, ~500 kB dupliqués).
            apexcharts: 'apexcharts/dist/apexcharts.esm.js',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
