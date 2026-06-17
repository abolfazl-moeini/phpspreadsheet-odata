<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Tests\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPDev\PhpSpreadsheetOData\Http\Router;

#[CoversClass(Router::class)]
final class RouterFeedIdTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router('/odata');
    }

    #[Test]
    public function it_extracts_feed_id_from_metadata_route(): void
    {
        $route = $this->router->match('/odata/tenant-a/$metadata');

        $this->assertSame(Router::ROUTE_METADATA, $route['type']);
        $this->assertSame('tenant-a', $route['feedId']);
    }

    #[Test]
    public function it_extracts_feed_id_from_collection_route(): void
    {
        $route = $this->router->match('/odata/tenant-a/Employees');

        $this->assertSame(Router::ROUTE_COLLECTION, $route['type']);
        $this->assertSame('tenant-a', $route['feedId']);
        $this->assertSame('Employees', $route['entitySet']);
    }

    #[Test]
    public function it_extracts_feed_id_from_entity_route(): void
    {
        $route = $this->router->match('/odata/tenant-a/Employees(2)');

        $this->assertSame(Router::ROUTE_ENTITY, $route['type']);
        $this->assertSame('tenant-a', $route['feedId']);
        $this->assertSame('Employees', $route['entitySet']);
        $this->assertSame(2, $route['key']);
    }

    #[Test]
    public function it_keeps_phase1_routes_without_feed_id(): void
    {
        $metadata = $this->router->match('/odata/$metadata');
        $collection = $this->router->match('/odata/Employees');
        $entity = $this->router->match('/odata/Employees(1)');

        $this->assertNull($metadata['feedId'] ?? null);
        $this->assertNull($collection['feedId'] ?? null);
        $this->assertNull($entity['feedId'] ?? null);
        $this->assertSame('Employees', $collection['entitySet']);
    }

    #[Test]
    public function it_accepts_url_safe_feed_id_characters(): void
    {
        $route = $this->router->match('/odata/tenant_42-beta/$metadata');

        $this->assertSame('tenant_42-beta', $route['feedId']);
    }
}
