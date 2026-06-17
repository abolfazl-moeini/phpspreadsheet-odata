<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Tests\Auth;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use WPDev\PhpSpreadsheetOData\Auth\BasicAuthenticator;

/**
 * @covers BasicAuthenticator
 */
final class BasicAuthenticatorTest extends TestCase
{
    /** @test */
    public function it_authenticates_with_valid_basic_credentials(): void
    {
        $authenticator = new BasicAuthenticator(
            fn (string $user, string $pass): bool => $user === 'admin' && $pass === 'pass'
        );

        $credentials = base64_encode('admin:pass');
        $request = new ServerRequest('GET', '/', ['Authorization' => 'Basic ' . $credentials]);

        $this->assertTrue($authenticator->authenticate($request));
    }
    /** @test */
    public function it_rejects_invalid_basic_credentials(): void
    {
        $authenticator = new BasicAuthenticator(
            fn (string $user, string $pass): bool => $user === 'admin' && $pass === 'pass'
        );

        $credentials = base64_encode('admin:wrong');
        $request = new ServerRequest('GET', '/', ['Authorization' => 'Basic ' . $credentials]);

        $this->assertFalse($authenticator->authenticate($request));
    }
    /** @test */
    public function it_rejects_missing_authorization_header(): void
    {
        $authenticator = new BasicAuthenticator(
            fn (string $user, string $pass): bool => $user === 'admin' && $pass === 'pass'
        );

        $request = new ServerRequest('GET', '/');

        $this->assertFalse($authenticator->authenticate($request));
    }
}