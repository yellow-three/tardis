<?php

namespace Tardis\Core\Formfields\Types;

use Tardis\Core\Formfields\Formfield;

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
