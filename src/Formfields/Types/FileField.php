<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class FileField extends Formfield
{
    public array $mimes = [];

    public int $maxSize = 0;

    public function mimes(array $mimes): self
    {
        $this->mimes = $mimes;

        return $this;
    }

    public function maxSize(int $kb): self
    {
        $this->maxSize = $kb;

        return $this;
    }

    public function type(): string
    {
        return 'file';
    }

    public function render(): string
    {
        return 'tardis::formfields.file';
    }

    public function viewData(): array
    {
        return array_merge(parent::viewData(), [
            'mimes' => $this->mimes,
            'maxSize' => $this->maxSize,
        ]);
    }
}
