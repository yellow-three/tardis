<?php

declare(strict_types=1);

namespace Tardis\State;

use Symfony\Component\Finder\Finder;
use Tardis\Support\FileSignature;
use Tardis\Support\JsonFile;

/**
 * The default driver: one JSON file per entry under storage/tardis, written
 * atomically (temp file + rename) and under a lock for read-modify-write.
 */
class FileStateStore implements StateStore
{
    public function get(string $path): ?string
    {
        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    public function put(string $path, string $contents): void
    {
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Inside the same directory, so the rename is atomic.
        $tmp = tempnam($dir, '.'.basename($path).'-');

        if ($tmp === false || file_put_contents($tmp, $contents) === false || ! @rename($tmp, $path)) {
            if (is_string($tmp)) {
                @unlink($tmp);
            }

            throw new \RuntimeException("Could not write [{$path}].");
        }
    }

    public function exists(string $path): bool
    {
        return is_file($path);
    }

    public function delete(string $path): bool
    {
        return is_file($path) && unlink($path);
    }

    public function files(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $names = [];

        foreach (new \DirectoryIterator($directory) as $file) {
            if ($file->isFile() && ! str_starts_with($file->getFilename(), '.')) {
                $names[] = $file->getFilename();
            }
        }

        sort($names);

        return $names;
    }

    public function signature(string $path): string
    {
        return FileSignature::of($path);
    }

    public function read(string $path): array
    {
        return JsonFile::read($path);
    }

    public function update(string $path, callable $change, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): array
    {
        return JsonFile::update($path, $change, $flags);
    }

    public function dump(): array
    {
        $entries = [];

        foreach (array_unique([StatePaths::root(), StatePaths::breadRoot()]) as $root) {
            if (! is_dir($root)) {
                continue;
            }

            foreach (Finder::create()->files()->in($root)->ignoreDotFiles(true)->notName('*.lock') as $file) {
                $entries[StatePaths::key($file->getPathname())] = (string) $file->getContents();
            }
        }

        ksort($entries);

        return $entries;
    }
}
