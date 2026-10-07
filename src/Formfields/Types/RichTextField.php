<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

/**
 * A long-form, HTML-capable text body. Rendered as a plain textarea so the
 * value round-trips without needing a bundled WYSIWYG asset.
 */
class RichTextField extends Formfield
{
    public function type(): string
    {
        return 'rich_text';
    }

    public function render(): string
    {
        return 'tardis::formfields.rich-text';
    }
}
