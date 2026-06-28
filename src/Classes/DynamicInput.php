<?php

declare(strict_types=1);

namespace Tardis\Classes;

use Illuminate\Support\Collection;

class DynamicInput
{
    /** @var Collection<int, array{key: string, value: mixed, label?: string, type?: string}> */
    protected Collection $fields;

    public function __construct(array $fields = [])
    {
        $this->fields = collect($fields);
    }

    /**
     * Add a dynamic input field row.
     */
    public function addField(string $key, mixed $value = null, ?string $label = null, string $type = 'text'): self
    {
        $this->fields->push([
            'key' => $key,
            'value' => $value,
            'label' => $label ?? $key,
            'type' => $type,
        ]);

        return $this;
    }

    /**
     * Remove a field by key.
     */
    public function removeField(string $key): self
    {
        $this->fields = $this->fields->filter(
            fn (array $field) => $field['key'] !== $key
        )->values();

        return $this;
    }

    /**
     * Reorder fields by an array of keys.
     */
    public function reorderFields(array $keys): self
    {
        $ordered = collect();

        foreach ($keys as $key) {
            $field = $this->fields->firstWhere('key', $key);
            if ($field) {
                $ordered->push($field);
            }
        }

        // Append any remaining fields not in the order list
        $remaining = $this->fields->reject(
            fn (array $field) => in_array($field['key'], $keys)
        );

        $this->fields = $ordered->merge($remaining)->values();

        return $this;
    }

    /**
     * Get all fields.
     */
    public function getFields(): Collection
    {
        return $this->fields;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return $this->fields->toArray();
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Create from JSON string.
     */
    public static function fromJson(string $json): self
    {
        return new self(json_decode($json, true) ?? []);
    }
}
