<?php

declare(strict_types=1);

namespace Tardis\Bread;

use Illuminate\Support\Facades\Schema;

/**
 * Does a BREAD definition still match its table? A field whose column was
 * dropped or renamed breaks the list (an unknown column in the query) or
 * silently writes nothing, so tardis:doctor and the builder point it out.
 */
final class DefinitionHealth
{
    /** Field types that read a relation, not a column of their own. */
    private const NO_COLUMN = ['has_one', 'has_many', 'belongs_to_many'];

    /**
     * Fields, and the order and search keys, that name a column the table
     * does not have. A field with an "accessor", or one the forms never write
     * (add and edit false, like a model accessor), is left out.
     *
     * @param  array<string, mixed>|BreadDefinition  $definition
     * @param  list<string>|null  $columns  the table's columns; read from the model when null
     * @return list<string>
     */
    public static function missingColumns(array|BreadDefinition $definition, ?array $columns = null): array
    {
        $data = $definition instanceof BreadDefinition ? $definition->toArray() : $definition;
        $columns ??= self::columns((string) ($data['model'] ?? ''));

        if ($columns === null) {
            return [];
        }

        $wanted = [];

        foreach ((array) ($data['fields'] ?? []) as $field) {
            $name = (string) ($field['name'] ?? '');
            $writes = (bool) ($field['add'] ?? true) || (bool) ($field['edit'] ?? true);

            if ($name === '' || in_array($field['type'] ?? '', self::NO_COLUMN, true) || ! empty($field['accessor']) || ! $writes) {
                continue;
            }

            $wanted[] = $name;
        }

        foreach (['order_column', 'search_key', 'order_display_column'] as $key) {
            if (! empty($data[$key]) && is_string($data[$key])) {
                $wanted[] = $data[$key];
            }
        }

        return array_values(array_unique(array_diff($wanted, $columns)));
    }

    /**
     * The columns of a model's table, or null when there is no model or no
     * table to compare with.
     *
     * @return list<string>|null
     */
    public static function columns(string $model): ?array
    {
        if (! BreadDefinition::isModelClass($model)) {
            return null;
        }

        $instance = new $model;
        $schema = Schema::connection($instance->getConnectionName());

        if (! $schema->hasTable($instance->getTable())) {
            return null;
        }

        return $schema->getColumnListing($instance->getTable());
    }
}
