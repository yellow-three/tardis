<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class NumberField extends Formfield
{
    public function type(): string
    {
        return 'number';
    }

    public function render(): string
    {
        return 'tardis::formfields.number';
    }
}
