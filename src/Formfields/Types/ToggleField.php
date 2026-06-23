<?php

namespace Tardis\Core\Formfields\Types;

use Tardis\Core\Formfields\Formfield;

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
