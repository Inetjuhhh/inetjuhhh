import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css',
                // Loaded on demand by the location picker in the blog form
                'resources/js/location-picker.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        rollupOptions: {
            // The blog form imports location-picker.js by URL and calls its mountLocationPicker(); without
            // this the build drops the exports of entry files (the dev server keeps them, so it only broke built)
            preserveEntrySignatures: 'exports-only',
        },
    },
});
