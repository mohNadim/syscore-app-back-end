<?php

namespace App\Services\Reports;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportSpreadsheet
{
    public const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * Columns whose raw values are translated through `admin_reports.values`.
     *
     * @var list<string>
     */
    protected const TRANSLATED_COLUMNS = ['method', 'target', 'status'];

    /**
     * Stream the sheets as an Excel download; headers and sheet names follow the request locale.
     *
     * Each sheet is `['name' => string, 'columns' => list<string>, 'rows' => list<list<scalar|null>>]`.
     *
     * @param  list<array{name: string, columns: list<string>, rows: list<list<int|float|string|null>>}>  $sheets
     */
    public function download(string $filename, array $sheets): StreamedResponse
    {
        return response()->streamDownload(function () use ($sheets): void {
            $writer = new Writer;
            $writer->openToFile('php://output');

            try {
                foreach (array_values($sheets) as $index => $sheet) {
                    $target = $index === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
                    $target->setName(__('admin_reports.sheets.'.$sheet['name']));

                    $writer->addRow(Row::fromValues(array_map(fn (string $column): string => __('admin_reports.columns.'.$column), $sheet['columns'])));

                    foreach ($sheet['rows'] as $values) {
                        $writer->addRow(Row::fromValues($this->translate($sheet['columns'], $values)));
                    }
                }
            } finally {
                $writer->close();
            }
        }, $filename, ['Content-Type' => self::CONTENT_TYPE]);
    }

    /**
     * @param  list<string>  $columns
     * @param  list<int|float|string|null>  $values
     * @return list<int|float|string|null>
     */
    protected function translate(array $columns, array $values): array
    {
        foreach ($columns as $index => $column) {
            if (in_array($column, self::TRANSLATED_COLUMNS, true) && is_string($values[$index] ?? null) && trans()->has('admin_reports.values.'.$values[$index])) {
                $values[$index] = __('admin_reports.values.'.$values[$index]);
            }
        }

        return $values;
    }
}
