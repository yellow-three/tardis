<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class MarkdownField extends Formfield
{
    public function type(): string
    {
        return 'markdown';
    }

    public function render(): string
    {
        return 'tardis::formfields.markdown';
    }
}
