<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class TagsField extends Formfield
{
    public array $suggestions = [];

    public function suggestions(array $suggestions): self
    {
        $this->suggestions = $suggestions;

        return $this;
    }

    public function type(): string
    {
        return 'tags';
    }

    public function render(): string
    {
        return 'tardis::formfields.tags';
    }

    public function viewData(): array
    {
        return array_merge(parent::viewData(), [
            'suggestions' => $this->suggestions,
        ]);
    }
}
