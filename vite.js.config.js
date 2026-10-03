import { defineConfig } from 'vite';

/**
 * Core script build: resources/js/app.js -> dist/assets/app.js as a classic
 * script. emptyOutDir is off so it leaves the stylesheet build alone.
 */
export default defineConfig({
    build: {
        outDir: 'dist',
        emptyOutDir: false,
        lib: {
            entry: 'resources/js/app.js',
            name: 'TardisCore',
            formats: ['iife'],
            fileName: () => 'assets/app.js',
        },
    },
});
