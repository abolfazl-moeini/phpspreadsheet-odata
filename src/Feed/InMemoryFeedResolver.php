<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Feed;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use WPDev\PhpSpreadsheetOData\Contracts\FeedResolverInterface;

final class InMemoryFeedResolver implements FeedResolverInterface
{
    /** @var array<string, Spreadsheet> */
    private $feeds;

    /**
     * @param array<string, Spreadsheet> $feeds
     */
    public function __construct(array $feeds)
    {
        $this->feeds = $feeds;
    }

    public function resolve(string $feedId): ?Spreadsheet
    {
        return $this->feeds[$feedId] ?? null;
    }

    /**
     * @return list<string>
     */
    public function listFeedIds(): array
    {
        return array_keys($this->feeds);
    }
}
