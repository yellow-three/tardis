<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

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
