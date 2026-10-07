<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class ColorField extends Formfield
{
    public function type(): string
    {
        return 'color';
    }

    public function render(): string
    {
        return 'tardis::formfields.color';
    }
}
