<?php

namespace Tardis\Manager;

use Tardis\Formfields\Formfield;
use Tardis\Formfields\Types\BelongsToManyField;
use Tardis\Formfields\Types\CheckboxField;
use Tardis\Formfields\Types\CodeEditorField;
use Tardis\Formfields\Types\DateField;
use Tardis\Formfields\Types\DateTimeField;
use Tardis\Formfields\Types\FileField;
use Tardis\Formfields\Types\HasManyField;
use Tardis\Formfields\Types\MarkdownField;
use Tardis\Formfields\Types\NumberField;
use Tardis\Formfields\Types\PasswordField;
use Tardis\Formfields\Types\RadioField;
use Tardis\Formfields\Types\SelectField;
use Tardis\Formfields\Types\SliderField;
use Tardis\Formfields\Types\SlugField;
use Tardis\Formfields\Types\TagsField;
use Tardis\Formfields\Types\TextareaField;
use Tardis\Formfields\Types\TextField;
use Tardis\Formfields\Types\TimeField;
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
            'time' => TimeField::class,
            'textarea' => TextareaField::class,
            'password' => PasswordField::class,
            'file' => FileField::class,
            'checkbox' => CheckboxField::class,
            'radio' => RadioField::class,
            'slider' => SliderField::class,
            'slug' => SlugField::class,
            'tags' => TagsField::class,
            'markdown' => MarkdownField::class,
            'code_editor' => CodeEditorField::class,
            'belongs_to_many' => BelongsToManyField::class,
            'has_many' => HasManyField::class,
        ];
    }

    /**
     * Legacy or semantic names detectors may report, mapped onto the registered
     * type that renders them.
     *
     * @var array<string, string>
     */
    protected array $aliases = [
        'image' => 'file',
        'email' => 'text',
        'simple_array' => 'tags',
    ];

    public function registerType(string $type, string $fieldClass): void
    {
        $this->registeredTypes[$type] = $fieldClass;
    }

    /**
     * Every registered type (name => Formfield class), built-in and host-added.
     * This registry is the single list BREAD definitions are validated against.
     *
     * @return array<string, class-string<Formfield>>
     */
    public function types(): array
    {
        return $this->registeredTypes;
    }

    public function has(string $type): bool
    {
        return isset($this->registeredTypes[$type]);
    }

    public function registerAlias(string $alias, string $type): void
    {
        $this->aliases[$alias] = $type;
    }

    /**
     * Map a detected type name onto a registered one; registered names and
     * unknown names pass through unchanged.
     */
    public function normalize(string $type): string
    {
        if ($this->has($type)) {
            return $type;
        }

        $mapped = $this->aliases[$type] ?? $type;

        return $this->has($mapped) ? $mapped : $type;
    }

    /**
     * @throws \InvalidArgumentException when no renderer is registered for the type
     */
    public function assertRegistered(string $type): void
    {
        if (! $this->has($type)) {
            throw new \InvalidArgumentException(sprintf('Unsupported BREAD field type [%s].', $type));
        }
    }

    public function make(string $type, string $name, ?string $label = null): Formfield
    {
        $class = $this->resolveType($type);

        return new $class($name, $label);
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

            if ($field instanceof BelongsToManyField || $field instanceof HasManyField) {
                if (isset($definition['relation'])) {
                    $field->relation($definition['relation']);
                }

                if (isset($definition['model'])) {
                    $field->model($definition['model']);
                }
            }

            if ($field instanceof BelongsToManyField && isset($definition['label_column'])) {
                $field->labelColumn($definition['label_column']);
            }

            if ($field instanceof FileField) {
                if (isset($definition['mimes'])) {
                    $field->mimes($definition['mimes']);
                }

                if (isset($definition['max_size'])) {
                    $field->maxSize((int) $definition['max_size']);
                }

                if (isset($definition['disk'])) {
                    $field->disk($definition['disk']);
                }

                if (isset($definition['directory'])) {
                    $field->directory($definition['directory']);
                }
            }

            if (isset($definition['translatable'])) {
                $field->translatable((bool) $definition['translatable']);
            }

            if (isset($definition['locales'])) {
                $field->locales((array) $definition['locales']);
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
