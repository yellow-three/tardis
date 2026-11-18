<?php

declare(strict_types=1);

namespace Tardis\Import\Voyager;

use Tardis\Bread\Sources\JsonBreadSource;

/**
 * A Voyager data_type with its data_rows as a Tardis BREAD definition.
 */
final class BreadMapper
{
    /** @var list<string> problems found by the last map() call */
    public array $problems = [];

    public function __construct(private readonly FieldMapper $fields = new FieldMapper) {}

    /**
     * @param  array<string, mixed>  $type  a data_types row (details decoded)
     * @param  list<array<string, mixed>>  $rows  its data_rows rows (details decoded)
     * @return array<string, mixed>|null null when the data type cannot become a BREAD
     */
    public function map(array $type, array $rows): ?array
    {
        $this->problems = [];
        $slug = (string) ($type['slug'] ?? '');
        $details = is_array($type['details'] ?? null) ? $type['details'] : [];

        if (! JsonBreadSource::isValidSlug($slug)) {
            $this->problems[] = "Slug [{$slug}] is not a valid BREAD slug.";

            return null;
        }

        usort($rows, fn (array $a, array $b) => (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0));

        $fields = [];

        foreach ($rows as $row) {
            $field = $this->fields->map($row);

            if ($field === null) {
                $this->problems[] = "Field [{$row['field']}]: {$this->fields->problem}";

                continue;
            }

            if (in_array($field['type'], ['has_one', 'has_many', 'belongs_to_many'], true)) {
                $this->problems[] = "Field [{$field['name']}]: check that the model has a [{$field['relation']}] relation method; Voyager does not record its name.";
            }

            // A relationship row replaces the plain foreign key row Voyager
            // usually keeps next to it.
            $fields[$field['name']] = $field;
        }

        return array_filter([
            'slug' => $slug,
            'model' => ltrim((string) ($type['model_name'] ?? ''), '\\'),
            'name' => (string) ($type['display_name_singular'] ?? '') ?: $slug,
            'name_plural' => (string) ($type['display_name_plural'] ?? '') ?: $slug,
            'description' => $type['description'] ?? null,
            'fields' => array_values($fields),
            'search_key' => $details['default_search_key'] ?? null,
            'order_column' => $details['order_column'] ?? null,
            'order_display_column' => $details['order_display_column'] ?? null,
            'order_direction' => in_array($details['order_direction'] ?? null, ['asc', 'desc'], true) ? $details['order_direction'] : 'asc',
            'scope' => $details['scope'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
