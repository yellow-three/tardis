<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class SelectField extends Formfield
{
    public array $options = [];

    public function options(array $options = []): self|array
    {
        if (func_num_args() === 0) {
            return $this->options;
        }

        $this->options = $options;

        return $this;
    }

    public function type(): string
    {
        return 'select';
    }

    public function render(): string
    {
        return 'tardis::formfields.select';
    }

    public function viewData(): array
    {
        return array_merge(parent::viewData(), [
            'options' => $this->options,
        ]);
    }
}
