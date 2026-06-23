<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class DateField extends Formfield
{
    public function type(): string
    {
        return 'date';
    }

    public function render(): string
    {
        return 'tardis::formfields.date';
    }
}
