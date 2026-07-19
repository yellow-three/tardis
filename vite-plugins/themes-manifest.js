import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';

/**
 * Vite plugin that parses DaisyUI theme definitions from the source CSS file
 * and generates a themes-manifest.json in the public output directory.
 *
 * Runs in both build and dev (serve) modes so the manifest is always available.
 *
 * @param {object} [options]
 * @param {string} [options.outputPath] - Where to write the manifest (relative to project root)
 * @param {string} [options.sourceCss] - Path to the source CSS file containing @plugin blocks
 */
export default function themesManifest(options = {}) {
    const outputPath = options.outputPath || 'public/tardis-assets/themes-manifest.json';
    const sourceCss = options.sourceCss || 'resources/css/app.css';

    function parseManifest(projectRoot) {
        try {
            const cssPath = resolve(projectRoot, sourceCss);
            const css = readFileSync(cssPath, 'utf-8');
            const themes = parseDaisyUIThemes(css);

            if (themes.length === 0) {
                console.warn('[themes-manifest] No DaisyUI theme definitions found in', sourceCss);
                return null;
            }

            return { themes };
        } catch (error) {
            console.error('[themes-manifest] Failed to parse themes:', error.message);
            return null;
        }
    }

    function writeManifestToDisk(projectRoot, manifest) {
        const manifestPath = resolve(projectRoot, outputPath);
        mkdirSync(dirname(manifestPath), { recursive: true });
        writeFileSync(manifestPath, JSON.stringify(manifest, null, 2) + '\n', 'utf-8');
        console.log(`[themes-manifest] Wrote ${manifest.themes.length} theme(s) to ${outputPath}`);
    }

    return {
        name: 'vite-plugin-themes-manifest',

        configureServer(server) {
            const manifest = parseManifest(process.cwd());
            if (!manifest) return;

            writeManifestToDisk(process.cwd(), manifest);

            server.middlewares.use((req, res, next) => {
                if (req.url === '/tardis-assets/themes-manifest.json') {
                    res.setHeader('Content-Type', 'application/json');
                    res.setHeader('Cache-Control', 'no-cache');
                    res.end(JSON.stringify(manifest, null, 2));
                    return;
                }
                next();
            });
        },

        closeBundle() {
            const manifest = parseManifest(process.cwd());
            if (manifest) {
                writeManifestToDisk(process.cwd(), manifest);
            }
        },
    };
}

/**
 * Parse all @plugin "daisyui/theme" blocks from CSS source text.
 *
 * @param {string} css - The full CSS source text
 * @returns {Array<object>} Array of theme objects
 */
function parseDaisyUIThemes(css) {
    const themeBlocks = [];
    const blockRegex = /@plugin\s+"daisyui\/theme"\s*\{([^}]+(?:\{[^}]*\}[^}]*)*)\}/g;

    let match;
    while ((match = blockRegex.exec(css)) !== null) {
        const block = match[1];
        const theme = parseThemeBlock(block);
        if (theme.name) {
            themeBlocks.push(theme);
        }
    }

    return themeBlocks;
}

/**
 * Parse a single theme block's CSS body into a structured theme object.
 *
 * @param {string} block - The inner CSS of a @plugin "daisyui/theme" block
 * @returns {object} Theme object with name, colorScheme, colors, previewColors
 */
function parseThemeBlock(block) {
    const theme = {
        name: '',
        colorScheme: 'light',
        colors: {},
        previewColors: [],
    };

    const nameMatch = block.match(/name:\s*"([^"]+)"/);
    if (nameMatch) {
        theme.name = nameMatch[1];
    }

    const colorSchemeMatch = block.match(/color-scheme:\s*(\w+)/);
    if (colorSchemeMatch) {
        theme.colorScheme = colorSchemeMatch[1];
    }

    const defaultMatch = block.match(/default:\s*(true|false)/);
    if (defaultMatch) {
        theme.default = defaultMatch[1] === 'true';
    }

    const prefersDarkMatch = block.match(/prefersdark:\s*(true|false)/);
    if (prefersDarkMatch) {
        theme.prefersDark = prefersDarkMatch[1] === 'true';
    }

    const colorKeys = [
        'primary',
        'secondary',
        'accent',
        'neutral',
        'base-100',
        'base-200',
        'base-300',
        'base-content',
        'primary-content',
        'secondary-content',
        'accent-content',
        'neutral-content',
        'info',
        'success',
        'warning',
        'error',
        'info-content',
        'success-content',
        'warning-content',
        'error-content',
    ];

    for (const key of colorKeys) {
        const regex = new RegExp(`--color-${escapeRegex(key)}:\\s*([^;]+);`);
        const colorMatch = block.match(regex);
        if (colorMatch) {
            theme.colors[key] = colorMatch[1].trim();
        }
    }

    const previewKeys = ['primary', 'secondary', 'accent', 'base-100'];
    theme.previewColors = previewKeys
        .filter((key) => theme.colors[key])
        .map((key) => theme.colors[key]);

    return theme;
}

/**
 * Escape special regex characters in a string.
 *
 * @param {string} str
 * @returns {string}
 */
function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
