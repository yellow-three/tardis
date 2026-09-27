<?php

namespace Tardis\Formfields;

use Illuminate\Database\Eloquent\Model;
use Tardis\Classes\Translation;

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

    public bool $translatable = false;

    public array $locales = [];

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

    public function translatable(bool $translatable = true): self
    {
        $this->translatable = $translatable;

        return $this;
    }

    public function locales(array $locales): self
    {
        $this->locales = array_values(array_filter(array_unique(array_map('strval', $locales))));

        return $this;
    }

    /**
     * The locales this field renders and persists, resolving field-level
     * overrides, then the global tardis.locales config, then app locale.
     */
    public function resolvedLocales(): array
    {
        return Translation::locales($this->locales);
    }

    /**
     * Transform the raw form value before it is persisted to the model.
     */
    public function transform(mixed $value): mixed
    {
        if (! $this->translatable) {
            return $value;
        }

        $normalized = Translation::normalize($value, $this->resolvedLocales());

        if (implode('', $normalized) === '') {
            return null;
        }

        return $normalized;
    }

    /**
     * Whether blank values (null/empty string) should be skipped entirely
     * instead of being transformed and persisted (e.g. password fields).
     */
    public function skipWhenBlank(): bool
    {
        return false;
    }

    /**
     * Whether this field represents a relationship (BelongsToMany, HasMany, ...)
     * that must not be written to a model column.
     */
    public function isRelation(): bool
    {
        return false;
    }

    public function stored(mixed $value, Model $model): void
    {
        //
    }

    public function updated(mixed $value, Model $model): void
    {
        $this->stored($value, $model);
    }

    public function viewData(): array
    {
        return [
            'field' => $this,
            'name' => $this->name,
            'label' => $this->label,
            'value' => old($this->name, $this->default),
            'error' => $errors ?? null,
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
