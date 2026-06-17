<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Tests\OData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPDev\PhpSpreadsheetOData\OData\EntitySetBuilder;
use WPDev\PhpSpreadsheetOData\Tests\Support\SpreadsheetFactory;

#[CoversClass(EntitySetBuilder::class)]
final class EntitySetBuilderTest extends TestCase
{
    #[Test]
    public function it_maps_worksheet_rows_to_entities_with_row_index_key(): void
    {
        $spreadsheet = SpreadsheetFactory::sample();
        $builder = new EntitySetBuilder($spreadsheet);

        $entities = $builder->build('Employees');

        $this->assertCount(3, $entities);
        $this->assertSame([
            'RowIndex' => 1,
            'Id' => 1,
            'Name' => 'Alice',
            'Age' => 30,
        ], $entities[0]);
        $this->assertSame([
            'RowIndex' => 2,
            'Id' => 2,
            'Name' => 'Bob',
            'Age' => 25,
        ], $entities[1]);
    }

    #[Test]
    public function it_returns_single_entity_by_row_index(): void
    {
        $spreadsheet = SpreadsheetFactory::sample();
        $builder = new EntitySetBuilder($spreadsheet);

        $entity = $builder->findByKey('Employees', 2);

        $this->assertNotNull($entity);
        $this->assertSame('Bob', $entity['Name']);
        $this->assertSame(2, $entity['RowIndex']);
    }

    #[Test]
    public function it_returns_null_for_unknown_row_index(): void
    {
        $spreadsheet = SpreadsheetFactory::sample();
        $builder = new EntitySetBuilder($spreadsheet);

        $this->assertNull($builder->findByKey('Employees', 99));
    }

    #[Test]
    public function it_handles_empty_header_columns_without_data_misalignment(): void
    {
        $spreadsheet = SpreadsheetFactory::fromRows([
            ['Id', '', 'Name'],
            [1, 'X', 'Alice'],
            [2, '', 'Bob'],
        ]);
        $builder = new EntitySetBuilder($spreadsheet);
        $entities = $builder->build('Employees');

        $this->assertSame('Alice', $entities[0]['Name']);
        $this->assertSame('Bob', $entities[1]['Name']);
        $this->assertArrayNotHasKey('', $entities[0]);
    }

    #[Test]
    public function it_returns_calculated_values_for_formulas(): void
    {
        $spreadsheet = SpreadsheetFactory::fromRows([
            ['A', 'B', 'Total'],
            [10, 20],
        ]);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('C2', '=A2+B2');

        $builder = new EntitySetBuilder($spreadsheet);
        $entities = $builder->build('Employees');

        $this->assertSame(30, $entities[0]['Total']);
    }

    #[Test]
    public function it_normalizes_property_names_and_handles_collisions(): void
    {
        $spreadsheet = SpreadsheetFactory::fromRows([
            ['First Name', 'First Name', '2026 Age!'],
            ['Alice', 'Bob', 30],
        ]);
        $builder = new EntitySetBuilder($spreadsheet);
        $entities = $builder->build('Employees');

        $this->assertCount(1, $entities);
        $this->assertSame('Alice', $entities[0]['First_Name']);
        $this->assertSame('Bob', $entities[0]['First_Name_2']);
        $this->assertSame(30, $entities[0]['_2026_Age']);
    }

    #[Test]
    public function it_normalizes_identifiers_via_static_helper(): void
    {
        $this->assertSame('Hello_World', EntitySetBuilder::normalizeIdentifier('Hello World'));
        $this->assertSame('_123_Test', EntitySetBuilder::normalizeIdentifier('123 Test'));
        $this->assertSame('SimpleName', EntitySetBuilder::normalizeIdentifier('SimpleName'));
        $this->assertSame('A_B_C', EntitySetBuilder::normalizeIdentifier('A!!B__C'));
        $this->assertSame('Identifier_d41d8cd9', substr(EntitySetBuilder::normalizeIdentifier(''), 0, 19));
    }
}
