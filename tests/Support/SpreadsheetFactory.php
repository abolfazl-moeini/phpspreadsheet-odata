<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use WPDev\PhpSpreadsheetOData\Support\WorksheetCells;

final class SpreadsheetFactory
{
    /**
     * @param list<list<mixed>> $rows
     */
    public static function fromRows(array $rows, string $sheetName = 'Employees'): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);

        $rowIndex = 1;
        foreach ($rows as $row) {
            $columnIndex = 1;
            foreach ($row as $value) {
                WorksheetCells::setValue($sheet, $columnIndex, $rowIndex, $value);
                ++$columnIndex;
            }
            ++$rowIndex;
        }

        return $spreadsheet;
    }

    public static function sample(): Spreadsheet
    {
        return self::fromRows([
            ['Id', 'Name', 'Age'],
            [1, 'Alice', 30],
            [2, 'Bob', 25],
            [3, 'Charlie', 35],
        ]);
    }
}