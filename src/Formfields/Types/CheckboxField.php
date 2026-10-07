<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class CheckboxField extends Formfield
{
    protected array $configurable = ['options'];

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

    protected function extraViewData(): array
    {
        return [
            'options' => $this->options,
        ];
    }
}
