<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

/**
 * A repeatable group of sub-fields stored as a JSON array of rows. The row
 * shape is declared in the BREAD definition under `fields`; each entry is a
 * sub-definition ({name, type, label, options}) or a bare name (text).
 *
 * Only the storage transforms live here; the rows are edited client-side
 * through Alpine (@entangle) so adding or removing a row never round-trips.
 */
class RepeaterField extends Formfield
{
    protected array $configurable = ['fields'];

    /** @var array<int, mixed> */
    public array $fields = [];

    public function type(): string
    {
        return 'repeater';
    }

    public function render(): string
    {
        return 'tardis::formfields.repeater';
    }

    public function add(mixed $value): mixed
    {
        return $this->edit($value);
    }

    /**
     * Every row is normalised to carry each declared sub-field, so a stored row
     * that predates a column still binds without an undefined index.
     *
     * @return array<int, array<string, mixed>>
     */
    public function edit(mixed $value): mixed
    {
        $keys = array_column($this->subfields(), 'name');

        return array_values(array_map(function ($row) use ($keys) {
            $row = is_array($row) ? $row : [];

            foreach ($keys as $key) {
                if (! array_key_exists($key, $row)) {
                    $row[$key] = '';
                }
            }

            return $row;
        }, is_array($value) ? $value : []));
    }

    /**
     * Rows where every sub-field is blank are dropped, so an empty "add" press
     * never persists a ghost row; an all-blank repeater stores null.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function store(mixed $value): mixed
    {
        $rows = [];

        foreach (is_array($value) ? $value : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $row = array_map(
                fn ($item) => is_string($item) ? trim($item) : $item,
                $row,
            );

            if ($this->rowHasValue($row)) {
                $rows[] = $row;
            }
        }

        return $rows === [] ? null : $rows;
    }

    /**
     * A blank row in the shape the form expects, for the "add row" control.
     *
     * @return array<string, string>
     */
    public function blankRow(): array
    {
        return array_fill_keys(array_column($this->subfields(), 'name'), '');
    }

    /**
     * The sub-definitions as a renderable list.
     *
     * @return array<int, array{name: string, type: string, label: string, options: array}>
     */
    public function subfields(): array
    {
        $subfields = [];

        foreach ($this->fields as $definition) {
            if (is_string($definition)) {
                $definition = ['name' => $definition, 'type' => 'text'];
            }

            if (! is_array($definition) || empty($definition['name'])) {
                continue;
            }

            $type = (string) ($definition['type'] ?? 'text');

            $subfields[] = [
                'name' => (string) $definition['name'],
                'type' => in_array($type, ['text', 'number', 'textarea', 'select'], true) ? $type : 'text',
                'label' => (string) ($definition['label'] ?? ucfirst(str_replace(['_', '-'], ' ', (string) $definition['name']))),
                'options' => (array) ($definition['options'] ?? []),
            ];
        }

        return $subfields;
    }

    protected function extraViewData(): array
    {
        return [
            'subfields' => $this->subfields(),
            'blankRow' => $this->blankRow(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowHasValue(array $row): bool
    {
        foreach ($row as $item) {
            if ($item === null) {
                continue;
            }

            if (is_array($item) ? $item !== [] : $item !== '') {
                return true;
            }
        }

        return false;
    }
}
