<?php

declare(strict_types=1);

namespace Tardis\Assets;

use Tardis\Support\FileSignature;

/**
 * Content hashes of file assets, so a page does not read and hash every
 * stylesheet and script it links on every request.
 *
 * A hash is keyed by the file's signature (inode, mtime, size): a changed file
 * misses and is hashed again, so a stale entry can never be served. Entries
 * live in memory for the process and in a PHP file under the framework cache,
 * which OPcache keeps in shared memory, so a request costs one stat per file.
 * The URL stays a content hash, identical on every server of a deployment.
 */
final class AssetHashes
{
    /** @var array<string, string>|null signature key => hash */
    private static ?array $hashes = null;

    public static function of(string $file): ?string
    {
        if (! is_file($file) || ! is_readable($file)) {
            return null;
        }

        // A file written within the last moments could change again inside the
        // same second without its signature moving, so it is hashed every time
        // until it has settled.
        $modified = @filemtime($file);

        if ($modified === false || time() - $modified < 2) {
            $hash = hash_file('sha256', $file);

            return $hash === false ? null : substr($hash, 0, 16);
        }

        $key = $file.'|'.FileSignature::of($file);
        self::$hashes ??= self::load();

        if (isset(self::$hashes[$key])) {
            return self::$hashes[$key];
        }

        $hash = hash_file('sha256', $file);

        if ($hash === false) {
            return null;
        }

        self::$hashes[$key] = substr($hash, 0, 16);
        self::store();

        return self::$hashes[$key];
    }

    /** Forget the in-memory table (tests). */
    public static function flush(): void
    {
        self::$hashes = null;
    }

    public static function path(): string
    {
        return storage_path('framework/cache/tardis-asset-hashes.php');
    }

    /**
     * @return array<string, string>
     */
    private static function load(): array
    {
        $path = self::path();

        if (! is_file($path)) {
            return [];
        }

        try {
            $data = include $path;
        } catch (\Throwable) {
            return [];
        }

        return is_array($data) ? array_filter($data, 'is_string') : [];
    }

    /**
     * Written atomically. Only entries for files that still exist are kept, so
     * the table does not grow with every rebuild. A cache directory that is
     * not writable only means the hashes are worked out again next time.
     */
    private static function store(): void
    {
        $entries = array_filter(
            self::$hashes ?? [],
            fn (string $key) => is_file(substr($key, 0, (int) strrpos($key, '|'))),
            ARRAY_FILTER_USE_KEY,
        );

        $path = self::path();
        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        if (! is_dir(dirname($path)) || @file_put_contents($temporary, '<?php return '.var_export($entries, true).';'.PHP_EOL) === false) {
            return;
        }

        if (! @rename($temporary, $path)) {
            @unlink($temporary);

            return;
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }
}
