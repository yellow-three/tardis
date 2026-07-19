import { writeFileSync, unlinkSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * Vite plugin that creates/removes a "hot" file in the package's resources/ directory.
 * 
 * This file signals to the Laravel package that Vite dev server is running,
 * enabling CSS/JS to be loaded from the dev server instead of built assets.
 * 
 * The hot file contains the Vite dev server URL (e.g., "http://localhost:5173").
 * It's created when the server starts and removed when it closes.
 * 
 * Usage: import hotFile from './vite-plugins/hot-file.js';
 *        plugins: [hotFile()]
 */
export default function hotFile() {
    return {
        name: 'vite-plugin-hot-file',
        apply: 'serve',

        configureServer(server) {
            const hotPath = resolve(process.cwd(), 'resources/hot');

            // Wait for the server to actually bind to a port, then write the hot file.
            // server.config.server.port is the *configured* port — Vite may fall back to
            // a different one if the configured port is already in use.
            server.httpServer?.on('listening', () => {
                const addr = server.httpServer.address();
                if (addr && typeof addr === 'object') {
                    // addr.address is '::' or '0.0.0.0' for wildcard binds — use localhost
                    const host = 'localhost';
                    const url = `http://${host}:${addr.port}`;
                    writeFileSync(hotPath, url, 'utf-8');
                    console.log(`[hot-file] Wrote ${hotPath} → ${url}`);
                }
            });

            // Remove hot file when server closes
            server.httpServer?.on('close', () => {
                try {
                    if (existsSync(hotPath)) {
                        unlinkSync(hotPath);
                        console.log(`[hot-file] Removed ${hotPath}`);
                    }
                } catch {
                    // Ignore cleanup errors
                }
            });
        },
    };
}
