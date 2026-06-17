<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\OData;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use WPDev\PhpSpreadsheetOData\Auth\ApiKeyAuthenticator;
use WPDev\PhpSpreadsheetOData\Auth\BasicAuthenticator;
use WPDev\PhpSpreadsheetOData\Auth\BearerAuthenticator;
use WPDev\PhpSpreadsheetOData\Contracts\AuthenticatorInterface;
use WPDev\PhpSpreadsheetOData\Contracts\FeedResolverInterface;
use WPDev\PhpSpreadsheetOData\Contracts\QueryHandlerInterface;
use WPDev\PhpSpreadsheetOData\Feed\InMemoryFeedResolver;
use WPDev\PhpSpreadsheetOData\Http\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ODataServer
{
    private ?AuthenticatorInterface $authenticator = null;

    private FeedResolverInterface $feedResolver;

    private ?Spreadsheet $legacySpreadsheet;

    private string $serviceRoot;

    private QueryHandlerInterface $queryProcessor;

    private Router $router;

    /**
     * @param FeedResolverInterface|Spreadsheet $dataSource
     */
    public function __construct(
        $dataSource,
        string $serviceRoot = 'http://localhost/odata',
        ?QueryHandlerInterface $queryProcessor = null
    ) {
        if ($dataSource instanceof Spreadsheet) {
            $this->feedResolver = new InMemoryFeedResolver([]);
            $this->legacySpreadsheet = $dataSource;
        } elseif ($dataSource instanceof FeedResolverInterface) {
            $this->feedResolver = $dataSource;
            $this->legacySpreadsheet = null;
        } else {
            throw new \InvalidArgumentException('Data source must be a Spreadsheet or FeedResolverInterface.');
        }

        $this->serviceRoot = rtrim($serviceRoot, '/');
        $this->queryProcessor = $queryProcessor ?? new QueryProcessor();
        $this->router = new Router($this->extractBasePath($this->serviceRoot));
    }

    /**
     * @param callable(string): bool $validator
     */
    public function useBearer(callable $validator): self
    {
        $this->authenticator = new BearerAuthenticator($validator);

        return $this;
    }

    /**
     * @param callable(string): bool $validator
     */
    public function useApiKey(string $headerName, callable $validator): self
    {
        $this->authenticator = new ApiKeyAuthenticator($headerName, $validator);

        return $this;
    }

    /**
     * @param callable(string, string): bool $validator
     */
    public function useBasicAuth(callable $validator): self
    {
        $this->authenticator = new BasicAuthenticator($validator);

        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (strtoupper($request->getMethod()) !== 'GET') {
            return ODataError::json(405, '405', 'Method Not Allowed', [
                'Allow' => 'GET',
            ]);
        }

        if ($this->authenticator !== null && !$this->authenticator->authenticate($request)) {
            return ODataError::json(401, '401', 'Unauthorized', $this->authChallengeHeaders());
        }

        try {
            $route = $this->router->match($request->getUri()->getPath());
            $feedId = $route['feedId'] ?? null;

            if ($route['type'] === Router::ROUTE_SERVICE_DOCUMENT) {
                return $this->serviceDocumentResponse();
            }

            $spreadsheet = $this->resolveSpreadsheet($feedId);

            if ($spreadsheet === null) {
                return $this->notFoundResponse();
            }

            $context = $this->createFeedContext($spreadsheet, $feedId);

            switch ($route['type']) {
                case Router::ROUTE_METADATA:
                    return $this->metadataResponse($context);
                case Router::ROUTE_COLLECTION:
                    $entitySet = EntitySetBuilder::normalizeIdentifier($route['entitySet'] ?? '');

                    return $this->collectionResponse(
                        $context,
                        $entitySet,
                        $request->getQueryParams()
                    );
                case Router::ROUTE_ENTITY:
                    $entitySet = EntitySetBuilder::normalizeIdentifier($route['entitySet'] ?? '');

                    return $this->entityResponse(
                        $context,
                        $entitySet,
                        $route['key'] ?? 0,
                        $request->getQueryParams()
                    );
                default:
                    return $this->notFoundResponse();
            }
        } catch (\InvalidArgumentException $e) {
            return ODataError::json(400, '400', $e->getMessage());
        } catch (\Throwable $e) {
            return ODataError::json(500, '500', 'Internal Server Error');
        }
    }

    private function serviceDocumentResponse(): ResponseInterface
    {
        if ($this->legacySpreadsheet !== null) {
            $context = $this->createFeedContext($this->legacySpreadsheet, null);
            $body = $context->responseFormatter->formatServiceDocument(
                $context->entitySetBuilder->getEntitySetNames()
            );

            return $this->jsonResponse(200, $body);
        }

        $formatter = new ResponseFormatter($this->serviceRoot);
        $body = $formatter->formatFeedServiceDocument($this->feedResolver->listFeedIds());

        return $this->jsonResponse(200, $body);
    }

    private function metadataResponse(FeedContext $context): ResponseInterface
    {
        return $this->xmlResponse(200, $context->metadataBuilder->build());
    }

    /**
     * @param array<string, string|array<string>> $queryParams
     */
    private function collectionResponse(
        FeedContext $context,
        string $entitySetName,
        array $queryParams
    ): ResponseInterface {
        if (!$context->entitySetBuilder->hasEntitySet($entitySetName)) {
            return $this->notFoundResponse();
        }

        $normalizedQuery = $this->normalizeQueryParams($queryParams);
        $entities = $context->entitySetBuilder->build($entitySetName);
        $result = $this->queryProcessor->apply($entities, $normalizedQuery);
        $body = $context->responseFormatter->formatCollection(
            $entitySetName,
            $result['value'],
            $result['count']
        );

        return $this->jsonResponse(200, $body);
    }

    /**
     * @param array<string, string|array<string>> $queryParams
     */
    private function entityResponse(
        FeedContext $context,
        string $entitySetName,
        int $key,
        array $queryParams
    ): ResponseInterface {
        if (!$context->entitySetBuilder->hasEntitySet($entitySetName)) {
            return $this->notFoundResponse();
        }

        $entity = $context->entitySetBuilder->findByKey($entitySetName, $key);

        if ($entity === null) {
            return $this->notFoundResponse();
        }

        $normalizedQuery = $this->normalizeQueryParams($queryParams);

        if (isset($normalizedQuery['$select'])) {
            $entity = $this->projectEntity($entity, $normalizedQuery['$select']);
        }

        $body = $context->responseFormatter->formatEntity($entitySetName, $entity);

        return $this->jsonResponse(200, $body);
    }

    private function resolveSpreadsheet(?string $feedId): ?Spreadsheet
    {
        if ($feedId !== null) {
            return $this->feedResolver->resolve($feedId);
        }

        return $this->legacySpreadsheet;
    }

    private function createFeedContext(Spreadsheet $spreadsheet, ?string $feedId): FeedContext
    {
        $scopedServiceRoot = $this->serviceRoot;

        if ($feedId !== null) {
            $scopedServiceRoot = $this->serviceRoot . '/' . rawurlencode($feedId);
        }

        $entitySetBuilder = new EntitySetBuilder($spreadsheet);

        return new FeedContext(
            $entitySetBuilder,
            new MetadataBuilder($spreadsheet, $entitySetBuilder),
            new ResponseFormatter($scopedServiceRoot)
        );
    }

    private function notFoundResponse(): ResponseInterface
    {
        return ODataError::json(404, '404', 'Not Found');
    }

    /**
     * @return array<string, string>
     */
    private function authChallengeHeaders(): array
    {
        if ($this->authenticator instanceof BasicAuthenticator) {
            return ['WWW-Authenticate' => 'Basic realm="OData"'];
        }

        if ($this->authenticator instanceof BearerAuthenticator) {
            return ['WWW-Authenticate' => 'Bearer realm="OData"'];
        }

        return [];
    }

    private function jsonResponse(int $status, string $body): ResponseInterface
    {
        return ODataError::withBody($status, 'application/json', $body);
    }

    private function xmlResponse(int $status, string $body): ResponseInterface
    {
        return ODataError::withBody($status, 'application/xml', $body);
    }

    private function extractBasePath(string $serviceRoot): string
    {
        $path = parse_url($serviceRoot, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/';
        }

        return rtrim($path, '/') ?: '/';
    }

    /**
     * @param array<string, mixed> $entity
     * @return array<string, mixed>
     */
    private function projectEntity(array $entity, string $select): array
    {
        $fields = array_map('trim', explode(',', $select));
        $projected = [];

        foreach ($fields as $field) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field)) {
                throw new \InvalidArgumentException('Invalid $select query syntax.');
            }

            if (array_key_exists($field, $entity)) {
                $projected[$field] = $entity[$field];
            }
        }

        return $projected;
    }

    /**
     * @param array<string, string|array<string>> $queryParams
     * @return array<string, string>
     */
    private function normalizeQueryParams(array $queryParams): array
    {
        $normalized = [];

        foreach ($queryParams as $name => $value) {
            if (is_array($value)) {
                $value = $value[0] ?? '';
            }

            $normalized[$name] = (string) $value;
        }

        return $normalized;
    }
}
