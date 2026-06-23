<?php

namespace Tardis\Core\Classes;

class Setting
{
    public string $type;

    public ?string $group;

    public string $key;

    public mixed $name;

    public mixed $value;

    public ?string $info;

    public bool $translatable;

    public bool $canBeTranslated;

    public ?string $uuid;

    public array $options;

    public array $validation;

    public function __construct(array $data)
    {
        $this->type = $data['type'] ?? 'text';
        $this->group = $data['group'] ?? null;
        $this->key = $data['key'] ?? '';
        $this->name = $data['name'] ?? $this->key;
        $this->value = $data['value'] ?? null;
        $this->info = $data['info'] ?? null;
        $this->translatable = $data['translatable'] ?? false;
        $this->canBeTranslated = $data['canBeTranslated'] ?? false;
        $this->uuid = $data['uuid'] ?? null;
        $this->options = $data['options'] ?? [];
        $this->validation = $data['validation'] ?? [];
    }

    public function getFullKey(): string
    {
        return $this->group
            ? $this->group.'.'.$this->key
            : $this->key;
    }

    public function displayName(): string
    {
        if (is_array($this->name)) {
            $locale = app()->getLocale();

            return $this->name[$locale] ?? reset($this->name) ?? $this->key;
        }

        return $this->name;
    }

    public function displayValue(): mixed
    {
        if ($this->translatable && is_array($this->value)) {
            $locale = app()->getLocale();

            return $this->value[$locale] ?? reset($this->value) ?? '';
        }

        return $this->value;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'group' => $this->group,
            'key' => $this->key,
            'name' => $this->name,
            'value' => $this->value,
            'info' => $this->info,
            'translatable' => $this->translatable,
            'canBeTranslated' => $this->canBeTranslated,
            'uuid' => $this->uuid,
            'options' => $this->options,
            'validation' => $this->validation,
        ];
    }
}
