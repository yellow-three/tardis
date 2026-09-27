<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class NumberField extends Formfield
{
    public function transform(mixed $value): mixed
    {
        if (blank($value)) {
            return null;
        }

        return (float) $value;
    }

    public function type(): string
    {
        return 'number';
    }

    public function render(): string
    {
        return 'tardis::formfields.number';
    }
}
