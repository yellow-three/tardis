<?php

namespace Tardis\Core\Bread;

class BreadLayout
{
    public function __construct(
        public string $slug,
        public string $name,
        public array $columns = [],
        public array $tabs = [],
        public ?string $icon = null,
        public ?string $description = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            slug: $data['slug'] ?? '',
            name: $data['name'] ?? $data['slug'] ?? '',
            columns: $data['columns'] ?? [],
            tabs: $data['tabs'] ?? [],
            icon: $data['icon'] ?? null,
            description: $data['description'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'columns' => $this->columns,
            'tabs' => $this->tabs,
            'icon' => $this->icon,
            'description' => $this->description,
        ];
    }
}
