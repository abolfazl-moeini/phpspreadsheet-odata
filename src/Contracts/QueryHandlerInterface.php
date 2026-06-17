<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Contracts;

interface QueryHandlerInterface
{
    /**
     * @param list<array<string, mixed>> $entities
     * @param array<string, string> $queryParams
     * @return array{value: list<array<string, mixed>>, count: int|null}
     */
    public function apply(array $entities, array $queryParams): array;
}