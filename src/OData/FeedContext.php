<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\OData;

final class FeedContext
{
    public function __construct(
        public readonly EntitySetBuilder $entitySetBuilder,
        public readonly MetadataBuilder $metadataBuilder,
        public readonly ResponseFormatter $responseFormatter
    ) {
    }
}
