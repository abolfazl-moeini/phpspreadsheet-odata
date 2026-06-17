<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\OData;

final class ResponseFormatter
{
    private string $serviceRoot;

    public function __construct(string $serviceRoot)
    {
        $this->serviceRoot = $serviceRoot;
    }

    /**
     * @param list<array<string, mixed>> $entities
     */
    public function formatCollection(string $entitySetName, array $entities, ?int $count): string
    {
        $payload = [
            '@odata.context' => $this->serviceRoot . '/$metadata#' . $entitySetName,
            'value' => $entities,
        ];

        if ($count !== null) {
            $payload['@odata.count'] = $count;
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $entity
     */
    public function formatEntity(string $entitySetName, array $entity): string
    {
        $payload = [
            '@odata.context' => $this->serviceRoot . '/$metadata#' . $entitySetName . '/$entity',
        ] + $entity;

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $entitySetNames
     */
    public function formatServiceDocument(array $entitySetNames): string
    {
        $payload = [
            '@odata.context' => $this->serviceRoot . '/$metadata',
            'value' => array_map(
                static function (string $name): array {
                    return [
                        'name' => $name,
                        'kind' => 'EntitySet',
                        'url' => $name,
                    ];
                },
                $entitySetNames
            ),
        ];

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $feedIds
     */
    public function formatFeedServiceDocument(array $feedIds): string
    {
        $payload = [
            'value' => array_map(
                static function (string $feedId): array {
                    return [
                        'name' => $feedId,
                        'kind' => 'Feed',
                        'url' => $feedId,
                    ];
                },
                $feedIds
            ),
        ];

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
