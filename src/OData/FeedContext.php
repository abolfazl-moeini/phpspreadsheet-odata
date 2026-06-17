<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\OData;

final class FeedContext
{
    /** @var EntitySetBuilder */
    public $entitySetBuilder;

    /** @var MetadataBuilder */
    public $metadataBuilder;

    /** @var ResponseFormatter */
    public $responseFormatter;

    public function __construct(
        EntitySetBuilder $entitySetBuilder,
        MetadataBuilder $metadataBuilder,
        ResponseFormatter $responseFormatter
    ) {
        $this->entitySetBuilder = $entitySetBuilder;
        $this->metadataBuilder = $metadataBuilder;
        $this->responseFormatter = $responseFormatter;
    }
}