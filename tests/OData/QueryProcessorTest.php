<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Tests\OData;

use PHPUnit\Framework\TestCase;
use WPDev\PhpSpreadsheetOData\OData\QueryProcessor;

/**
 * @covers QueryProcessor
 */
final class QueryProcessorTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $entities;

    protected function setUp(): void
    {
        $this->entities = [
            ['RowIndex' => 1, 'Name' => 'Alice', 'Age' => 30],
            ['RowIndex' => 2, 'Name' => 'Bob', 'Age' => 25],
            ['RowIndex' => 3, 'Name' => 'Charlie', 'Age' => 35],
        ];
    }
    /** @test */
    public function it_limits_results_with_top(): void
    {
        $processor = new QueryProcessor();
        $result = $processor->apply($this->entities, ['$top' => '2']);

        $this->assertCount(2, $result['value']);
        $this->assertNull($result['count']);
    }
    /** @test */
    public function it_skips_results_with_skip(): void
    {
        $processor = new QueryProcessor();
        $result = $processor->apply($this->entities, ['$skip' => '1']);

        $this->assertCount(2, $result['value']);
        $this->assertSame('Bob', $result['value'][0]['Name']);
    }
    /** @test */
    public function it_filters_with_eq_operator(): void
    {
        $processor = new QueryProcessor();
        $result = $processor->apply($this->entities, ['$filter' => "Name eq 'Alice'"]);

        $this->assertCount(1, $result['value']);
        $this->assertSame('Alice', $result['value'][0]['Name']);
    }
    /** @test */
    public function it_filters_with_numeric_comparisons(): void
    {
        $processor = new QueryProcessor();
        $result = $processor->apply($this->entities, ['$filter' => 'Age gt 25']);

        $this->assertCount(2, $result['value']);
        $this->assertSame('Alice', $result['value'][0]['Name']);
        $this->assertSame('Charlie', $result['value'][1]['Name']);
    }
    /** @test */
    public function it_projects_columns_with_select(): void
    {
        $processor = new QueryProcessor();
        $result = $processor->apply($this->entities, ['$select' => 'Name,Age']);

        $this->assertSame(['Name' => 'Alice', 'Age' => 30], $result['value'][0]);
        $this->assertArrayNotHasKey('RowIndex', $result['value'][0]);
    }
    /** @test */
    public function it_returns_count_when_requested(): void
    {
        $processor = new QueryProcessor();
        $result = $processor->apply($this->entities, ['$count' => 'true', '$top' => '1']);

        $this->assertSame(3, $result['count']);
        $this->assertCount(1, $result['value']);
    }
    /** @test */
    public function it_sorts_with_orderby_ascending(): void
    {
        $processor = new QueryProcessor();
        $result = $processor->apply($this->entities, ['$orderby' => 'Name asc']);

        $this->assertSame('Alice', $result['value'][0]['Name']);
        $this->assertSame('Bob', $result['value'][1]['Name']);
        $this->assertSame('Charlie', $result['value'][2]['Name']);
    }
    /** @test */
    public function it_sorts_with_orderby_descending(): void
    {
        $processor = new QueryProcessor();
        $result = $processor->apply($this->entities, ['$orderby' => 'Age desc']);

        $this->assertSame('Charlie', $result['value'][0]['Name']);
        $this->assertSame('Alice', $result['value'][1]['Name']);
        $this->assertSame('Bob', $result['value'][2]['Name']);
    }
    /** @test */
    public function it_filters_when_value_contains_and_keyword(): void
    {
        $entities = [
            ['RowIndex' => 1, 'Name' => 'Alice and Bob'],
            ['RowIndex' => 2, 'Name' => 'Charlie'],
        ];
        $processor = new QueryProcessor();
        $result = $processor->apply($entities, ['$filter' => "Name eq 'Alice and Bob'"]);

        $this->assertCount(1, $result['value']);
        $this->assertSame('Alice and Bob', $result['value'][0]['Name']);
    }
    /** @test */
    public function it_throws_exception_on_invalid_filter_syntax(): void
    {
        $processor = new QueryProcessor();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid $filter query syntax');
        $processor->apply($this->entities, ['$filter' => 'Name eq']);
    }
    /** @test */
    public function it_throws_exception_on_unsupported_or_operator_in_filter(): void
    {
        $processor = new QueryProcessor();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple conditions must be combined with "and"');
        $processor->apply($this->entities, ['$filter' => "Name eq 'Alice' or Age gt 25"]);
    }
    /** @test */
    public function it_throws_exception_on_invalid_top(): void
    {
        $processor = new QueryProcessor();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The $top query option must be a non-negative integer');
        $processor->apply($this->entities, ['$top' => 'abc']);
    }
    /** @test */
    public function it_throws_exception_on_negative_top(): void
    {
        $processor = new QueryProcessor();
        $this->expectException(\InvalidArgumentException::class);
        $processor->apply($this->entities, ['$top' => '-5']);
    }
    /** @test */
    public function it_throws_exception_on_invalid_skip(): void
    {
        $processor = new QueryProcessor();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The $skip query option must be a non-negative integer');
        $processor->apply($this->entities, ['$skip' => 'xyz']);
    }
    /** @test */
    public function it_throws_exception_on_invalid_count(): void
    {
        $processor = new QueryProcessor();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The $count query option must be "true" or "false"');
        $processor->apply($this->entities, ['$count' => 'yes']);
    }
    /** @test */
    public function it_throws_exception_on_invalid_orderby(): void
    {
        $processor = new QueryProcessor();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid $orderby query syntax');
        $processor->apply($this->entities, ['$orderby' => 'Name invalid_dir']);
    }
    /** @test */
    public function it_throws_exception_on_invalid_select(): void
    {
        $processor = new QueryProcessor();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid $select query syntax');
        $processor->apply($this->entities, ['$select' => 'Name,Age!!']);
    }
    /** @test */
    public function it_allows_orderby_without_direction(): void
    {
        $processor = new QueryProcessor();
        $result = $processor->apply($this->entities, ['$orderby' => 'Name']);

        $this->assertSame('Alice', $result['value'][0]['Name']);
        $this->assertSame('Bob', $result['value'][1]['Name']);
        $this->assertSame('Charlie', $result['value'][2]['Name']);
    }
    /** @test */
    public function it_handles_case_insensitive_filter_operators(): void
    {
        $processor = new QueryProcessor();
        $result = $processor->apply($this->entities, ['$filter' => "Name EQ 'Alice'"]);

        $this->assertCount(1, $result['value']);
        $this->assertSame('Alice', $result['value'][0]['Name']);
    }
}