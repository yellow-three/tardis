<?php

namespace Tardis\Bread;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ModelReflector
{
    public static function analyze(string $modelClass): array
    {
        if (! class_exists($modelClass)) {
            return [];
        }

        $model = new $modelClass;

        return [
            'table' => $model->getTable(),
            'fillable' => $model->getFillable(),
            'dates' => $model->getDates(),
            'casts' => $model->getCasts(),
            'accessors' => self::getAccessors($model),
            'scopes' => self::getScopes($model),
            'relationships' => self::getRelationships($model),
            'softDelete' => self::hasSoftDeletes($model),
            'timestamps' => $model->timestamps,
        ];
    }

    public static function getFields(string $modelClass): array
    {
        $analysis = self::analyze($modelClass);
        $fields = [];

        foreach ($analysis['fillable'] as $field) {
            $type = self::guessFieldType($field, $analysis['casts']);

            $fields[$field] = [
                'name' => $field,
                'type' => $type,
                'label' => Str::headline($field),
                'required' => false,
                'browse' => true,
                'read' => true,
                'edit' => true,
                'add' => true,
                'validation' => [],
            ];
        }

        return $fields;
    }

    protected static function getAccessors(Model $model): array
    {
        $accessors = [];

        foreach (class_parents($model) as $parent) {
            $methods = get_class_methods($parent);
            foreach ($methods as $method) {
                if (str_starts_with($method, 'get') && str_ends_with($method, 'Attribute')) {
                    $name = lcfirst(substr($method, 3, -9));
                    $accessors[] = $name;
                }
            }
        }

        $methods = get_class_methods($model);
        foreach ($methods as $method) {
            if (str_starts_with($method, 'get') && str_ends_with($method, 'Attribute')) {
                $name = lcfirst(substr($method, 3, -9));
                if (! in_array($name, $accessors)) {
                    $accessors[] = $name;
                }
            }
        }

        return $accessors;
    }

    protected static function getScopes(Model $model): array
    {
        $scopes = [];
        $methods = get_class_methods($model);

        foreach ($methods as $method) {
            if (str_starts_with($method, 'scope') && strlen($method) > 5) {
                $scopes[] = lcfirst(substr($method, 5));
            }
        }

        return $scopes;
    }

    protected static function getRelationships(Model $model): array
    {
        $relationships = [];
        $methods = get_class_methods($model);

        $relationTypes = ['hasMany', 'belongsTo', 'belongsToMany', 'hasOne', 'morphMany', 'morphTo', 'hasManyThrough'];

        foreach ($methods as $method) {
            $return = null;
            try {
                $reflection = new \ReflectionMethod($model, $method);
                if ($reflection->getNumberOfParameters() === 0) {
                    $return = $reflection->invoke($model);
                }
            } catch (\Throwable) {
                continue;
            }

            if ($return instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                $type = class_basename($return);
                $related = $return->getRelated();

                $relationships[$method] = [
                    'type' => lcfirst($type),
                    'model' => get_class($related),
                    'foreign_key' => $return instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo
                        ? $return->getForeignKeyName()
                        : null,
                ];
            }
        }

        return $relationships;
    }

    protected static function hasSoftDeletes(Model $model): bool
    {
        return in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model));
    }

    protected static function guessFieldType(string $field, array $casts): string
    {
        if (isset($casts[$field])) {
            return match ($casts[$field]) {
                'boolean' => 'toggle',
                'integer', 'decimal', 'float' => 'number',
                'date' => 'date',
                'datetime' => 'datetime',
                'array', 'json' => 'simple_array',
                default => 'text',
            };
        }

        if (Str::contains($field, ['_at', '_time'])) {
            return 'datetime';
        }

        if (Str::contains($field, ['_date'])) {
            return 'date';
        }

        if (Str::contains($field, ['password', 'secret'])) {
            return 'password';
        }

        if (Str::contains($field, ['image', 'avatar', 'photo', 'picture'])) {
            return 'image';
        }

        if (Str::contains($field, ['url', 'link'])) {
            return 'text';
        }

        if (Str::contains($field, ['email'])) {
            return 'email';
        }

        if (Str::contains($field, ['description', 'body', 'content', 'text', 'note'])) {
            return 'textarea';
        }

        if (Str::contains($field, ['is_', 'has_', 'enable'])) {
            return 'toggle';
        }

        return 'text';
    }
}
