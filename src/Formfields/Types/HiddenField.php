<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class HiddenField extends Formfield
{
    public function type(): string
    {
        return 'hidden';
    }

    public function render(): string
    {
        return 'tardis::formfields.hidden';
    }
}
