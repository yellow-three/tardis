<?php

declare(strict_types=1);

namespace Tardis\Support;

/**
 * Read-modify-write of the small JSON state files under storage/tardis.
 *
 * update() holds an exclusive lock on a sibling ".lock" file while it reads
 * the current content, applies the change and writes the result atomically
 * (temp file + rename). Two requests changing the file at the same time
 * therefore both land, instead of the later one writing back a stale copy
 * that drops the earlier change. Readers need no lock: the rename means they
 * see either the old or the new file, never half of one.
 */
final class JsonFile
{
    /**
     * @return array<mixed>
     */
    public static function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param  callable(array<mixed>): array<mixed>  $change
     * @return array<mixed> the data as written
     */
    public static function update(string $path, callable $change, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): array
    {
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $lock = fopen($path.'.lock', 'c');

        if ($lock === false) {
            throw new \RuntimeException("Could not open the lock for [{$path}].");
        }

        try {
            flock($lock, LOCK_EX);

            $data = $change(self::read($path));

            $tmp = tempnam($dir, '.'.basename($path, '.json').'-');

            if ($tmp === false || file_put_contents($tmp, json_encode($data, $flags)."\n") === false || ! rename($tmp, $path)) {
                throw new \RuntimeException("Could not write [{$path}].");
            }

            return $data;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
