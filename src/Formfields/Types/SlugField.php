<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class SlugField extends Formfield
{
    protected array $configurable = ['from'];

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

    protected function extraViewData(): array
    {
        return [
            'from' => $this->from,
        ];
    }
}
