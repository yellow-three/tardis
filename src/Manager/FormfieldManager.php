<?php

namespace Tardis\Manager;

use Tardis\Formfields\Formfield;
use Tardis\Formfields\Types\CheckboxField;
use Tardis\Formfields\Types\DateField;
use Tardis\Formfields\Types\DateTimeField;
use Tardis\Formfields\Types\FileField;
use Tardis\Formfields\Types\NumberField;
use Tardis\Formfields\Types\PasswordField;
use Tardis\Formfields\Types\RadioField;
use Tardis\Formfields\Types\SelectField;
use Tardis\Formfields\Types\SliderField;
use Tardis\Formfields\Types\SlugField;
use Tardis\Formfields\Types\TagsField;
use Tardis\Formfields\Types\TextareaField;
use Tardis\Formfields\Types\TextField;
use Tardis\Formfields\Types\ToggleField;

class FormfieldManager
{
    protected array $fields = [];

    protected array $registeredTypes = [];

    public function __construct()
    {
        $this->registeredTypes = [
            'text' => TextField::class,
            'number' => NumberField::class,
            'select' => SelectField::class,
            'toggle' => ToggleField::class,
            'date' => DateField::class,
            'datetime' => DateTimeField::class,
            'textarea' => TextareaField::class,
            'password' => PasswordField::class,
            'file' => FileField::class,
            'checkbox' => CheckboxField::class,
            'radio' => RadioField::class,
            'slider' => SliderField::class,
            'slug' => SlugField::class,
            'tags' => TagsField::class,
        ];
    }

    public function registerType(string $type, string $fieldClass): void
    {
        $this->registeredTypes[$type] = $fieldClass;
    }

    public function make(string $type, string $name, ?string $label = null): Formfield
    {
        $class = $this->resolveType($type);

        return new $class($name, $label);
    }

    public function field(string $name, mixed $value = null): array
    {
        return [];
    }

    public function fields(array $definitions): array
    {
        $fields = [];

        foreach ($definitions as $definition) {
            $type = $definition['type'];
            $name = $definition['name'];
            $label = $definition['label'] ?? null;

            $field = $this->make($type, $name, $label);

            if (isset($definition['rules'])) {
                $field->rules($definition['rules']);
            }

            if (isset($definition['default'])) {
                $field->default($definition['default']);
            }

            if (isset($definition['placeholder'])) {
                $field->placeholder($definition['placeholder']);
            }

            if (isset($definition['help'])) {
                $field->help($definition['help']);
            }

            if (isset($definition['width'])) {
                $field->width($definition['width']);
            }

            if (isset($definition['options']) && $field instanceof SelectField) {
                $field->options($definition['options']);
            }

            $fields[] = $field;
        }

        return $fields;
    }

    public function resolveType(string $type): ?string
    {
        return $this->registeredTypes[$type] ?? null;
    }
}
