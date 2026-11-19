<?php

declare(strict_types=1);

namespace Tardis\Support;

/**
 * Identifies one version of a state file, so a long-running worker (Octane,
 * a queue worker) notices that another process changed it.
 *
 * Writes go through a temporary file renamed into place (JsonFile), which
 * gives every version a new inode: two writes within the same second, with
 * the same size, still differ. Checking costs one stat call.
 */
final class FileSignature
{
    public static function of(string $path): string
    {
        clearstatcache(true, $path);

        $stat = @stat($path);

        return $stat === false ? 'missing' : $stat['ino'].':'.$stat['mtime'].':'.$stat['size'];
    }
}
