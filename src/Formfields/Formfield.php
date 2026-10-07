<?php

namespace Tardis\Formfields;

use Illuminate\Database\Eloquent\Model;
use Tardis\Assets\Asset;
use Tardis\Classes\Translation;

abstract class Formfield
{
    public string $name;

    /**
     * A plain string, or a locale-keyed map (['en' => 'Title', 'tr' => 'Başlık'])
     * that resolves per locale. Kept raw so a definition survives a save without
     * losing its other languages; resolvedLabel() renders it.
     *
     * @var string|array<string, string>
     */
    public string|array $label;

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

    /** @var array<int|string, string> definition key => property */
    protected array $configurable = [];

    public function __construct(string $name, string|array|null $label = null)
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
     * The label as shown for a locale: a locale map resolved through the
     * field's locales, a translation key translated, a plain string unchanged.
     */
    public function resolvedLabel(?string $locale = null): string
    {
        return Translation::label($this->label, $this->locales ?: null, $locale);
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
     * The value a listing cell shows. The default is the stored value; a type
     * overrides it to decorate (a colour badge, a relation label, a clipped
     * string). The create/edit hook is deliberately separate from browse/read
     * so a decorated value can never be written back.
     */
    public function browse(mixed $value): mixed
    {
        return $value;
    }

    /**
     * The value the read (detail) page shows. See browse().
     */
    public function read(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Prepare a stored value for the edit form. The default hands the value
     * through unchanged; a type may normalise it (a date to its input format).
     */
    public function edit(mixed $value): mixed
    {
        return $value;
    }

    /**
     * The value a create form opens with. The default is the declared default,
     * run through the hook so a type can derive one (today's date, a slug).
     */
    public function add(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Transform a submitted value on create. Delegates to transform() so every
     * existing type keeps its write behaviour; a type may override.
     */
    public function store(mixed $value): mixed
    {
        return $this->transform($value);
    }

    /**
     * Transform a submitted value on update, given the value being replaced.
     * Delegates to store() by default, so an override that only cares about
     * the new value can target store() alone.
     */
    public function update(mixed $value, mixed $old): mixed
    {
        return $this->store($value);
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

    /**
     * Read the type-specific keys of a BREAD definition ($configurable maps a
     * definition key onto a public property, or lists a key that is also the
     * property name).
     */
    public function configure(array $definition): void
    {
        foreach ($this->configurable as $key => $property) {
            $key = is_int($key) ? $property : $key;

            if (! array_key_exists($key, $definition)) {
                continue;
            }

            $value = $definition[$key];
            $current = $this->{$property};

            $this->{$property} = match (true) {
                is_int($current) => (int) $value,
                is_bool($current) => (bool) $value,
                is_array($current) => (array) $value,
                default => $value,
            };
        }
    }

    /**
     * Everything a field view needs. `model` is the Livewire property path the
     * control binds to; a page may override it (and add its own keys) through
     * $context, e.g. one path per locale of a translatable field.
     */
    public function viewData(array $context = []): array
    {
        $model = $context['model'] ?? 'form.'.$this->name;

        return array_merge([
            'field' => $this,
            'name' => $this->name,
            'label' => $this->resolvedLabel(),
            'model' => $model,
            'id' => 'field_'.str_replace('.', '_', $model),
            'helpText' => $this->helpText,
            'placeholder' => $this->placeholder,
            'disabled' => $this->disabled,
            'readonly' => $this->readonly,
            'extraAttributes' => $this->attributes,
            'required' => in_array('required', $this->rules, true),
        ], $this->extraViewData(), $context);
    }

    /**
     * Scripts and styles this type needs. They are written only on a page that
     * renders the field, so a heavy editor costs nothing elsewhere.
     *
     * @return array<int, Asset>
     */
    public function assets(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function extraViewData(): array
    {
        return [];
    }

    abstract public function type(): string;

    abstract public function render(): string;
}
