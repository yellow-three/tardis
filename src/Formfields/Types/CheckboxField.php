<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class CheckboxField extends Formfield
{
    public array $options = [];

    public function options(array $options = []): self
    {
        $this->options = $options;

        return $this;
    }

    public function type(): string
    {
        return 'checkbox';
    }

    public function render(): string
    {
        return 'tardis::formfields.checkbox';
    }

    public function viewData(): array
    {
        return array_merge(parent::viewData(), [
            'options' => $this->options,
        ]);
    }
}
