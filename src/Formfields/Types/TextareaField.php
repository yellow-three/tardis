<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

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
