<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\OData;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use WPDev\PhpSpreadsheetOData\Support\Str;
use WPDev\PhpSpreadsheetOData\Support\WorksheetCells;

final class EntitySetBuilder
{
    private Spreadsheet $spreadsheet;

    public function __construct(Spreadsheet $spreadsheet)
    {
        $this->spreadsheet = $spreadsheet;
    }

    public static function normalizeIdentifier(string $name): string
    {
        $normalized = (string) preg_replace('/[^A-Za-z0-9_]/', '_', $name);

        if (preg_match('/^[0-9]/', $normalized)) {
            $normalized = '_' . $normalized;
        }

        $normalized = (string) preg_replace('/__+/', '_', $normalized);

        $trimmed = trim($normalized, '_');
        if ($trimmed === '') {
            return 'Identifier_' . substr(md5($name), 0, 8);
        }

        if (preg_match('/^[0-9]/', $trimmed)) {
            $trimmed = '_' . $trimmed;
        }

        return $trimmed;
    }

    /**
     * @return list<string>
     */
    public function getEntitySetNames(): array
    {
        $names = [];

        foreach ($this->spreadsheet->getWorksheetIterator() as $worksheet) {
            $names[] = self::normalizeIdentifier($worksheet->getTitle());
        }

        return $names;
    }

    public function hasEntitySet(string $name): bool
    {
        return $this->findWorksheet($name) !== null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function build(string $sheetName): array
    {
        $worksheet = $this->requireWorksheet($sheetName);
        $headerMap = $this->extractHeaderMap($worksheet);

        if ($headerMap === []) {
            return [];
        }

        $entities = [];
        $highestRow = $worksheet->getHighestDataRow();

        for ($row = 2; $row <= $highestRow; ++$row) {
            $entities[] = $this->buildEntityFromRow($worksheet, $headerMap, $row);
        }

        return $entities;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByKey(string $sheetName, int $key): ?array
    {
        if ($key < 1) {
            return null;
        }

        $worksheet = $this->findWorksheet($sheetName);
        if ($worksheet === null) {
            return null;
        }

        $headerMap = $this->extractHeaderMap($worksheet);
        if ($headerMap === []) {
            return null;
        }

        $row = $key + 1;
        if ($row > $worksheet->getHighestDataRow()) {
            return null;
        }

        return $this->buildEntityFromRow($worksheet, $headerMap, $row);
    }

    /**
     * @return list<string>
     */
    public function getPropertyNames(string $sheetName): array
    {
        return $this->extractHeaders($this->requireWorksheet($sheetName));
    }

    private function findWorksheet(string $name): ?Worksheet
    {
        foreach ($this->spreadsheet->getWorksheetIterator() as $worksheet) {
            $title = $worksheet->getTitle();
            if (
                strcasecmp($title, $name) === 0 ||
                strcasecmp(self::normalizeIdentifier($title), $name) === 0
            ) {
                return $worksheet;
            }
        }

        return null;
    }

    private function requireWorksheet(string $name): Worksheet
    {
        $worksheet = $this->findWorksheet($name);

        if ($worksheet === null) {
            throw new \InvalidArgumentException(sprintf('Worksheet "%s" was not found.', $name));
        }

        return $worksheet;
    }

    /**
     * @return list<string>
     */
    private function extractHeaders(Worksheet $worksheet): array
    {
        return array_values($this->extractHeaderMap($worksheet));
    }

    /**
     * @return array<int, string>
     */
    private function extractHeaderMap(Worksheet $worksheet): array
    {
        $map = [];
        $highestColumn = $worksheet->getHighestDataColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        $usedHeaders = [];

        for ($column = 1; $column <= $highestColumnIndex; ++$column) {
            $value = WorksheetCells::getCell($worksheet, $column, 1)->getValue();

            if ($value === null || $value === '') {
                continue;
            }

            $header = Str::toString($value);
            if ($header !== '') {
                $normalized = self::normalizeIdentifier($header);
                $uniqueNormalized = $normalized;
                $counter = 2;
                while (in_array(strtolower($uniqueNormalized), $usedHeaders, true)) {
                    $uniqueNormalized = $normalized . '_' . $counter;
                    ++$counter;
                }
                $usedHeaders[] = strtolower($uniqueNormalized);
                $map[$column] = $uniqueNormalized;
            }
        }

        return $map;
    }

    /**
     * @param array<int, string> $headerMap
     * @return array<string, mixed>
     */
    private function buildEntityFromRow(Worksheet $worksheet, array $headerMap, int $row): array
    {
        $entity = ['RowIndex' => $row - 1];

        foreach ($headerMap as $col => $header) {
            $cell = WorksheetCells::getCell($worksheet, $col, $row);
            try {
                $value = $cell->getCalculatedValue();
            } catch (\Throwable $e) {
                $value = $cell->getValue();
            }
            $entity[$header] = $this->normalizeValue($value);
        }

        return $entity;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function normalizeValue($value)
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return Str::toString($value);
    }
}
