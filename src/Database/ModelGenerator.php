<?php

declare(strict_types=1);

namespace Tardis\Database;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class ModelGenerator
{
    /** @var array<int, string> Columns managed by Eloquent conventions (excluded from fillable and casts). */
    public const MANAGED_COLUMNS = ['id', 'created_at', 'updated_at'];

    /**
     * Generate an Eloquent model file for an existing table.
     *
     * @param  array<int, array<string, mixed>>  $columns  Schema::getColumns() shaped column definitions.
     * @param  array{namespace?: string, path?: string, force?: bool}  $options
     *
     * @throws RuntimeException When the model already exists and force is not set.
     */
    public function generate(string $table, array $columns, array $options = []): string
    {
        $namespace = $options['namespace'] ?? 'App\Models';
        $path = $options['path'] ?? app_path('Models');
        $force = $options['force'] ?? false;

        $className = Str::studly(Str::singular($table));
        $file = $path.'/'.$className.'.php';

        if (File::exists($file) && ! $force) {
            throw new RuntimeException("Model [{$className}] already exists.");
        }

        $withTimestamps = $this->hasTimestamps($columns);

        $replacements = [
            '{{NAMESPACE}}' => $namespace,
            '{{IMPORTS}}' => $this->buildImports($withTimestamps),
            '{{ATTRIBUTES}}' => $this->buildAttributes($table, $columns, $withTimestamps),
            '{{CLASS_NAME}}' => $className,
            '{{FILLABLE}}' => $this->buildFillable($columns),
            '{{CASTS_BLOCK}}' => $this->buildCasts($columns),
        ];

        $content = File::get(__DIR__.'/../../stubs/model.stub');
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        File::ensureDirectoryExists($path);
        File::put($file, $content);

        return $file;
    }

    protected function buildImports(bool $withTimestamps): string
    {
        return $withTimestamps
            ? ''
            : 'use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;';
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    protected function buildAttributes(string $table, array $columns, bool $withTimestamps): string
    {
        $attributes = "#[Table('{$table}'{$this->primaryKeyArgument($columns)})]";

        if (! $withTimestamps) {
            $attributes .= "\n#[WithoutTimestamps]";
        }

        return $attributes."\n";
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    protected function primaryKeyArgument(array $columns): string
    {
        $primary = $this->primaryKeyColumn($columns);

        if ($primary === null || $primary['name'] === 'id') {
            return '';
        }

        $argument = ", key: '{$primary['name']}'";
        $type = strtolower((string) ($primary['type'] ?? ''));

        if (Str::contains($type, ['char', 'text', 'uuid', 'guid', 'json'])) {
            $argument .= ", keyType: 'string', incrementing: false";
        }

        return $argument;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, mixed>|null
     */
    protected function primaryKeyColumn(array $columns): ?array
    {
        foreach ($columns as $column) {
            if (($column['primary'] ?? 0) > 0) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    protected function hasTimestamps(array $columns): bool
    {
        $names = array_column($columns, 'name');

        return in_array('created_at', $names, true) && in_array('updated_at', $names, true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    protected function buildFillable(array $columns): string
    {
        $fillable = [];

        foreach ($columns as $column) {
            $name = (string) $column['name'];

            if (! in_array($name, self::MANAGED_COLUMNS, true)) {
                $fillable[] = "'{$name}',";
            }
        }

        return implode("\n        ", $fillable);
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    protected function buildCasts(array $columns): string
    {
        $casts = [];

        foreach ($columns as $column) {
            $name = (string) $column['name'];

            if (in_array($name, self::MANAGED_COLUMNS, true)) {
                continue;
            }

            $cast = $this->castForType(strtolower((string) ($column['type'] ?? '')));

            if ($cast !== null) {
                $casts[] = "        '{$name}' => '{$cast}',";
            }
        }

        if ($casts === []) {
            return '';
        }

        return "\n\n    /** @var array<string, string> */\n    protected \$casts = [\n".implode("\n", $casts)."\n    ];\n";
    }

    protected function castForType(string $type): ?string
    {
        if (str_contains($type, 'bool')) {
            return 'boolean';
        }

        if (str_contains($type, 'int')) {
            return 'integer';
        }

        if (in_array($type, ['decimal', 'numeric', 'money'], true)) {
            return 'decimal:2';
        }

        if (Str::contains($type, ['float', 'double', 'real'])) {
            return 'float';
        }

        if (str_contains($type, 'json')) {
            return 'array';
        }

        if (str_contains($type, 'datetime') || str_contains($type, 'timestamp')) {
            return 'datetime';
        }

        if (str_contains($type, 'date')) {
            return 'date';
        }

        return null;
    }
}
