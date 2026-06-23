<?php

namespace Tardis\Core\Formfields\Types;

use Tardis\Core\Formfields\Formfield;

class TextareaField extends Formfield
{
    public function type(): string
    {
        return 'textarea';
    }

    public function render(): string
    {
        return 'tardis::formfields.textarea';
    }
}
