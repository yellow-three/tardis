<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class DateTimeField extends Formfield
{
    protected array $configurable = ['with_time' => 'withTime'];

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

    protected function extraViewData(): array
    {
        return [
            'withTime' => $this->withTime,
        ];
    }
}
