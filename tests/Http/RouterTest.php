<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Tests\Http;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use WPDev\PhpSpreadsheetOData\Http\Router;
use WPDev\PhpSpreadsheetOData\OData\ODataServer;
use WPDev\PhpSpreadsheetOData\Tests\Support\SpreadsheetFactory;

/**
 * @covers Router
 */
final class RouterTest extends TestCase
{
    private ODataServer $server;

    protected function setUp(): void
    {
        $this->server = new ODataServer(SpreadsheetFactory::sample(), 'http://localhost/odata');
    }
    /** @test */
    public function it_routes_service_document(): void
    {
        $request = new ServerRequest('GET', '/odata');
        $response = $this->server->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('@odata.context', $body);
        $this->assertArrayHasKey('value', $body);
    }
    /** @test */
    public function it_routes_metadata_endpoint(): void
    {
        $request = new ServerRequest('GET', '/odata/$metadata');
        $response = $this->server->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/xml', $response->getHeaderLine('Content-Type'));
        $this->assertSame('4.0', $response->getHeaderLine('OData-Version'));
        $this->assertStringContainsString('<edmx:Edmx', (string) $response->getBody());
    }
    /** @test */
    public function it_routes_entity_collection(): void
    {
        $request = new ServerRequest('GET', '/odata/Employees');
        $response = $this->server->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('4.0', $response->getHeaderLine('OData-Version'));
        /** @var array{value: list<array<string, mixed>>} $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(3, $body['value']);
    }
    /** @test */
    public function it_routes_single_entity_by_key(): void
    {
        $request = new ServerRequest('GET', '/odata/Employees(2)');
        $response = $this->server->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Bob', $body['Name']);
    }
    /** @test */
    public function it_returns_404_for_unknown_route(): void
    {
        $request = new ServerRequest('GET', '/odata/UnknownSheet');
        $response = $this->server->handle($request);

        $this->assertSame(404, $response->getStatusCode());
    }
    /** @test */
    public function it_returns_401_when_authentication_fails(): void
    {
        $server = new ODataServer(SpreadsheetFactory::sample(), 'http://localhost/odata');
        $server->useBearer(fn (string $token): bool => $token === 'secret');

        $request = new ServerRequest('GET', '/odata/Employees');
        $response = $server->handle($request);

        $this->assertSame(401, $response->getStatusCode());
    }
    /** @test */
    public function it_applies_query_options_on_collection(): void
    {
        $request = (new ServerRequest('GET', '/odata/Employees'))
            ->withQueryParams(['$top' => '1', '$filter' => "Name eq 'Alice'"]);
        $response = $this->server->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array{value: list<array<string, mixed>>} $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $body['value']);
        $this->assertSame('Alice', $body['value'][0]['Name']);
    }
    /** @test */
    public function it_rejects_non_get_methods_with_405(): void
    {
        foreach (['POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            $request = new ServerRequest($method, '/odata/Employees');
            $response = $this->server->handle($request);
            $this->assertSame(405, $response->getStatusCode(), "Expected 405 for $method");
            $this->assertSame('GET', $response->getHeaderLine('Allow'));
        }
    }
    /** @test */
    public function it_routes_entity_collection_case_insensitively(): void
    {
        $request = new ServerRequest('GET', '/odata/employees');
        $response = $this->server->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array{value: list<array<string, mixed>>} $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(3, $body['value']);
    }
    /** @test */
    public function it_returns_400_bad_request_on_validation_errors(): void
    {
        $request = (new ServerRequest('GET', '/odata/Employees'))
            ->withQueryParams(['$top' => 'invalid']);
        $response = $this->server->handle($request);

        $this->assertSame(400, $response->getStatusCode());
        /** @var array{error: array{code: string, message: string}} $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('400', $body['error']['code']);
        $this->assertStringContainsString('must be a non-negative integer', $body['error']['message']);
    }
}