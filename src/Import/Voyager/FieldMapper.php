<?php

declare(strict_types=1);

namespace Tardis\Import\Voyager;

use Illuminate\Support\Str;

/**
 * One Voyager data_row as a Tardis field definition.
 *
 * Returns null, with the reason in $problem, for a row Tardis has no field
 * for (multiple_images, a relationship kind it does not support).
 */
final class FieldMapper
{
    /** Voyager type => Tardis type. Relationships are mapped separately. */
    private const TYPES = [
        'text' => 'text',
        'text_area' => 'textarea',
        'rich_text_box' => 'rich_text',
        'code_editor' => 'code_editor',
        'markdown_editor' => 'markdown',
        'number' => 'number',
        'checkbox' => 'toggle',
        'color' => 'color',
        'date' => 'date',
        'timestamp' => 'datetime',
        'time' => 'time',
        'file' => 'file',
        'image' => 'image',
        'password' => 'password',
        'radio_btn' => 'radio',
        'select_dropdown' => 'select',
        'select_multiple' => 'select_multiple',
        'hidden' => 'hidden',
        'coordinates' => 'coordinates',
        'media_picker' => 'media_picker',
    ];

    private const RELATIONS = [
        'belongsTo' => 'belongs_to',
        'hasOne' => 'has_one',
        'hasMany' => 'has_many',
        'belongsToMany' => 'belongs_to_many',
    ];

    public ?string $problem = null;

    /**
     * @param  array<string, mixed>  $row  a data_rows row (details already decoded)
     * @return array<string, mixed>|null
     */
    public function map(array $row): ?array
    {
        $this->problem = null;
        $details = is_array($row['details'] ?? null) ? $row['details'] : [];
        $voyagerType = (string) ($row['type'] ?? 'text');

        $field = [
            'name' => (string) $row['field'],
            'label' => (string) ($row['display_name'] ?? '') ?: Str::headline((string) $row['field']),
            'browse' => (bool) ($row['browse'] ?? true),
            'read' => (bool) ($row['read'] ?? true),
            'edit' => (bool) ($row['edit'] ?? true),
            'add' => (bool) ($row['add'] ?? true),
            'validation' => $this->validation($row, $details),
        ];

        if ($voyagerType === 'relationship') {
            return $this->relationship($field, $details);
        }

        $type = self::TYPES[$voyagerType] ?? null;

        if ($type === null) {
            $this->problem = "Voyager field type [{$voyagerType}] has no Tardis equivalent.";

            return null;
        }

        $field['type'] = $type;

        foreach (['default', 'placeholder', 'description'] as $key) {
            if (isset($details[$key]) && is_scalar($details[$key]) && $details[$key] !== '') {
                $field[$key === 'description' ? 'help' : $key] = $details[$key];
            }
        }

        if (in_array($type, ['select', 'select_multiple', 'radio'], true) && is_array($details['options'] ?? null)) {
            $field['options'] = $details['options'];
        }

        if ($type === 'image') {
            $field += $this->image($details);
        }

        return $field;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>|null
     */
    private function relationship(array $field, array $details): ?array
    {
        $kind = (string) ($details['type'] ?? '');
        $type = self::RELATIONS[$kind] ?? null;
        $model = ltrim((string) ($details['model'] ?? ''), '\\');

        if ($type === null || $model === '') {
            $this->problem = "Voyager relationship [{$kind}] is not supported.";

            return null;
        }

        $field['type'] = $type;
        $field['model'] = $model;
        $field['label_column'] = (string) ($details['label'] ?? 'name');

        if ($type === 'belongs_to') {
            // Voyager names the row after a synthetic key; Tardis names the
            // field after the foreign key column it writes.
            $field['name'] = (string) ($details['column'] ?? $field['name']);
            $field['relation'] = Str::camel(class_basename($model));
        } else {
            $field['name'] = $field['relation'] = $this->relationName($type, $model);
        }

        return $field;
    }

    /**
     * Voyager does not record the relation method; this is the usual name,
     * which the import report asks the user to check.
     */
    private function relationName(string $type, string $model): string
    {
        $base = Str::camel(class_basename($model));

        return $type === 'has_one' ? $base : Str::plural($base);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $details
     * @return list<string>
     */
    private function validation(array $row, array $details): array
    {
        $rule = $details['validation']['rule'] ?? $details['validation']['rules'] ?? [];
        $rules = is_string($rule) ? explode('|', $rule) : (array) $rule;
        $rules = array_values(array_filter(array_map(fn ($r) => trim((string) $r), $rules)));

        if (! empty($row['required']) && ! in_array('required', $rules, true)) {
            array_unshift($rules, 'required');
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function image(array $details): array
    {
        $image = [];

        if (is_array($details['resize'] ?? null)) {
            $image['resize'] = array_intersect_key($details['resize'], array_flip(['width', 'height']));
        }

        if (isset($details['quality'])) {
            $image['quality'] = (int) rtrim((string) $details['quality'], '%');
        }

        $thumbnails = [];

        foreach ((array) ($details['thumbnails'] ?? []) as $thumbnail) {
            if (! is_array($thumbnail) || empty($thumbnail['name'])) {
                continue;
            }

            if (is_array($thumbnail['crop'] ?? null)) {
                $thumbnails[] = ['name' => (string) $thumbnail['name'], 'width' => $thumbnail['crop']['width'] ?? null, 'height' => $thumbnail['crop']['height'] ?? null, 'crop' => true];
            } elseif (isset($thumbnail['scale'])) {
                // Voyager scales by a percentage of the original; Tardis fits a
                // box. A box from the resize width is the closest match.
                $percent = (int) rtrim((string) $thumbnail['scale'], '%');
                $width = isset($details['resize']['width']) ? (int) round($details['resize']['width'] * $percent / 100) : null;
                $thumbnails[] = ['name' => (string) $thumbnail['name'], 'width' => $width, 'height' => null];
            }
        }

        if ($thumbnails !== []) {
            $image['thumbnails'] = $thumbnails;
        }

        return $image;
    }
}
