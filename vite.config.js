import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import hotFile from './vite-plugins/hot-file.js';

/**
 * Stylesheet build. The core script is built separately (vite.js.config.js) as
 * a classic IIFE: it must run before Livewire starts Alpine, which a module
 * script cannot guarantee, and a multi-entry build cannot emit an IIFE.
 */
export default defineConfig({
    plugins: [
        hotFile(),
        tailwindcss(),
    ],
    build: {
        outDir: 'dist',
        rollupOptions: {
            input: {
                app: 'resources/css/app.css',
            },
            output: {
                assetFileNames: 'assets/[name][extname]',
            },
        },
    },
});
