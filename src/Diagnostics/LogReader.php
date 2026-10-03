<?php

declare(strict_types=1);

namespace Tardis\Diagnostics;

/**
 * Read-only access to the application log directory.
 *
 * The panel exposes logs to administrators, so every path that reaches the
 * filesystem is validated here rather than in the caller: a filename is only
 * accepted when it matches the configured pattern and resolves inside the log
 * directory, which stops `../` traversal and symlinks that point elsewhere.
 */
final class LogReader
{
    public function path(): string
    {
        $configured = config('tardis.system.logs.path');

        if (! is_string($configured) || trim($configured) === '') {
            return storage_path('logs');
        }

        $configured = trim($configured);

        // An absolute path is used as-is; anything else is relative to storage.
        if (str_starts_with($configured, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $configured) === 1) {
            return rtrim($configured, '/\\');
        }

        return storage_path($configured);
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        $directory = $this->path();

        if (! is_dir($directory)) {
            return [];
        }

        $pattern = $this->pattern();
        $files = [];

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $directory.DIRECTORY_SEPARATOR.$entry;

            if (! is_file($full) || ! $this->matches($entry, $pattern)) {
                continue;
            }

            $files[] = $entry;
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    public function tail(string $file, ?int $lines = null): array
    {
        $path = $this->resolve($file);
        $lines = max(1, $lines ?? (int) config('tardis.system.logs.tail', 200));

        $size = filesize($path);

        if ($size === false || $size === 0) {
            return [];
        }

        $maxBytes = max(1, (int) config('tardis.system.logs.max_bytes', 262144));
        $read = min($size, $maxBytes);

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        try {
            fseek($handle, $size - $read);
            $contents = (string) fread($handle, $read);
        } finally {
            fclose($handle);
        }

        $contents = rtrim($contents, "\r\n");

        if ($contents === '') {
            return [];
        }

        $all = explode("\n", $contents);

        // When the read started mid-file the first line is a fragment; drop it
        // so the caller never sees a truncated log entry.
        if ($read < $size) {
            array_shift($all);
        }

        return array_values(array_slice($all, -$lines));
    }

    /**
     * Resolve a caller-supplied filename to a real path inside the log
     * directory, or throw. This is the single choke point for path safety.
     */
    private function resolve(string $file): string
    {
        $this->assertSafeName($file);

        $directory = realpath($this->path());

        if ($directory === false) {
            throw new \InvalidArgumentException("The log directory [{$this->path()}] does not exist.");
        }

        $candidate = realpath($directory.DIRECTORY_SEPARATOR.$file);

        if ($candidate === false || ! is_file($candidate) || ! is_readable($candidate)) {
            throw new \InvalidArgumentException("The log file [{$file}] does not exist or is not readable.");
        }

        // realpath() resolves symlinks, so a link pointing outside the log
        // directory is rejected here even though its name matched the pattern.
        if (! str_starts_with($candidate, $directory.DIRECTORY_SEPARATOR)) {
            throw new \InvalidArgumentException("The log file [{$file}] is outside the log directory.");
        }

        return $candidate;
    }

    private function assertSafeName(string $file): void
    {
        if ($file === '' || str_contains($file, "\0") || str_contains($file, '/') || str_contains($file, '\\') || str_contains($file, '..')) {
            throw new \InvalidArgumentException("Invalid log file name [{$file}].");
        }

        if (! $this->matches($file, $this->pattern())) {
            throw new \InvalidArgumentException("Invalid log file name [{$file}].");
        }
    }

    private function pattern(): string
    {
        $pattern = config('tardis.system.logs.filename_pattern');

        return is_string($pattern) && $pattern !== '' ? $pattern : '/^[A-Za-z0-9._-]+\.log$/';
    }

    /**
     * preg_match() emits a warning on a malformed pattern; the configured
     * pattern is host-supplied, so a bad one must fail closed (no match)
     * rather than leak a warning into the response.
     */
    private function matches(string $file, string $pattern): bool
    {
        try {
            return preg_match($pattern, $file) === 1;
        } catch (\Throwable) {
            return false;
        }
    }
}
