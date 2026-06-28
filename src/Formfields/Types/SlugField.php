<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class SlugField extends Formfield
{
    public ?string $from = null;

    public function from(string $field): self
    {
        $this->from = $field;

        return $this;
    }

    public function type(): string
    {
        return 'slug';
    }

    public function render(): string
    {
        return 'tardis::formfields.slug';
    }

    public function viewData(): array
    {
        return array_merge(parent::viewData(), [
            'from' => $this->from,
        ]);
    }
}
