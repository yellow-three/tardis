<?php

declare(strict_types=1);

namespace Tardis\Bread\Export;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tardis\Bread\BreadQuery;
use Tardis\Bread\RelatedRecords;
use Tardis\Formfields\Formfield;
use Tardis\Support\Translation;

/**
 * Writes a BREAD listing as CSV: the columns the list shows, the rows the
 * current search, filters and sort select, the values as the list shows them.
 *
 * Rows are read in chunks and streamed, so a large table never sits in memory.
 * Password fields and the model's hidden attributes are never written. A cell
 * that a spreadsheet would read as a formula (=, +, -, @, tab, return) is
 * prefixed with a quote, so opening the file cannot run anything.
 */
final class CsvExporter
{
    private const CHUNK = 500;

    /**
     * @param  list<array<string, mixed>>  $fields  the listing's columns
     * @param  array<string, Formfield>  $formfields  keyed by field name
     */
    public function __construct(
        private readonly array $fields,
        private readonly array $formfields = [],
    ) {}

    public function download(Builder $query, string $filename): StreamedResponse
    {
        $columns = $this->columns($query->getModel());

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'wb');

            // Excel reads a UTF-8 file as its local code page without the BOM.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(fn (array $field) => self::safe($this->label($field)), $columns), escape: '');

            foreach ($query->lazy(self::CHUNK) as $row) {
                fputcsv($out, array_map(fn (array $field) => self::safe($this->cell($row, $field)), $columns), escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The listing's columns minus anything secret.
     *
     * @return list<array<string, mixed>>
     */
    public function columns(Model $model): array
    {
        $hidden = $model->getHidden();

        return array_values(array_filter($this->fields, function (array $field) use ($hidden) {
            $name = (string) ($field['name'] ?? '');

            return $name !== ''
                && ($field['type'] ?? '') !== 'password'
                && ! in_array($name, $hidden, true)
                && ! in_array((string) ($field['accessor'] ?? ''), $hidden, true);
        }));
    }

    /** @param  array<string, mixed>  $field */
    public function cell(Model $row, array $field): string
    {
        $name = (string) ($field['name'] ?? '');

        if (BreadQuery::showsRelation($field)) {
            return $this->relationLabels($row, $field);
        }

        $value = data_get($row, $field['accessor'] ?? $name);

        if (! empty($field['translatable'])) {
            return (string) Translation::value($value, $field['locales'] ?? null);
        }

        if (isset($this->formfields[$name])) {
            $value = $this->formfields[$name]->browse($value);
        }

        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }

    /**
     * A cell a spreadsheet would treat as a formula gets a leading quote
     * (OWASP "CSV injection").
     */
    public static function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /** @param  array<string, mixed>  $field */
    public function label(array $field): string
    {
        $label = $field['label'] ?? $field['display_name'] ?? null;

        return is_string($label) && $label !== '' ? $label : Str::headline((string) ($field['name'] ?? ''));
    }

    /** @param  array<string, mixed>  $field */
    private function relationLabels(Model $row, array $field): string
    {
        $relation = (string) ($field['relation'] ?? $field['name'] ?? '');

        if ($relation === '' || ! method_exists($row, $relation)) {
            return '';
        }

        $related = RelatedRecords::visible(
            $row,
            $relation,
            isset($field['model']) ? (string) $field['model'] : null
        );
        $labelColumn = (string) ($field['label_column'] ?? 'name');

        return $related->map(fn ($item) => (string) (data_get($item, $labelColumn) ?? data_get($item, 'name') ?? data_get($item, 'id') ?? ''))
            ->filter(fn (string $label) => $label !== '')
            ->implode('; ');
    }
}
