<?php

declare(strict_types=1);

namespace Tardis\Support;

use InvalidArgumentException;
use Tardis\Models\ActivityLog;
use Tardis\Models\Media;
use Tardis\Models\Permission;
use Tardis\Models\Role;

class ModelResolver
{
    public static function role(): string
    {
        return self::resolve('role', Role::class);
    }

    public static function permission(): string
    {
        return self::resolve('permission', Permission::class);
    }

    public static function media(): string
    {
        return self::resolve('media', Media::class);
    }

    public static function activityLog(): string
    {
        return self::resolve('activity_log', ActivityLog::class);
    }

    protected static function resolve(string $key, string $baseClass): string
    {
        $configKey = 'tardis.models.'.$key;
        $class = config($configKey, $baseClass);

        if (! is_string($class) || $class === '') {
            throw new InvalidArgumentException(sprintf(
                'Invalid model class for config key %s: %s',
                $configKey,
                is_scalar($class) ? (string) $class : gettype($class)
            ));
        }

        if (! class_exists($class)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid model class for config key %s: %s (class does not exist)',
                $configKey,
                $class
            ));
        }

        if (! is_subclass_of($class, $baseClass) && $class !== $baseClass) {
            throw new InvalidArgumentException(sprintf(
                'Invalid model class for config key %s: %s does not extend %s',
                $configKey,
                $class,
                $baseClass
            ));
        }

        return $class;
    }
}
