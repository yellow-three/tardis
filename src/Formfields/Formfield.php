<?php

namespace Tardis\Formfields;

abstract class Formfield
{
    public string $name;

    public string $label;

    public mixed $default = null;

    public array $rules = [];

    public array $attributes = [];

    public bool $disabled = false;

    public bool $readonly = false;

    public ?string $helpText = null;

    public ?string $placeholder = null;

    public ?string $wrapperClass = null;

    public int $width = 12;

    public function __construct(string $name, ?string $label = null)
    {
        $this->name = $name;
        $this->label = $label ?? $name;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;

        return $this;
    }

    public function rules(array|string $rules): self
    {
        $this->rules = is_string($rules) ? explode('|', $rules) : $rules;

        return $this;
    }

    public function attributes(array $attrs): self
    {
        $this->attributes = $attrs;

        return $this;
    }

    public function disabled(bool $disabled = true): self
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function readonly(bool $readonly = true): self
    {
        $this->readonly = $readonly;

        return $this;
    }

    public function help(string $text): self
    {
        $this->helpText = $text;

        return $this;
    }

    public function placeholder(string $text): self
    {
        $this->placeholder = $text;

        return $this;
    }

    public function wrapperClass(string $class): self
    {
        $this->wrapperClass = $class;

        return $this;
    }

    public function width(int $cols): self
    {
        $this->width = $cols;

        return $this;
    }

    public function viewData(): array
    {
        return [
            'field' => $this,
            'name' => $this->name,
            'label' => $this->label,
            'value' => old($this->name, $this->default),
            'error' => $this->name,
            'helpText' => $this->helpText,
            'placeholder' => $this->placeholder,
            'disabled' => $this->disabled,
            'readonly' => $this->readonly,
            'attributes' => $this->attributes,
            'required' => in_array('required', $this->rules),
        ];
    }

    abstract public function type(): string;

    abstract public function render(): string;
}
