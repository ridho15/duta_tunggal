import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Tema Filament (Spesifikasi UI/UX Bagian 2) — dipakai lewat ->viteTheme() di AdminPanelProvider
                'resources/css/filament/admin/theme.css',
                // Pemindai barcode cadangan untuk HP (Tahap 3)
                'resources/js/scan-fallback.js',
            ],
            refresh: true,
        }),
        vue(),
        react(),
    ],
    server: {
        cors: true,
    },
    build: {
        target: 'esnext'
    },
});