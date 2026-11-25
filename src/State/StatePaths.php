<?php

declare(strict_types=1);

namespace Tardis\State;

use Illuminate\Support\Str;

/**
 * Maps a file-driver path to the key it has everywhere else, and back:
 * storage/tardis/plugins.json is "plugins.json", a BREAD file under
 * tardis.bread.path is "bread/<name>", whatever that path is on this server.
 */
final class StatePaths
{
    public static function root(): string
    {
        return rtrim(storage_path('tardis'), '/\\');
    }

    public static function breadRoot(): string
    {
        return rtrim((string) (config('tardis.bread.path') ?: storage_path('tardis/bread')), '/\\');
    }

    public static function key(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        foreach (['bread/' => self::breadRoot(), '' => self::root()] as $prefix => $root) {
            $root = str_replace('\\', '/', $root).'/';

            if (str_starts_with($path, $root)) {
                return $prefix.substr($path, strlen($root));
            }
        }

        // A store pointed somewhere else (a test, a custom path) keeps its own key.
        return ltrim($path, '/');
    }

    public static function path(string $key): string
    {
        return Str::startsWith($key, 'bread/')
            ? self::breadRoot().'/'.substr($key, 6)
            : self::root().'/'.$key;
    }
}
