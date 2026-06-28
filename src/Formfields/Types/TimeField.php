<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class TimeField extends Formfield
{
    public function type(): string
    {
        return 'time';
    }

    public function render(): string
    {
        return 'tardis::formfields.time';
    }
}
