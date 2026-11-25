<?php

declare(strict_types=1);

namespace Tardis\Bread\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadQuery;
use Tardis\Bread\Livewire\ReadsBreadDefinition;

/**
 * Which fields of a BREAD the JSON API reads and writes for the current
 * user, and how a record looks in a response. The same rules as the panel:
 * a field behind an "ability" the user lacks is neither shown nor written,
 * add/edit/read switches apply, passwords and a model's hidden attributes
 * never leave the server.
 */
final class ApiFields
{
    /** Field types a JSON body cannot carry (uploads). */
    private const UNWRITABLE = ['file', 'image', 'multiple_images'];

    /** Field types whose value is never sent back. */
    private const UNREADABLE = ['password'];

    public function __construct(private readonly BreadDefinition $definition) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function visible(): array
    {
        return array_values(ReadsBreadDefinition::withoutHiddenFields($this->definition->toDisplayArray())['fields'] ?? []);
    }

    /**
     * The fields a response shows: those the read page shows.
     *
     * @return list<array<string, mixed>>
     */
    public function readable(): array
    {
        return array_values(array_filter($this->visible(), fn (array $field) => ($field['read'] ?? true) !== false
            && ! in_array($field['type'] ?? '', self::UNREADABLE, true)));
    }

    /**
     * The fields a create ("add") or update ("edit") may set.
     *
     * @return list<array<string, mixed>>
     */
    public function writable(string $context): array
    {
        return array_values(array_filter($this->visible(), fn (array $field) => ($field[$context] ?? true) !== false
            && ! in_array($field['type'] ?? '', self::UNWRITABLE, true)));
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Model $record): array
    {
        $hidden = array_flip($record->getHidden());
        $data = [$record->getKeyName() => $record->getKey()];

        foreach ($this->readable() as $field) {
            $name = (string) ($field['name'] ?? '');

            if ($name === '' || isset($hidden[$name])) {
                continue;
            }

            $data[$name] = BreadQuery::showsRelation($field) && ($field['type'] ?? '') !== 'belongs_to'
                ? $this->relatedKeys($record, $field)
                : $record->getAttribute($name);
        }

        foreach (['created_at', 'updated_at'] as $timestamp) {
            if ($record->usesTimestamps() && ! array_key_exists($timestamp, $data) && $record->getAttribute($timestamp) !== null) {
                $data[$timestamp] = $record->getAttribute($timestamp);
            }
        }

        return $data;
    }

    /**
     * What a client needs to build a form: name, type, label and rules.
     *
     * @return list<array<string, mixed>>
     */
    public function schema(): array
    {
        return array_map(fn (array $field) => array_filter([
            'name' => $field['name'] ?? null,
            'type' => $field['type'] ?? 'text',
            'label' => $field['label'] ?? null,
            'validation' => $field['validation'] ?? null,
            'options' => $field['options'] ?? null,
            'read' => ($field['read'] ?? true) !== false,
            'add' => ($field['add'] ?? true) !== false && ! in_array($field['type'] ?? '', self::UNWRITABLE, true),
            'edit' => ($field['edit'] ?? true) !== false && ! in_array($field['type'] ?? '', self::UNWRITABLE, true),
        ], fn ($value) => $value !== null), $this->visible());
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<mixed>|mixed
     */
    private function relatedKeys(Model $record, array $field): mixed
    {
        $relation = (string) ($field['relation'] ?? $field['name'] ?? '');

        if ($relation === '' || ! method_exists($record, $relation)) {
            return null;
        }

        $related = $record->getRelationValue($relation);

        if ($related instanceof Collection) {
            return $related->map(fn (Model $model) => $model->getKey())->values()->all();
        }

        return $related instanceof Model ? $related->getKey() : null;
    }
}
