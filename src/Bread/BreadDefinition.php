<?php

namespace Tardis\Bread;

use Illuminate\Database\Eloquent\Builder;
use Tardis\Classes\Translation;

class BreadDefinition
{
    public function __construct(
        public string $slug,
        public string $model,
        public string|array $name,
        public string|array $namePlural,
        public array $fields = [],
        public array $relationships = [],
        public array $layout = ['browse' => [], 'edit' => [], 'read' => [], 'list' => [], 'view' => [], 'field_order' => [], 'widths' => [], 'legends' => []],
        public array $actions = [],
        public array $validation = [],
        public ?string $icon = null,
        public string|array|null $description = null,
        public bool $softDelete = false,
        public ?string $orderColumn = null,
        public string $orderDirection = 'asc',
        public ?string $searchKey = null,
        /** @var array<string, string> action (browse|add|edit|read) => Livewire component replacing the stock page */
        public array $components = [],
        /** Ability prefix; defaults to the slug ("browse {policy}") */
        public ?string $policy = null,
        /** Model query scope applied to listings and record lookups */
        public ?string $scope = null,
    ) {}

    /**
     * The word abilities are built from ("browse posts"): the policy when the
     * definition sets one, otherwise the slug.
     */
    public function permissionKey(): string
    {
        return $this->policy !== null && $this->policy !== '' ? $this->policy : $this->slug;
    }

    /**
     * The singular label for the active locale.
     *
     * A definition may store a plain string or a locale map; only the locale
     * map is translated, so a plain name comes back verbatim.
     */
    public function resolvedName(?string $locale = null): string
    {
        return Translation::label($this->name, null, $locale);
    }

    /**
     * The plural label for the active locale, falling back to the singular.
     */
    public function resolvedNamePlural(?string $locale = null): string
    {
        return Translation::label(
            $this->namePlural === [] ? $this->name : $this->namePlural,
            null,
            $locale
        );
    }

    /**
     * The description for the active locale.
     */
    public function resolvedDescription(?string $locale = null): ?string
    {
        if ($this->description === null || $this->description === '') {
            return null;
        }

        return Translation::label($this->description, null, $locale);
    }

    /**
     * The definition with its labels resolved for display.
     *
     * Runtime pages consume this so `ucfirst()` and Blade interpolation keep
     * receiving plain strings, while `toArray()` stays raw and keeps the
     * locale maps intact for the JSON save round-trip.
     *
     * @return array<string, mixed>
     */
    public function toDisplayArray(): array
    {
        return array_merge($this->toArray(), [
            'name' => $this->resolvedName(),
            'name_plural' => $this->resolvedNamePlural(),
            'description' => $this->resolvedDescription(),
        ]);
    }

    public static function fromArray(array $data): self
    {
        $layout = $data['layout'] ?? ['browse' => [], 'edit' => [], 'read' => []];
        // Normalize to support named layouts (list/view) while preserving backward compat
        if (! isset($layout['list'])) {
            $layout['list'] = $layout['browse'] ?? [];
        }
        if (! isset($layout['view'])) {
            $layout['view'] = $layout['read'] ?? [];
        }
        if (! isset($layout['field_order'])) {
            $layout['field_order'] = [];
        }
        if (! isset($layout['browse'])) {
            $layout['browse'] = $layout['list'] ?? [];
        }
        if (! isset($layout['read'])) {
            $layout['read'] = $layout['view'] ?? [];
        }
        if (! isset($layout['edit'])) {
            $layout['edit'] = $layout['edit'] ?? [];
        }
        if (! isset($layout['widths'])) {
            $layout['widths'] = [];
        }
        if (! isset($layout['legends'])) {
            $layout['legends'] = [];
        }

        return new self(
            slug: $data['slug'] ?? '',
            model: $data['model'] ?? '',
            name: $data['name'] ?? '',
            namePlural: $data['name_plural'] ?? $data['name'] ?? '',
            fields: $data['fields'] ?? [],
            relationships: $data['relationships'] ?? [],
            layout: $layout,
            actions: $data['actions'] ?? [],
            validation: $data['validation'] ?? [],
            icon: $data['icon'] ?? null,
            description: $data['description'] ?? null,
            softDelete: $data['soft_delete'] ?? false,
            orderColumn: $data['order_column'] ?? null,
            orderDirection: $data['order_direction'] ?? 'asc',
            searchKey: $data['search_key'] ?? null,
            components: array_filter((array) ($data['components'] ?? []), fn ($component) => is_string($component) && $component !== ''),
            policy: $data['policy'] ?? null,
            scope: $data['scope'] ?? null,
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
            'order_column' => $this->orderColumn,
            'order_direction' => $this->orderDirection,
            'search_key' => $this->searchKey,
            'components' => $this->components,
            'policy' => $this->policy,
            'scope' => $this->scope,
        ];
    }

    /**
     * A query on the BREAD's model with the definition's scope applied. Listings
     * and every record lookup go through it, so a record the scope hides can
     * neither be opened, edited nor deleted by guessing its id.
     */
    public function query(): Builder
    {
        $query = ($this->model)::query();

        if ($this->scope !== null && $this->scope !== '') {
            $query->scopes([$this->scope]);
        }

        return $query;
    }

    /**
     * Inject a field immediately after an existing one. A plugin uses this to
     * extend a BREAD it does not own: the definition is spliced in place, so
     * the new field survives a toArray()/fromArray() round-trip and renders
     * wherever the anchor field does.
     *
     * @param  array<string, mixed>  $options  the field definition; a "name" is required
     */
    public function addAfterFormField(string $afterField, string $type, array $options = []): self
    {
        $options['type'] = $type;

        if (! isset($options['name']) || $options['name'] === '') {
            throw new \InvalidArgumentException('addAfterFormField() needs a field name in $options.');
        }

        $this->fields = static::insertAfter($this->fields, $afterField, $options);

        return $this;
    }

    /**
     * Splice a field into the list after the named anchor. A definition may key
     * its fields by name or hold a plain list; the new field keeps that shape,
     * and an anchor that does not exist appends rather than drops the field.
     *
     * @param  array<int|string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $new
     * @return array<int|string, array<string, mixed>>
     */
    protected static function insertAfter(array $fields, string $afterField, array $new): array
    {
        $result = [];
        $inserted = false;

        foreach ($fields as $key => $field) {
            $result[$key] = $field;

            if (! $inserted && is_array($field) && ($field['name'] ?? null) === $afterField) {
                if (is_string($key)) {
                    $result[$new['name']] = $new;
                } else {
                    $result[] = $new;
                }

                $inserted = true;
            }
        }

        if (! $inserted) {
            $result[] = $new;
        }

        return $result;
    }

    public function getListLayout(): array
    {
        return $this->layout['list'] ?? $this->layout['browse'] ?? [];
    }

    public function getViewLayout(): array
    {
        return $this->layout['view'] ?? $this->layout['read'] ?? [];
    }

    public function getFieldOrder(): array
    {
        return $this->layout['field_order'] ?? [];
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
