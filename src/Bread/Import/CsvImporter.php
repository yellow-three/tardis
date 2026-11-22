<?php

declare(strict_types=1);

namespace Tardis\Bread\Import;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadQuery;
use Tardis\Bread\BreadSaver;
use Tardis\Bread\Export\CsvExporter;
use Tardis\Bread\FieldValidationRules;
use Tardis\Bread\MissingColumnsException;

/**
 * Reads a CSV file into a BREAD, through the same validation and saving as
 * the create and edit pages (field rules, store()/update() hooks, events).
 *
 * The header row names the columns, by field name or label, so a file the
 * list exported comes back in. A row whose key column holds the key of an
 * existing record (inside the BREAD's scope) updates it; any other row is
 * created. Every row is validated first: one invalid row and nothing is
 * written, and the result lists each problem with its row number. Valid
 * files are written in one transaction.
 *
 * File, image, media, password and translatable fields, and relations other
 * than belongs_to, are not imported: a CSV cell cannot carry them faithfully.
 */
final class CsvImporter
{
    private const SKIPPED_TYPES = ['file', 'image', 'multiple_images', 'media_picker', 'password', 'rich_text', 'markdown'];

    public function __construct(
        private readonly string $slug,
        private readonly BreadDefinition $definition,
    ) {}

    public function import(string $path): ImportResult
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return new ImportResult(errors: [1 => [__('tardis::bread.import_unreadable')]]);
        }

        try {
            [$columns, $ignored, $keyIndex] = $this->header((array) fgetcsv($handle, escape: ''));

            if ($columns === []) {
                return new ImportResult(errors: [1 => [__('tardis::bread.import_no_columns')]], ignoredColumns: $ignored);
            }

            $rows = [];
            $line = 1;
            $limit = max(1, (int) config('tardis.bread.import_limit', 1000));

            while (($cells = fgetcsv($handle, escape: '')) !== false) {
                $line++;

                if ($cells === [null] || array_filter($cells, fn ($cell) => trim((string) $cell) !== '') === []) {
                    continue;
                }

                if (count($rows) >= $limit) {
                    return new ImportResult(errors: [$line => [__('tardis::bread.import_too_many', ['limit' => $limit])]], ignoredColumns: $ignored);
                }

                $rows[$line] = $cells;
            }
        } finally {
            fclose($handle);
        }

        return $this->write($this->plan($rows, $columns, $keyIndex), $ignored);
    }

    /**
     * The fields a CSV may set, keyed by lower-case name and label.
     *
     * @return array<string, array<string, mixed>>
     */
    public function importableFields(): array
    {
        $fields = [];

        foreach ($this->definition->toDisplayArray()['fields'] ?? [] as $field) {
            $name = (string) ($field['name'] ?? '');
            $type = (string) ($field['type'] ?? '');

            if ($name === '' || in_array($type, self::SKIPPED_TYPES, true) || ! empty($field['translatable'])
                || (BreadQuery::showsRelation($field) && $type !== 'belongs_to')
                || (($field['add'] ?? true) === false && ($field['edit'] ?? true) === false)) {
                continue;
            }

            $fields[Str::lower($name)] = $field;

            if (is_string($field['label'] ?? null) && $field['label'] !== '') {
                $fields[Str::lower($field['label'])] ??= $field;
            }
        }

        return $fields;
    }

    /**
     * @param  list<string|null>  $header
     * @return array{0: array<int, array<string, mixed>>, 1: list<string>, 2: ?int}
     */
    private function header(array $header): array
    {
        $fields = $this->importableFields();
        $keyName = Str::lower($this->definition->model ? (new $this->definition->model)->getKeyName() : 'id');
        $columns = [];
        $ignored = [];
        $keyIndex = null;

        foreach ($header as $index => $cell) {
            // The export writes a byte-order mark and may quote a cell.
            $name = Str::lower(trim(ltrim((string) $cell, "\xEF\xBB\xBF'")));

            if ($name === $keyName) {
                $keyIndex = $index;
            } elseif (isset($fields[$name])) {
                $columns[$index] = $fields[$name];
            } elseif ($name !== '') {
                $ignored[] = (string) $cell;
            }
        }

        return [$columns, $ignored, $keyIndex];
    }

    /**
     * Each row as a form, validated, with the record it updates.
     *
     * @param  array<int, list<string|null>>  $rows
     * @param  array<int, array<string, mixed>>  $columns
     * @return array{rows: array<int, array{form: array<string, mixed>, fields: list<array<string, mixed>>, record: ?Model}>, errors: array<int, list<string>>}
     */
    private function plan(array $rows, array $columns, ?int $keyIndex): array
    {
        $planned = [];
        $errors = [];
        $auth = app(BreadAuthorization::class);

        foreach ($rows as $line => $cells) {
            $key = $keyIndex === null ? '' : trim((string) ($cells[$keyIndex] ?? ''));
            $record = $key === '' ? null : $this->definition->query()->find($key);
            $context = $record === null ? 'add' : 'edit';

            if (! $auth->allows($context, $this->slug, $record)) {
                $errors[$line] = [__('tardis::bread.import_not_allowed', ['ability' => $context])];

                continue;
            }

            $fields = [];
            $form = [];

            foreach ($columns as $index => $field) {
                if (($field[$context] ?? true) === false) {
                    continue;
                }

                $value = $this->value((string) ($cells[$index] ?? ''));
                $fields[] = $field;
                $form[(string) $field['name']] = $value;
            }

            $validator = Validator::make(['form' => $form], FieldValidationRules::for($fields, $form, null, $context));

            if ($validator->fails()) {
                $errors[$line] = array_values($validator->errors()->all());

                continue;
            }

            $planned[$line] = ['form' => $form, 'fields' => $fields, 'record' => $record];
        }

        return ['rows' => $planned, 'errors' => $errors];
    }

    /**
     * @param  array{rows: array<int, array{form: array<string, mixed>, fields: list<array<string, mixed>>, record: ?Model}>, errors: array<int, list<string>>}  $plan
     * @param  list<string>  $ignored
     */
    private function write(array $plan, array $ignored): ImportResult
    {
        if ($plan['errors'] !== []) {
            return new ImportResult(errors: $plan['errors'], ignoredColumns: $ignored);
        }

        $saver = app(BreadSaver::class);
        $created = 0;
        $updated = 0;
        $line = 0;

        try {
            DB::transaction(function () use ($plan, $saver, &$created, &$updated, &$line) {
                foreach ($plan['rows'] as $line => $row) {
                    if ($row['record'] === null) {
                        $saver->create($this->slug, (string) $this->definition->model, $row['fields'], $row['form']);
                        $created++;
                    } else {
                        $saver->update($this->slug, $row['record'], $row['fields'], $row['form']);
                        $updated++;
                    }
                }
            });
        } catch (MissingColumnsException $e) {
            return new ImportResult(errors: [$line => [__('tardis::bread.fields_required_by_database', ['fields' => implode(', ', $e->columns)])]], ignoredColumns: $ignored);
        } catch (QueryException|AuthorizationException) {
            // A duplicate inside the file, a constraint the rules do not
            // know about: the whole file is rolled back.
            return new ImportResult(errors: [$line => [__('tardis::bread.import_rejected')]], ignoredColumns: $ignored);
        }

        return new ImportResult($created, $updated, [], $ignored);
    }

    /** An empty cell is null; a quote the export added before a formula is removed. */
    private function value(string $cell): ?string
    {
        $cell = trim($cell);

        if ($cell === '') {
            return null;
        }

        return str_starts_with($cell, "'") && CsvExporter::safe(substr($cell, 1)) !== substr($cell, 1) ? substr($cell, 1) : $cell;
    }
}
