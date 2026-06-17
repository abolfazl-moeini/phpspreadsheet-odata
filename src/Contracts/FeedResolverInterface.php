<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Contracts;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

interface FeedResolverInterface
{
    public function resolve(string $feedId): ?Spreadsheet;

    /**
     * @return list<string>
     */
    public function listFeedIds(): array;
}
