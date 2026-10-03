<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class TagsField extends Formfield
{
    protected array $configurable = ['suggestions'];

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

    protected function extraViewData(): array
    {
        return [
            'suggestions' => $this->suggestions,
        ];
    }
}
