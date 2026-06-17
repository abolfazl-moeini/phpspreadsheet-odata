<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Http;

use WPDev\PhpSpreadsheetOData\Support\Str;

final class Router
{
    public const ROUTE_SERVICE_DOCUMENT = 'service_document';
    public const ROUTE_METADATA = 'metadata';
    public const ROUTE_COLLECTION = 'collection';
    public const ROUTE_ENTITY = 'entity';
    public const ROUTE_NOT_FOUND = 'not_found';

    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * @return array{type: string, feedId: string|null, entitySet: string|null, key: int|null}
     */
    public function match(string $requestPath): array
    {
        $relativePath = $this->resolveRelativePath($requestPath);

        if ($relativePath === '' || $relativePath === '/') {
            return [
                'type' => self::ROUTE_SERVICE_DOCUMENT,
                'feedId' => null,
                'entitySet' => null,
                'key' => null,
            ];
        }

        if ($relativePath === '/$metadata') {
            return [
                'type' => self::ROUTE_METADATA,
                'feedId' => null,
                'entitySet' => null,
                'key' => null,
            ];
        }

        if (preg_match('#^/([A-Za-z0-9_-]+)/\$metadata$#', $relativePath, $matches)) {
            return [
                'type' => self::ROUTE_METADATA,
                'feedId' => $matches[1],
                'entitySet' => null,
                'key' => null,
            ];
        }

        if (preg_match('#^/([A-Za-z0-9_-]+)/(.+)\((\d+)\)$#', $relativePath, $matches)) {
            return [
                'type' => self::ROUTE_ENTITY,
                'feedId' => $matches[1],
                'entitySet' => rawurldecode($matches[2]),
                'key' => (int) $matches[3],
            ];
        }

        if (preg_match('#^/([A-Za-z0-9_-]+)/(.+)$#', $relativePath, $matches)) {
            return [
                'type' => self::ROUTE_COLLECTION,
                'feedId' => $matches[1],
                'entitySet' => rawurldecode($matches[2]),
                'key' => null,
            ];
        }

        if (preg_match('#^/(.+)\((\d+)\)$#', $relativePath, $matches)) {
            return [
                'type' => self::ROUTE_ENTITY,
                'feedId' => null,
                'entitySet' => rawurldecode($matches[1]),
                'key' => (int) $matches[2],
            ];
        }

        if (preg_match('#^/(.+)$#', $relativePath, $matches)) {
            return [
                'type' => self::ROUTE_COLLECTION,
                'feedId' => null,
                'entitySet' => rawurldecode($matches[1]),
                'key' => null,
            ];
        }

        return [
            'type' => self::ROUTE_NOT_FOUND,
            'feedId' => null,
            'entitySet' => null,
            'key' => null,
        ];
    }

    private function resolveRelativePath(string $requestPath): string
    {
        $normalizedRequestPath = rtrim($requestPath, '/') ?: '/';
        $normalizedBasePath = rtrim($this->basePath, '/') ?: '/';

        if ($normalizedBasePath === '/') {
            return $normalizedRequestPath;
        }

        if ($normalizedRequestPath === $normalizedBasePath) {
            return '/';
        }

        if (Str::startsWith($normalizedRequestPath, $normalizedBasePath . '/')) {
            return substr($normalizedRequestPath, strlen($normalizedBasePath));
        }

        if (Str::startsWith($normalizedRequestPath, $normalizedBasePath)) {
            return substr($normalizedRequestPath, strlen($normalizedBasePath)) ?: '/';
        }

        return $normalizedRequestPath;
    }
}
