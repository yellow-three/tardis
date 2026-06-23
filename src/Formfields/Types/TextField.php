<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class TextField extends Formfield
{
    public function type(): string
    {
        return 'text';
    }

    public function render(): string
    {
        return 'tardis::formfields.text';
    }
}
