<?php

namespace Kilik\TableBundle\Services;

use Kilik\TableBundle\Components\TableInterface;

/**
 * Writes the rows of a table as CSV.
 *
 * @internal
 */
final class CsvWriter
{
    /**
     * Writes a header line before the first row, a line per row, and a total line if the table has total columns.
     *
     * @param resource                       $stream
     * @param iterable<array<string, mixed>> $rows
     * @param array<array<string, mixed>>    $callbackRows rows given to the export callbacks
     */
    public static function write($stream, TableInterface $table, iterable $rows, array $callbackRows, bool $haveTotalColumns): void
    {
        $headerWritten = false;
        foreach ($rows as $row) {
            // first line: keys
            if (!$headerWritten) {
                $headers = array_map(static function ($column) {
                    return $column->getExportName() ?? $column->getName();
                }, $table->getColumns());

                if ($haveTotalColumns) {
                    $headers = array_merge([''], $headers);
                }

                fputcsv($stream, $headers, ';', '"', '\\');
                $headerWritten = true;
            }

            $line = array_map(static function ($column) use ($row, $callbackRows) {
                return $column->getExportValue($row, $callbackRows);
            }, $table->getColumns());

            if ($haveTotalColumns) {
                $line = array_merge([''], $line);
            }

            fputcsv($stream, $line, ';', '"', '\\');
        }

        if ($haveTotalColumns) {
            $total = array_map(static function ($column) {
                return $column->getTotal();
            }, $table->getColumns());

            fputcsv($stream, array_merge(['Total'], $total), ';', '"', '\\');
        }
    }
}
