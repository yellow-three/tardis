<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class DateTimeField extends Formfield
{
    public bool $withTime = true;

    public function withTime(bool $withTime = true): self
    {
        $this->withTime = $withTime;

        return $this;
    }

    public function type(): string
    {
        return 'datetime';
    }

    public function render(): string
    {
        return 'tardis::formfields.datetime';
    }

    public function viewData(): array
    {
        return array_merge(parent::viewData(), [
            'withTime' => $this->withTime,
        ]);
    }
}
