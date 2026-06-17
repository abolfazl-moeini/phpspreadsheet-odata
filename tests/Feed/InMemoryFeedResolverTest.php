<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Tests\Feed;

use PHPUnit\Framework\TestCase;
use WPDev\PhpSpreadsheetOData\Feed\InMemoryFeedResolver;
use WPDev\PhpSpreadsheetOData\Tests\Support\SpreadsheetFactory;

/**
 * @covers InMemoryFeedResolver
 */
final class InMemoryFeedResolverTest extends TestCase
{
    /** @test */
    public function it_resolves_known_feed_id(): void
    {
        $spreadsheet = SpreadsheetFactory::sample();
        $resolver = new InMemoryFeedResolver(['tenant-a' => $spreadsheet]);

        $this->assertSame($spreadsheet, $resolver->resolve('tenant-a'));
    }
    /** @test */
    public function it_returns_null_for_unknown_feed_id(): void
    {
        $resolver = new InMemoryFeedResolver(['tenant-a' => SpreadsheetFactory::sample()]);

        $this->assertNull($resolver->resolve('missing'));
    }
    /** @test */
    public function it_lists_registered_feed_ids(): void
    {
        $resolver = new InMemoryFeedResolver([
            'tenant-a' => SpreadsheetFactory::sample(),
            'tenant-b' => SpreadsheetFactory::fromRows([
                ['Name'],
                ['Zoe'],
            ], 'People'),
        ]);

        $this->assertSame(['tenant-a', 'tenant-b'], $resolver->listFeedIds());
    }
}