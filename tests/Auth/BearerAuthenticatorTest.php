<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Tests\Auth;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPDev\PhpSpreadsheetOData\Auth\BearerAuthenticator;

#[CoversClass(BearerAuthenticator::class)]
final class BearerAuthenticatorTest extends TestCase
{
    #[Test]
    public function it_authenticates_with_valid_bearer_token(): void
    {
        $authenticator = new BearerAuthenticator(fn (string $token): bool => $token === 'secret');

        $request = new ServerRequest('GET', '/', ['Authorization' => 'Bearer secret']);

        $this->assertTrue($authenticator->authenticate($request));
    }

    #[Test]
    public function it_rejects_invalid_bearer_token(): void
    {
        $authenticator = new BearerAuthenticator(fn (string $token): bool => $token === 'secret');

        $request = new ServerRequest('GET', '/', ['Authorization' => 'Bearer wrong']);

        $this->assertFalse($authenticator->authenticate($request));
    }

    #[Test]
    public function it_rejects_missing_authorization_header(): void
    {
        $authenticator = new BearerAuthenticator(fn (string $token): bool => $token === 'secret');

        $request = new ServerRequest('GET', '/');

        $this->assertFalse($authenticator->authenticate($request));
    }
}
