<?php

namespace Tardis\Formfields\Types;

/**
 * A select that accepts several values, stored as a JSON array. Behaves like
 * SelectField for its options, but the value round-trips as a list and an
 * empty selection persists null rather than [].
 */
class SelectMultipleField extends SelectField
{
    public function type(): string
    {
        return 'select_multiple';
    }

    public function render(): string
    {
        return 'tardis::formfields.select-multiple';
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
        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }

        return ($value === null || $value === '') ? [] : [(string) $value];
    }

    /**
     * @return array<int, string>|null
     */
    public function store(mixed $value): mixed
    {
        $values = array_values(array_filter(
            array_map('strval', is_array($value) ? $value : []),
            fn ($item) => $item !== '',
        ));

        return $values === [] ? null : $values;
    }
}
