<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Support;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class WorksheetCells
{
    /**
     * @param mixed $value
     */
    public static function setValue(Worksheet $worksheet, int $column, int $row, $value): void
    {
        if (method_exists($worksheet, 'setCellValueByColumnAndRow')) {
            $worksheet->setCellValueByColumnAndRow($column, $row, $value);

            return;
        }

        $worksheet->setCellValue([$column, $row], $value);
    }

    public static function getCell(Worksheet $worksheet, int $column, int $row): Cell
    {
        if (method_exists($worksheet, 'getCellByColumnAndRow')) {
            return $worksheet->getCellByColumnAndRow($column, $row);
        }

        return $worksheet->getCell([$column, $row]);
    }
}