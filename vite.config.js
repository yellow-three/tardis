import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import hotFile from './vite-plugins/hot-file.js';
import themesManifest from './vite-plugins/themes-manifest.js';

export default defineConfig({
    plugins: [
        hotFile(),
        tailwindcss(),
        themesManifest(),
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
