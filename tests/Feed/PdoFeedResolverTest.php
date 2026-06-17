<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Tests\Feed;

use PHPUnit\Framework\TestCase;
use WPDev\PhpSpreadsheetOData\Feed\PdoFeedResolver;
use WPDev\PhpSpreadsheetOData\Tests\Support\SpreadsheetFactory;

/**
 * @covers PdoFeedResolver
 */
final class PdoFeedResolverTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        PdoFeedResolver::createTable($this->pdo);
    }
    /** @test */
    public function it_resolves_spreadsheet_from_database_mapping(): void
    {
        $spreadsheet = SpreadsheetFactory::fromRows([
            ['Name'],
            ['FromDb'],
        ], 'People');

        $sources = ['people-source' => $spreadsheet];

        $insert = $this->pdo->prepare('INSERT INTO odata_feeds (feed_id, source_ref) VALUES (?, ?)');
        $insert->execute(['tenant-db', 'people-source']);

        $resolver = new PdoFeedResolver(
            $this->pdo,
            'odata_feeds',
            fn (string $sourceRef): ?\PhpOffice\PhpSpreadsheet\Spreadsheet => $sources[$sourceRef] ?? null,
        );

        $resolved = $resolver->resolve('tenant-db');

        $this->assertSame($spreadsheet, $resolved);
    }
    /** @test */
    public function it_returns_null_when_feed_id_is_not_in_database(): void
    {
        $resolver = new PdoFeedResolver(
            $this->pdo,
            'odata_feeds',
            fn (string $sourceRef): ?\PhpOffice\PhpSpreadsheet\Spreadsheet => null,
        );

        $this->assertNull($resolver->resolve('missing'));
    }

    /** @test */
    public function it_lists_feed_ids_from_database(): void
    {
        $insert = $this->pdo->prepare('INSERT INTO odata_feeds (feed_id, source_ref) VALUES (?, ?)');
        $insert->execute(['feed-1', 'ref-a']);
        $insert->execute(['feed-2', 'ref-b']);

        $resolver = new PdoFeedResolver(
            $this->pdo,
            'odata_feeds',
            fn (string $sourceRef): ?\PhpOffice\PhpSpreadsheet\Spreadsheet => null,
        );

        $this->assertSame(['feed-1', 'feed-2'], $resolver->listFeedIds());
    }
}