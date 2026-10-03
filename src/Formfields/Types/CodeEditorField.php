<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class CodeEditorField extends Formfield
{
    protected array $configurable = ['language'];

    public string $language = 'php';

    public function language(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function type(): string
    {
        return 'code_editor';
    }

    public function render(): string
    {
        return 'tardis::formfields.code-editor';
    }

    protected function extraViewData(): array
    {
        return [
            'language' => $this->language,
        ];
    }
}
