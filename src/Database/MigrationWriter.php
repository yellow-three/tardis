<?php

declare(strict_types=1);

namespace Tardis\Database;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * Turns a change made on the Database screen into a migration file, so the
 * same change reaches every other environment through `php artisan migrate`
 * instead of living only in the database it was clicked into
 * (tardis.database.migrations).
 *
 * Column specs are the editor's rows: name, type, length, nullable, default
 * and primary. Values are exported with var_export, so nothing typed into the
 * editor ends up as code.
 */
class MigrationWriter
{
    public const MODE_MIGRATE = 'migrate';

    public const MODE_WRITE = 'write';

    public const MODE_OFF = 'off';

    public function mode(): string
    {
        $mode = (string) config('tardis.database.migrations.mode', self::MODE_MIGRATE);

        return in_array($mode, [self::MODE_MIGRATE, self::MODE_WRITE, self::MODE_OFF], true) ? $mode : self::MODE_MIGRATE;
    }

    public function enabled(): bool
    {
        return $this->mode() !== self::MODE_OFF;
    }

    public function directory(): string
    {
        return rtrim((string) (config('tardis.database.migrations.path') ?: database_path('migrations')), '/\\');
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    public function createTable(string $table, array $columns, bool $id = true, bool $timestamps = true): string
    {
        $lines = [];

        if ($id) {
            $lines[] = '$table->id();';
        }

        foreach ($columns as $column) {
            $lines[] = $this->columnLine($column, ! empty($column['primary'])).';';
        }

        if ($timestamps) {
            $lines[] = '$table->timestamps();';
        }

        return $this->write("create_{$table}_table", $this->schemaCall('create', $table, $lines), $this->dropTableCall($table));
    }

    /** @param  array<string, mixed>  $column */
    public function addColumn(string $table, array $column): string
    {
        return $this->write(
            "add_{$column['name']}_to_{$table}_table",
            $this->schemaCall('table', $table, [$this->columnLine($column).';']),
            $this->schemaCall('table', $table, ['$table->dropColumn('.$this->export($column['name']).');']),
        );
    }

    /**
     * @param  array<string, mixed>  $column  the new definition (its name may differ)
     * @param  array<string, mixed>|null  $previous  the old definition, when it changes
     */
    public function modifyColumn(string $table, string $original, array $column, ?array $previous): string
    {
        $up = [];
        $down = [];
        $name = (string) $column['name'];

        if ($name !== $original) {
            $up[] = $this->schemaCall('table', $table, ['$table->renameColumn('.$this->export($original).', '.$this->export($name).');']);
        }

        if ($previous !== null) {
            $up[] = $this->schemaCall('table', $table, [$this->columnLine($column).'->change();']);
            $down[] = $this->schemaCall('table', $table, [$this->columnLine(['name' => $name] + $previous).'->change();']);
        }

        if ($name !== $original) {
            $down[] = $this->schemaCall('table', $table, ['$table->renameColumn('.$this->export($name).', '.$this->export($original).');']);
        }

        return $this->write("change_{$original}_on_{$table}_table", implode("\n\n", $up), implode("\n\n", $down));
    }

    /** @param  array<string, mixed>|null  $previous  the dropped column, re-added on rollback when known */
    public function dropColumn(string $table, string $column, ?array $previous): string
    {
        return $this->write(
            "drop_{$column}_from_{$table}_table",
            $this->schemaCall('table', $table, ['$table->dropColumn('.$this->export($column).');']),
            $previous === null
                ? '// The dropped column is not known well enough to re-add it.'
                : $this->schemaCall('table', $table, [$this->columnLine(['name' => $column, 'nullable' => true] + $previous).';']),
        );
    }

    public function dropTable(string $table): string
    {
        return $this->write(
            "drop_{$table}_table",
            $this->dropTableCall($table),
            '// A dropped table and its rows cannot be restored from this migration.',
        );
    }

    /**
     * Run one migration file the way `php artisan migrate` would, so it is
     * recorded in the migrations table and never runs twice.
     */
    public function run(string $file): void
    {
        $exit = Artisan::call('migrate', ['--path' => $file, '--realpath' => true, '--force' => true]);

        if ($exit !== 0) {
            throw new \RuntimeException(trim(Artisan::output()) ?: 'The migration did not run.');
        }
    }

    /** @param  array<string, mixed>  $column */
    protected function columnLine(array $column, bool $primary = false): string
    {
        $type = (string) $column['type'];
        $name = $this->export($column['name']);
        $length = trim((string) ($column['length'] ?? ''));

        if ($type === 'decimal' && preg_match('/^(\d+)\s*,\s*(\d+)$/', $length, $parts) === 1) {
            $line = "\$table->decimal({$name}, {$parts[1]}, {$parts[2]})";
        } elseif (in_array($type, ['string', 'integer', 'bigInteger', 'float'], true) && ctype_digit($length)) {
            $line = "\$table->{$type}({$name}, {$length})";
        } else {
            $line = "\$table->{$type}({$name})";
        }

        if ($primary) {
            $line .= '->primary()';
        }

        if (! empty($column['nullable'])) {
            $line .= '->nullable()';
        }

        if (($column['default'] ?? '') !== '') {
            $line .= '->default('.($column['default'] === 'null' ? 'null' : $this->export((string) $column['default'])).')';
        }

        return $line;
    }

    /** @param  array<int, string>  $lines */
    protected function schemaCall(string $method, string $table, array $lines): string
    {
        $body = implode("\n", array_map(fn (string $line) => '            '.$line, $lines));

        return "        Schema::{$method}({$this->export($table)}, function (Blueprint \$table) {\n{$body}\n        });";
    }

    protected function dropTableCall(string $table): string
    {
        return "        Schema::dropIfExists({$this->export($table)});";
    }

    protected function export(mixed $value): string
    {
        return var_export($value, true);
    }

    /**
     * Migrations run in file name order. Two changes in the same second
     * would sort by name ("add_…" before "create_…") and break on a fresh
     * database, so every new file gets a stamp after the newest one there.
     */
    protected function nextStamp(string $directory): string
    {
        $stamp = now()->startOfSecond();

        foreach (glob($directory.'/*.php') ?: [] as $existing) {
            if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_/', basename($existing), $match) === 1) {
                $last = Carbon::createFromFormat('Y_m_d_His', $match[1]);

                if ($last !== null && $last->greaterThanOrEqualTo($stamp)) {
                    $stamp = $last->copy()->addSecond();
                }
            }
        }

        return $stamp->format('Y_m_d_His');
    }

    /**
     * @return string the full path of the written file
     */
    protected function write(string $name, string $up, string $down): string
    {
        $directory = $this->directory();

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Could not create the migrations directory [{$directory}].");
        }

        $slug = Str::of($name)->lower()->replaceMatches('/[^a-z0-9_]+/', '_')->limit(120, '')->toString();
        $file = "{$directory}/{$this->nextStamp($directory)}_{$slug}.php";

        $down = str_starts_with(ltrim($down), '//') ? '        '.ltrim($down) : $down;

        $contents = <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

/**
 * Written by the Tardis Database screen. Commit it so other environments get
 * the same change with `php artisan migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
{$up}
    }

    public function down(): void
    {
{$down}
    }
};

PHP;

        if (file_put_contents($file, $contents) === false) {
            throw new \RuntimeException("Could not write the migration [{$file}].");
        }

        return $file;
    }
}
