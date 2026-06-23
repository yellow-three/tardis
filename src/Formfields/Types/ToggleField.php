<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class ToggleField extends Formfield
{
    public function type(): string
    {
        return 'toggle';
    }

    public function render(): string
    {
        return 'tardis::formfields.toggle';
    }
}
