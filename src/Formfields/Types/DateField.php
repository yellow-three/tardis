<?php

namespace Tardis\Core\Formfields\Types;

use Tardis\Core\Formfields\Formfield;

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
