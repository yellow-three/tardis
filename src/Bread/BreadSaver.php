<?php

declare(strict_types=1);

namespace Tardis\Bread;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tardis\Events\BreadRecordCreated;
use Tardis\Events\BreadRecordUpdated;
use Tardis\Formfields\Formfield;
use Tardis\Manager\FormfieldManager;
use Throwable;

/**
 * Turns a submitted form into a stored record. The create and edit pages used
 * to carry two copies of this; both now go through here.
 */
class BreadSaver
{
    public function __construct(protected FormfieldManager $formfields) {}

    /**
     * @param  array<int, array<string, mixed>>  $fieldDefinitions  the fields shown on the form
     * @param  array<string, mixed>  $form
     *
     * @throws MissingColumnsException
     */
    public function create(string $slug, string $modelClass, array $fieldDefinitions, array $form): Model
    {
        [$data, $relations] = $this->prepare($modelClass, $fieldDefinitions, $form);

        // The parent row is inserted before the relations are written, so a
        // relation that dies mid-loop would otherwise leave a committed record
        // with half its relations. Only the database writes are wrapped:
        // uploads were already moved to disk while the values were transformed.
        $created = DB::transaction(function () use ($modelClass, $data, $relations) {
            $model = $modelClass::create($data);

            foreach ($relations as [$field, $value]) {
                $field->stored($value, $model);
            }

            return $model;
        });

        BreadRecordCreated::dispatch($slug, $created, $data);

        return $created;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fieldDefinitions
     * @param  array<string, mixed>  $form
     * @return array<string, mixed> the attributes that changed
     *
     * @throws MissingColumnsException
     */
    public function update(string $slug, Model $record, array $fieldDefinitions, array $form): array
    {
        [$data, $relations] = $this->prepare($record::class, $fieldDefinitions, $form);

        DB::transaction(function () use ($record, $data, $relations) {
            $record->update($data);

            foreach ($relations as [$field, $value]) {
                $field->updated($value, $record);
            }
        });

        $changes = $record->getChanges();

        if ($changes !== []) {
            BreadRecordUpdated::dispatch($slug, $record, array_intersect_key($record->getPrevious(), $changes), $changes);
        }

        return $changes;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array{0: Formfield, 1: mixed}>}
     */
    protected function prepare(string $modelClass, array $fieldDefinitions, array $form): array
    {
        $fields = $this->formfields->fields($fieldDefinitions);
        $data = [];
        $relations = [];

        foreach ($fields as $field) {
            $value = $form[$field->name] ?? $field->default;

            if ($field->skipWhenBlank() && blank($value)) {
                continue;
            }

            if ($field->isRelation()) {
                $relations[] = [$field, $value];

                continue;
            }

            $data[$field->name] = $field->transform($value);
        }

        // A field left blank must not be written as NULL when the table refuses
        // nulls: the statement would die on SQLSTATE 23000 and the user would
        // not be told which field was at fault.
        $missing = array_values(array_filter(
            $this->requiredColumns($modelClass, $fields),
            fn (string $column) => ! array_key_exists($column, $data) || blank($data[$column]),
        ));

        if ($missing !== []) {
            throw new MissingColumnsException($missing);
        }

        return [$data, $relations];
    }

    /**
     * Columns the table declares NOT NULL without a default and the form covers.
     *
     * @param  array<int, Formfield>  $fields
     * @return array<int, string>
     */
    protected function requiredColumns(string $modelClass, array $fields): array
    {
        try {
            $model = new $modelClass;
            $schema = $model->getConnection()->getSchemaBuilder()->getColumns($model->getTable());
        } catch (Throwable) {
            // A missing or renamed table is reported by the write itself.
            return [];
        }

        $inDefinition = array_map(fn (Formfield $field) => $field->name, $fields);
        $required = [];

        foreach ($schema as $column) {
            // A NULL default is still a default, so test the value and not just
            // the key's presence (the MySQL schema always reports the key).
            if (($column['nullable'] ?? true) || ($column['default'] ?? null) !== null) {
                continue;
            }

            if (in_array($column['name'], $inDefinition, true)) {
                $required[] = $column['name'];
            }
        }

        return $required;
    }
}
