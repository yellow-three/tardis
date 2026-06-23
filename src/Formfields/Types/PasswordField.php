<?php

namespace Tardis\Core\Formfields\Types;

use Tardis\Core\Formfields\Formfield;

class PasswordField extends Formfield
{
    public function type(): string
    {
        return 'password';
    }

    public function render(): string
    {
        return 'tardis::formfields.password';
    }
}
