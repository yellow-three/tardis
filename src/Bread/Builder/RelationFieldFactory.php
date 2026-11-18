<?php

declare(strict_types=1);

namespace Tardis\Bread\Builder;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tardis\Bread\BreadDefinition;

/**
 * Turns a relationship the builder detected on a model (ModelReflector) into
 * the field definition that puts it on the form.
 */
final class RelationFieldFactory
{
    /** Eloquent relation (as detected) => Tardis field type. */
    private const TYPES = [
        'belongsTo' => 'belongs_to',
        'hasOne' => 'has_one',
        'belongsToMany' => 'belongs_to_many',
        'hasMany' => 'has_many',
    ];

    /** Columns tried, in order, as the label of a related record. */
    private const LABEL_COLUMNS = ['name', 'title', 'label', 'email', 'slug'];

    public static function supports(string $relationType): bool
    {
        return isset(self::TYPES[$relationType]);
    }

    /**
     * @param  array<string, mixed>  $detected  ['type' => 'belongsTo', 'model' => ..., 'foreign_key' => ...]
     * @param  array<string, array<string, mixed>>  $fields  the builder's field config, keyed by name
     * @return array<string, mixed>|null
     */
    public static function fromDetected(string $relation, array $detected, array $fields): ?array
    {
        $type = self::TYPES[$detected['type'] ?? ''] ?? null;
        $model = (string) ($detected['model'] ?? '');

        if ($type === null || ! BreadDefinition::isModelClass($model)) {
            return null;
        }

        $name = $type === 'belongs_to' ? (string) ($detected['foreign_key'] ?? '') : $relation;

        if ($name === '') {
            return null;
        }

        $existing = $fields[$name] ?? [];
        $listed = in_array($type, ['belongs_to', 'belongs_to_many'], true);

        return [
            'name' => $name,
            'type' => $type,
            'label' => $existing['label'] ?? Str::headline($relation),
            'required' => $existing['required'] ?? false,
            'browse' => $existing['browse'] ?? $listed,
            'read' => $existing['read'] ?? true,
            'edit' => $existing['edit'] ?? true,
            'add' => $existing['add'] ?? true,
            'validation' => $existing['validation'] ?? [],
            'relation' => $relation,
            'model' => $model,
            'label_column' => self::labelColumn($model),
        ];
    }

    /**
     * @param  array<string, mixed>  $detected
     * @param  array<string, array<string, mixed>>  $fields
     */
    public static function isOnForm(string $relation, array $detected, array $fields): bool
    {
        $type = self::TYPES[$detected['type'] ?? ''] ?? null;

        if ($type === null) {
            return false;
        }

        foreach ($fields as $field) {
            if (($field['type'] ?? null) === $type && ($field['relation'] ?? null) === $relation) {
                return true;
            }
        }

        return false;
    }

    /**
     * The first readable column of the related table, else its key.
     *
     * @param  class-string<Model>  $model
     */
    public static function labelColumn(string $model): string
    {
        $instance = new $model;

        try {
            $columns = Schema::connection($instance->getConnectionName())->getColumnListing($instance->getTable());
        } catch (\Throwable) {
            $columns = [];
        }

        foreach (self::LABEL_COLUMNS as $column) {
            if (in_array($column, $columns, true)) {
                return $column;
            }
        }

        return $instance->getKeyName();
    }
}
