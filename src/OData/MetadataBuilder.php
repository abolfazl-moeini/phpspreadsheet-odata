<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\OData;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

final class MetadataBuilder
{
    private const NAMESPACE_NAME = 'WPDev.PhpSpreadsheetOData';

    /** @var Spreadsheet */
    private $spreadsheet;

    /** @var EntitySetBuilder */
    private $entitySetBuilder;

    public function __construct(Spreadsheet $spreadsheet, EntitySetBuilder $entitySetBuilder)
    {
        $this->spreadsheet = $spreadsheet;
        $this->entitySetBuilder = $entitySetBuilder;
    }

    public function build(): string
    {
        $entityTypes = '';

        foreach ($this->spreadsheet->getWorksheetIterator() as $worksheet) {
            $sheetName = $worksheet->getTitle();
            $normalizedName = EntitySetBuilder::normalizeIdentifier($sheetName);
            $properties = $this->entitySetBuilder->getPropertyNames($sheetName);

            $entityTypes .= $this->buildEntityTypeXml($normalizedName, $properties);
        }

        $entitySets = '';

        foreach ($this->spreadsheet->getWorksheetIterator() as $worksheet) {
            $sheetName = $worksheet->getTitle();
            $normalizedName = EntitySetBuilder::normalizeIdentifier($sheetName);
            $entitySets .= sprintf(
                '<EntitySet Name="%s" EntityType="%s.%s" />',
                $this->escapeXml($normalizedName),
                self::NAMESPACE_NAME,
                $this->escapeXml($normalizedName)
            );
        }

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<edmx:Edmx Version="4.0" xmlns:edmx="http://docs.oasis-open.org/odata/ns/edmx">
  <edmx:DataServices>
    <Schema Namespace="{$this->escapeXml(self::NAMESPACE_NAME)}" xmlns="http://docs.oasis-open.org/odata/ns/edm">
      {$entityTypes}
      <EntityContainer Name="Container">
        {$entitySets}
      </EntityContainer>
    </Schema>
  </edmx:DataServices>
</edmx:Edmx>
XML;
    }

    /**
     * @param list<string> $properties
     */
    private function buildEntityTypeXml(string $sheetName, array $properties): string
    {
        $propertyXml = '<Property Name="RowIndex" Type="Edm.Int32" Nullable="false" />';

        $sample = $this->getFirstDataRowSample($sheetName);

        foreach ($properties as $property) {
            $type = $this->inferEdmType($sample[$property] ?? null);
            $propertyXml .= sprintf(
                '<Property Name="%s" Type="%s" Nullable="true" />',
                $this->escapeXml($property),
                $type
            );
        }

        return sprintf(
            '<EntityType Name="%s"><Key><PropertyRef Name="RowIndex" /></Key>%s</EntityType>',
            $this->escapeXml($sheetName),
            $propertyXml
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getFirstDataRowSample(string $sheetName): array
    {
        try {
            $entities = $this->entitySetBuilder->build($sheetName);

            return $entities[0] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param mixed $value
     */
    private function inferEdmType($value): string
    {
        if ($value === null) {
            return 'Edm.String';
        }
        if (is_bool($value)) {
            return 'Edm.Boolean';
        }
        if (is_int($value)) {
            return 'Edm.Int32';
        }
        if (is_float($value)) {
            return 'Edm.Double';
        }
        if ($value instanceof \DateTimeInterface || (is_string($value) && $this->looksLikeIsoDate($value))) {
            return 'Edm.DateTimeOffset';
        }

        return 'Edm.String';
    }

    private function looksLikeIsoDate(string $s): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/', $s);
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}