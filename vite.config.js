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
});
