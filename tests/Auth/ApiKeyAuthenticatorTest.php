<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Tests\Auth;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPDev\PhpSpreadsheetOData\Auth\ApiKeyAuthenticator;

#[CoversClass(ApiKeyAuthenticator::class)]
final class ApiKeyAuthenticatorTest extends TestCase
{
    #[Test]
    public function it_authenticates_with_valid_api_key(): void
    {
        $authenticator = new ApiKeyAuthenticator('X-API-Key', fn (string $key): bool => $key === 'my-key');

        $request = new ServerRequest('GET', '/', ['X-API-Key' => 'my-key']);

        $this->assertTrue($authenticator->authenticate($request));
    }

    #[Test]
    public function it_rejects_invalid_api_key(): void
    {
        $authenticator = new ApiKeyAuthenticator('X-API-Key', fn (string $key): bool => $key === 'my-key');

        $request = new ServerRequest('GET', '/', ['X-API-Key' => 'bad-key']);

        $this->assertFalse($authenticator->authenticate($request));
    }

    #[Test]
    public function it_rejects_missing_api_key_header(): void
    {
        $authenticator = new ApiKeyAuthenticator('X-API-Key', fn (string $key): bool => $key === 'my-key');

        $request = new ServerRequest('GET', '/');

        $this->assertFalse($authenticator->authenticate($request));
    }
}
