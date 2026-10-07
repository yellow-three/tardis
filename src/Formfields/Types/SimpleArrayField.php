<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

/**
 * An ordered list of plain strings stored as a JSON array. Values are edited
 * as repeatable text inputs; blank entries are dropped on save so a list never
 * carries empty holes.
 */
class SimpleArrayField extends Formfield
{
    protected array $configurable = ['min', 'max'];

    public int $min = 0;

    public int $max = 0;

    public function type(): string
    {
        return 'simple_array';
    }

    public function render(): string
    {
        return 'tardis::formfields.simple-array';
    }

    public function add(mixed $value): mixed
    {
        return $this->edit($value);
    }

    /**
     * @return array<int, string>
     */
    public function edit(mixed $value): mixed
    {
        return array_values(array_map('strval', is_array($value) ? $value : []));
    }

    /**
     * @return array<int, string>|null
     */
    public function store(mixed $value): mixed
    {
        $items = array_values(array_filter(
            array_map(fn ($item) => trim((string) $item), is_array($value) ? $value : []),
            fn ($item) => $item !== '',
        ));

        return $items === [] ? null : $items;
    }

    protected function extraViewData(): array
    {
        return [
            'min' => $this->min,
            'max' => $this->max,
        ];
    }
}
