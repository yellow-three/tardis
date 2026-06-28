<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class SliderField extends Formfield
{
    public int $min = 0;

    public int $max = 100;

    public int $step = 1;

    public function min(int $min): self
    {
        $this->min = $min;

        return $this;
    }

    public function max(int $max): self
    {
        $this->max = $max;

        return $this;
    }

    public function step(int $step): self
    {
        $this->step = $step;

        return $this;
    }

    public function type(): string
    {
        return 'slider';
    }

    public function render(): string
    {
        return 'tardis::formfields.slider';
    }

    public function viewData(): array
    {
        return array_merge(parent::viewData(), [
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step,
        ]);
    }
}
