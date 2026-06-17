<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Tests\OData;

use GuzzleHttp\Psr7\ServerRequest;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;
use WPDev\PhpSpreadsheetOData\Contracts\FeedResolverInterface;
use WPDev\PhpSpreadsheetOData\OData\ODataServer;
use WPDev\PhpSpreadsheetOData\Tests\Support\SpreadsheetFactory;

final class ODataServerTest extends TestCase
{
    /** @test */
    public function it_normalizes_trailing_slash_in_service_root(): void
    {
        $server = new ODataServer(SpreadsheetFactory::sample(), 'http://localhost/odata/');
        $request = new ServerRequest('GET', '/odata/Employees');
        $response = $server->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array{@odata.context: string} $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('http://localhost/odata/$metadata#Employees', $body['@odata.context']);
    }

    /** @test */
    public function it_returns_500_when_feed_resolver_throws(): void
    {
        $resolver = new class implements FeedResolverInterface {
            public function resolve(string $feedId): ?Spreadsheet
            {
                throw new \RuntimeException('Resolver failure');
            }

            public function listFeedIds(): array
            {
                return ['broken'];
            }
        };

        $server = new ODataServer($resolver, 'http://localhost/odata');
        $request = new ServerRequest('GET', '/odata/broken/$metadata');
        $response = $server->handle($request);

        $this->assertSame(500, $response->getStatusCode());
        /** @var array{error: array{code: string, message: string}} $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('500', $body['error']['code']);
        $this->assertSame('Internal Server Error', $body['error']['message']);
    }

    /** @test */
    public function it_includes_www_authenticate_for_basic_auth_failures(): void
    {
        $server = new ODataServer(SpreadsheetFactory::sample(), 'http://localhost/odata');
        $server->useBasicAuth(function (string $user, string $pass): bool {
            return false;
        });

        $request = new ServerRequest('GET', '/odata/Employees');
        $response = $server->handle($request);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Basic realm="OData"', $response->getHeaderLine('WWW-Authenticate'));
    }
}