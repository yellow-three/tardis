<?php

namespace Tardis\Bread;

class BreadDefinition
{
    public function __construct(
        public string $slug,
        public string $model,
        public string $name,
        public string $namePlural,
        public array $fields = [],
        public array $relationships = [],
        public array $layout = ['browse' => [], 'edit' => [], 'read' => []],
        public array $actions = [],
        public array $validation = [],
        public ?string $icon = null,
        public ?string $description = null,
        public bool $softDelete = false,
        public bool $serverSide = false,
        public ?string $orderColumn = null,
        public string $orderDirection = 'asc',
        public ?string $searchKey = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            slug: $data['slug'] ?? '',
            model: $data['model'] ?? '',
            name: $data['name'] ?? '',
            namePlural: $data['name_plural'] ?? $data['name'] ?? '',
            fields: $data['fields'] ?? [],
            relationships: $data['relationships'] ?? [],
            layout: $data['layout'] ?? ['browse' => [], 'edit' => [], 'read' => []],
            actions: $data['actions'] ?? [],
            validation: $data['validation'] ?? [],
            icon: $data['icon'] ?? null,
            description: $data['description'] ?? null,
            softDelete: $data['soft_delete'] ?? false,
            serverSide: $data['server_side'] ?? false,
            orderColumn: $data['order_column'] ?? null,
            orderDirection: $data['order_direction'] ?? 'asc',
            searchKey: $data['search_key'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'model' => $this->model,
            'name' => $this->name,
            'name_plural' => $this->namePlural,
            'fields' => $this->fields,
            'relationships' => $this->relationships,
            'layout' => $this->layout,
            'actions' => $this->actions,
            'validation' => $this->validation,
            'icon' => $this->icon,
            'description' => $this->description,
            'soft_delete' => $this->softDelete,
            'server_side' => $this->serverSide,
            'order_column' => $this->orderColumn,
            'order_direction' => $this->orderDirection,
            'search_key' => $this->searchKey,
        ];
    }

    public function getField(string $name): ?array
    {
        return $this->fields[$name] ?? null;
    }

    public function getBrowseFields(): array
    {
        return array_filter($this->fields, fn ($field) => $field['browse'] ?? true);
    }

    public function getEditFields(): array
    {
        return array_filter($this->fields, fn ($field) => $field['edit'] ?? true);
    }

    public function getReadFields(): array
    {
        return array_filter($this->fields, fn ($field) => $field['read'] ?? true);
    }
}
