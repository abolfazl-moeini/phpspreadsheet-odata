<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Tests\Http;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPDev\PhpSpreadsheetOData\Feed\InMemoryFeedResolver;
use WPDev\PhpSpreadsheetOData\OData\ODataServer;
use WPDev\PhpSpreadsheetOData\Tests\Support\SpreadsheetFactory;

final class FeedRoutingTest extends TestCase
{
    private ODataServer $server;

    protected function setUp(): void
    {
        $tenantA = SpreadsheetFactory::fromRows([
            ['Id', 'Name'],
            [1, 'Alice'],
            [2, 'Bob'],
        ], 'Employees');

        $tenantB = SpreadsheetFactory::fromRows([
            ['Sku', 'Title'],
            ['A1', 'Widget'],
            ['B2', 'Gadget'],
        ], 'Products');

        $resolver = new InMemoryFeedResolver([
            'tenant-a' => $tenantA,
            'tenant-b' => $tenantB,
        ]);

        $this->server = new ODataServer($resolver, 'http://localhost/odata');
    }

    #[Test]
    public function it_returns_404_for_unknown_feed_id(): void
    {
        $request = new ServerRequest('GET', '/odata/unknown/$metadata');
        $response = $this->server->handle($request);

        $this->assertSame(404, $response->getStatusCode());
        /** @var array{error: array{code: string, message: string}} $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('404', $body['error']['code']);
    }

    #[Test]
    public function it_returns_metadata_for_known_feed_id(): void
    {
        $request = new ServerRequest('GET', '/odata/tenant-a/$metadata');
        $response = $this->server->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $xml = (string) $response->getBody();
        $this->assertStringContainsString('EntityType Name="Employees"', $xml);
        $this->assertStringNotContainsString('EntityType Name="Products"', $xml);
    }

    #[Test]
    public function it_returns_entity_collection_for_known_feed_id(): void
    {
        $request = new ServerRequest('GET', '/odata/tenant-a/Employees');
        $response = $this->server->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array{value: list<array<string, mixed>>, '@odata.context': string} $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $body['value']);
        $this->assertSame('Alice', $body['value'][0]['Name']);
        $this->assertSame('http://localhost/odata/tenant-a/$metadata#Employees', $body['@odata.context']);
    }

    #[Test]
    public function it_returns_different_datasets_for_different_feed_ids(): void
    {
        $requestA = new ServerRequest('GET', '/odata/tenant-a/Employees');
        $requestB = new ServerRequest('GET', '/odata/tenant-b/Products');

        /** @var array{value: list<array<string, mixed>>} $bodyA */
        $bodyA = json_decode((string) $this->server->handle($requestA)->getBody(), true, 512, JSON_THROW_ON_ERROR);
        /** @var array{value: list<array<string, mixed>>} $bodyB */
        $bodyB = json_decode((string) $this->server->handle($requestB)->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Alice', $bodyA['value'][0]['Name']);
        $this->assertSame('Widget', $bodyB['value'][0]['Title']);
    }

    #[Test]
    public function it_returns_401_before_feed_resolution_when_auth_fails(): void
    {
        $this->server->useBearer(fn (string $token): bool => $token === 'secret');

        $request = new ServerRequest('GET', '/odata/tenant-a/Employees');
        $response = $this->server->handle($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function it_returns_404_with_valid_auth_for_unknown_feed_id(): void
    {
        $this->server->useBearer(fn (string $token): bool => $token === 'secret');

        $request = new ServerRequest('GET', '/odata/unknown/Employees', [
            'Authorization' => 'Bearer secret',
        ]);
        $response = $this->server->handle($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function it_keeps_phase1_routes_working_with_legacy_spreadsheet_constructor(): void
    {
        $legacyServer = new ODataServer(SpreadsheetFactory::sample(), 'http://localhost/odata');
        $request = new ServerRequest('GET', '/odata/Employees');
        $response = $legacyServer->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array{value: list<array<string, mixed>>} $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(3, $body['value']);
    }

    #[Test]
    public function it_lists_feeds_at_root_service_document_for_resolver(): void
    {
        $request = new ServerRequest('GET', '/odata');
        $response = $this->server->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array{value: list<array<string, mixed>>} $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $body['value']);
        $this->assertSame('tenant-a', $body['value'][0]['name']);
        $this->assertSame('Feed', $body['value'][0]['kind']);
    }
}
