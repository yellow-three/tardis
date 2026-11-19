import { writeFileSync, unlinkSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * Vite plugin that creates/removes a "hot" file in the package's resources/ directory.
 * 
 * This file signals to the Laravel package that Vite dev server is running,
 * enabling CSS/JS to be loaded from the dev server instead of built assets.
 * 
 * The hot file contains the Vite dev server URL (e.g., "http://localhost:5173").
 * It follows the server's own settings: `server.origin` when set, otherwise
 * https when `server.https` is on and the configured host (a wildcard bind such
 * as 0.0.0.0 becomes localhost). TARDIS_VITE_URL overrides all of it, for a
 * dev server reached through a proxy or another machine.
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
                    const url = devServerUrl(server.config, addr.port);
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

/**
 * @param {import('vite').ResolvedConfig} config
 * @param {number} port the port the server actually bound
 */
export function devServerUrl(config, port) {
    const override = process.env.TARDIS_VITE_URL;

    if (override) {
        return override.replace(/\/+$/, '');
    }

    if (config.server.origin) {
        return config.server.origin.replace(/\/+$/, '');
    }

    const configured = typeof config.server.host === 'string' ? config.server.host : '';
    const host = configured === '' || configured === '0.0.0.0' || configured === '::' ? 'localhost' : configured;
    const protocol = config.server.https ? 'https' : 'http';

    return `${protocol}://${host.includes(':') ? `[${host}]` : host}:${port}`;
}
