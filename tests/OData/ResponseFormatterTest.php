<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Tests\OData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPDev\PhpSpreadsheetOData\OData\ResponseFormatter;

#[CoversClass(ResponseFormatter::class)]
final class ResponseFormatterTest extends TestCase
{
    #[Test]
    public function it_formats_entity_collection_with_odata_context_and_value(): void
    {
        $formatter = new ResponseFormatter('http://localhost/odata');
        $payload = $formatter->formatCollection(
            'Employees',
            [
                ['RowIndex' => 1, 'Name' => 'Alice'],
            ],
            null
        );

        /** @var array{value: list<array<string, mixed>>, '@odata.context': string} $decoded */
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('http://localhost/odata/$metadata#Employees', $decoded['@odata.context']);
        $this->assertSame('Alice', $decoded['value'][0]['Name']);
    }

    #[Test]
    public function it_includes_count_in_collection_when_provided(): void
    {
        $formatter = new ResponseFormatter('http://localhost/odata');
        $payload = $formatter->formatCollection('Employees', [], 5);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(5, $decoded['@odata.count']);
    }

    #[Test]
    public function it_formats_single_entity_with_context(): void
    {
        $formatter = new ResponseFormatter('http://localhost/odata');
        $payload = $formatter->formatEntity('Employees', ['RowIndex' => 1, 'Name' => 'Alice']);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('http://localhost/odata/$metadata#Employees/$entity', $decoded['@odata.context']);
        $this->assertSame('Alice', $decoded['Name']);
        $this->assertArrayNotHasKey('value', $decoded);
    }

    #[Test]
    public function it_formats_service_document(): void
    {
        $formatter = new ResponseFormatter('http://localhost/odata');
        $payload = $formatter->formatServiceDocument(['Employees', 'Products']);

        /** @var array{value: list<array<string, mixed>>, '@odata.context': string} $decoded */
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('http://localhost/odata/$metadata', $decoded['@odata.context']);
        $this->assertSame('Employees', $decoded['value'][0]['name']);
        $this->assertSame('Products', $decoded['value'][1]['name']);
        $this->assertSame('EntitySet', $decoded['value'][0]['kind']);
    }
}
