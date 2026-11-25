<?php

declare(strict_types=1);

namespace Tardis\Database;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ModelGenerator
{
    /** @var array<int, string> Columns managed by Eloquent conventions (excluded from fillable and casts). */
    public const MANAGED_COLUMNS = ['id', 'created_at', 'updated_at'];

    /** @var list<string> Columns skipped in the last generate() because their name is not an identifier. */
    protected array $warnings = [];

    /**
     * Generate an Eloquent model file for an existing table.
     *
     * Table, namespace, class and column names end up as PHP source, so only
     * identifiers are accepted. A database may hold any UTF-8 string as a
     * column name; those columns are skipped, with a warning, rather than
     * interpolated into the file.
     *
     * @param  array<int, array<string, mixed>>  $columns  Schema::getColumns() shaped column definitions.
     * @param  array{namespace?: string, path?: string, force?: bool}  $options
     *
     * @throws InvalidArgumentException When a name or the output path is unsafe.
     * @throws RuntimeException When the model already exists and force is not set.
     */
    public function generate(string $table, array $columns, array $options = []): string
    {
        $this->warnings = [];

        $namespace = (string) ($options['namespace'] ?? 'App\Models');
        $force = $options['force'] ?? false;

        if (! $this->isQualifiedIdentifier($namespace)) {
            throw new InvalidArgumentException('The model namespace is not a valid PHP namespace.');
        }

        if (! $this->isIdentifier($table)) {
            throw new InvalidArgumentException('The table name is not a valid identifier.');
        }

        $className = Str::studly(Str::singular($table));

        if (preg_match('/^[A-Z][A-Za-z0-9_]*$/D', $className) !== 1) {
            throw new InvalidArgumentException('The model class name is not a valid identifier.');
        }

        $file = $this->modelPath($table, $options);

        if (File::exists($file) && ! $force) {
            throw new RuntimeException("Model [{$className}] already exists.");
        }

        $columns = $this->validColumns($columns);
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

        File::ensureDirectoryExists(dirname($file));
        File::put($file, $content);

        return $file;
    }

    /**
     * Names skipped in the last generate() call, for callers that want to
     * tell the user a database column could not be represented in PHP.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Check whether a generated model file already exists for the given table.
     *
     * @param  array{namespace?: string, path?: string, force?: bool}  $options
     */
    public function modelExists(string $table, array $options = []): bool
    {
        return File::exists($this->modelPath($table, $options));
    }

    /**
     * @param  array{namespace?: string, path?: string, force?: bool}  $options
     */
    protected function modelPath(string $table, array $options = []): string
    {
        $path = (string) ($options['path'] ?? app_path('Models'));
        $className = Str::studly(Str::singular($table));

        return $this->insideBasePath($path).'/'.$className.'.php';
    }

    /**
     * Resolve a user-supplied output directory without allowing `..`, a
     * symlink or an absolute path to write outside the application. The path
     * may not exist yet, so the check uses the real path of its closest
     * existing parent and keeps the remaining segments relative to it.
     */
    protected function insideBasePath(string $path): string
    {
        $base = realpath(base_path()) ?: base_path();
        $candidate = str_replace('\\', '/', $path);

        if (! str_starts_with($candidate, '/')) {
            $candidate = str_replace('\\', '/', base_path()).'/'.$candidate;
        }

        $segments = [];

        foreach (explode('/', $candidate) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $candidate = '/'.implode('/', $segments);
        $parent = $candidate;

        while (! is_dir($parent) && dirname($parent) !== $parent) {
            $parent = dirname($parent);
        }

        $real = realpath($parent);

        if ($real === false || ! ($real === $base || str_starts_with($real, rtrim($base, '/').'/'))) {
            throw new InvalidArgumentException('The model path must be inside the application.');
        }

        $suffix = substr($candidate, strlen($parent));

        return $suffix === '' ? $real : $real.$suffix;
    }

    protected function isIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $value) === 1;
    }

    protected function isQualifiedIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $value) === 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, array<string, mixed>>
     */
    protected function validColumns(array $columns): array
    {
        $valid = [];

        foreach ($columns as $column) {
            $name = (string) ($column['name'] ?? '');

            if (! $this->isIdentifier($name)) {
                $message = 'Skipped a database column whose name is not a valid identifier.';
                $this->warnings[] = $message;
                Log::warning($message, ['column' => $name]);

                continue;
            }

            $valid[] = $column;
        }

        return $valid;
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
        $attributes = '#[Table('.var_export($table, true).$this->primaryKeyArgument($columns).')]';

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

        $argument = ', key: '.var_export($primary['name'], true);
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
                $fillable[] = var_export($name, true).',';
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
                $casts[] = '        '.var_export($name, true).' => '.var_export($cast, true).',';
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
