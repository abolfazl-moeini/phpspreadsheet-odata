<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Tests\Auth;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use WPDev\PhpSpreadsheetOData\Auth\ApiKeyAuthenticator;

/**
 * @covers ApiKeyAuthenticator
 */
final class ApiKeyAuthenticatorTest extends TestCase
{
    /** @test */
    public function it_authenticates_with_valid_api_key(): void
    {
        $authenticator = new ApiKeyAuthenticator('X-API-Key', fn (string $key): bool => $key === 'my-key');

        $request = new ServerRequest('GET', '/', ['X-API-Key' => 'my-key']);

        $this->assertTrue($authenticator->authenticate($request));
    }
    /** @test */
    public function it_rejects_invalid_api_key(): void
    {
        $authenticator = new ApiKeyAuthenticator('X-API-Key', fn (string $key): bool => $key === 'my-key');

        $request = new ServerRequest('GET', '/', ['X-API-Key' => 'bad-key']);

        $this->assertFalse($authenticator->authenticate($request));
    }
    /** @test */
    public function it_rejects_missing_api_key_header(): void
    {
        $authenticator = new ApiKeyAuthenticator('X-API-Key', fn (string $key): bool => $key === 'my-key');

        $request = new ServerRequest('GET', '/');

        $this->assertFalse($authenticator->authenticate($request));
    }
}