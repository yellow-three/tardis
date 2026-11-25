<?php

declare(strict_types=1);

namespace Tardis\Bread\Export;

use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Excel (.xlsx) export and import for BREAD lists, on top of the CSV ones:
 * the same columns, rows, rules and authorization. Needs the optional
 * openspout/openspout package; without it only CSV is offered.
 */
final class Xlsx
{
    private const CHUNK = 500;

    public static function available(): bool
    {
        return (bool) config('tardis.bread.xlsx', true) && class_exists(Writer::class) && class_exists(Reader::class);
    }

    /**
     * Write the listing as a workbook: cells are stored as text, so nothing
     * in them is ever read as a formula.
     */
    public static function download(CsvExporter $exporter, Builder $query, string $filename): BinaryFileResponse
    {
        $columns = $exporter->columns($query->getModel());
        $path = (string) tempnam(sys_get_temp_dir(), 'tardis-xlsx-');

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_map(fn (array $field) => $exporter->label($field), $columns)));

        foreach ($query->lazy(self::CHUNK) as $row) {
            $writer->addRow(Row::fromValues(array_map(fn (array $field) => $exporter->cell($row, $field), $columns)));
        }

        $writer->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * The first sheet as a CSV file, for CsvImporter. The caller deletes it.
     */
    public static function toCsv(string $xlsxPath): string
    {
        $csv = (string) tempnam(sys_get_temp_dir(), 'tardis-xlsx-csv-');
        $out = fopen($csv, 'wb');

        $reader = new Reader;
        $reader->open($xlsxPath);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    fputcsv($out, array_map(fn ($value) => self::text($value), $row->toArray()), escape: '');
                }

                break;
            }
        } finally {
            $reader->close();
            fclose($out);
        }

        return $csv;
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof \DateTimeInterface => $value->format($value->format('H:i:s') === '00:00:00' ? 'Y-m-d' : 'Y-m-d H:i:s'),
            $value instanceof \DateInterval => $value->format('%H:%I:%S'),
            is_bool($value) => $value ? '1' : '0',
            is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX => (string) (int) $value,
            default => (string) $value,
        };
    }
}
