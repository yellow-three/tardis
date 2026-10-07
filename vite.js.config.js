import { defineConfig } from 'vite';

/**
 * Core script build: resources/js/app.js -> resources/compiled/assets/app.js as a classic
 * script. emptyOutDir is off so it leaves the stylesheet build alone.
 */
export default defineConfig({
    build: {
        outDir: 'resources/compiled',
        emptyOutDir: false,
        lib: {
            entry: 'resources/js/app.js',
            name: 'TardisCore',
            formats: ['iife'],
            fileName: () => 'assets/app.js',
        },
    },
});
