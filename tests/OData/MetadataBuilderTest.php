<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Tests\OData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPDev\PhpSpreadsheetOData\OData\EntitySetBuilder;
use WPDev\PhpSpreadsheetOData\OData\MetadataBuilder;
use WPDev\PhpSpreadsheetOData\Tests\Support\SpreadsheetFactory;

#[CoversClass(MetadataBuilder::class)]
final class MetadataBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_valid_edmx_metadata_with_entity_sets_and_types(): void
    {
        $spreadsheet = SpreadsheetFactory::sample();
        $builder = new MetadataBuilder(
            $spreadsheet,
            new EntitySetBuilder($spreadsheet)
        );

        $xml = $builder->build();

        $this->assertStringContainsString('<?xml version="1.0" encoding="utf-8"?>', $xml);
        $this->assertStringContainsString('<edmx:Edmx Version="4.0"', $xml);
        $this->assertStringContainsString('<EntityType Name="Employees">', $xml);
        $this->assertStringContainsString('<Key><PropertyRef Name="RowIndex" /></Key>', $xml);
        $this->assertStringContainsString('<Property Name="RowIndex" Type="Edm.Int32" Nullable="false" />', $xml);
        $this->assertStringContainsString('<Property Name="Id" Type="Edm.Int32" Nullable="true" />', $xml);
        $this->assertStringContainsString('<Property Name="Name" Type="Edm.String" Nullable="true" />', $xml);
        $this->assertStringContainsString('<Property Name="Age" Type="Edm.Int32" Nullable="true" />', $xml);
        $this->assertStringContainsString('EntitySet Name="Employees"', $xml);
        $this->assertStringContainsString('EntityType="WPDev.PhpSpreadsheetOData.Employees"', $xml);
    }
}
