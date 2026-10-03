<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class SelectField extends Formfield
{
    protected array $configurable = ['options'];

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

    protected function extraViewData(): array
    {
        return [
            'options' => $this->options,
        ];
    }
}
