<?php

declare(strict_types=1);

namespace Tardis\Bread;

use InvalidArgumentException;

/**
 * Supported BREAD field types.
 *
 * Case values MUST stay in sync with FormfieldManager::$registeredTypes
 * so every field type a config file can declare has a renderer.
 */
enum FieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Select = 'select';
    case Toggle = 'toggle';
    case Date = 'date';
    case Datetime = 'datetime';
    case Time = 'time';
    case Textarea = 'textarea';
    case Password = 'password';
    case File = 'file';
    case Checkbox = 'checkbox';
    case Radio = 'radio';
    case Slider = 'slider';
    case Slug = 'slug';
    case Tags = 'tags';
    case Markdown = 'markdown';
    case CodeEditor = 'code_editor';
    case BelongsToMany = 'belongs_to_many';
    case HasMany = 'has_many';

    /**
     * Resolve a field type from its string key, throwing on unknown values.
     *
     * @throws InvalidArgumentException
     */
    public static function fromValue(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new InvalidArgumentException(sprintf('Unsupported BREAD field type [%s].', $value));
    }

    /**
     * Normalize a detected field type to a supported enum value.
     *
     * ModelReflector and other detectors may report legacy/semantic types
     * ('image', 'email', 'simple_array') that have no dedicated renderer;
     * map them onto the closest supported type. Valid types pass through.
     */
    public static function normalize(string $type): string
    {
        return match ($type) {
            'image' => self::File->value,
            'email' => self::Text->value,
            'simple_array' => self::Tags->value,
            default => $type,
        };
    }
}
