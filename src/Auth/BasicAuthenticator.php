<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Auth;

use WPDev\PhpSpreadsheetOData\Contracts\AuthenticatorInterface;
use WPDev\PhpSpreadsheetOData\Support\Str;
use Psr\Http\Message\ServerRequestInterface;

final class BasicAuthenticator implements AuthenticatorInterface
{
    /** @var callable */
    private $validator;

    /**
     * @param callable(string, string): bool $validator
     */
    public function __construct(callable $validator)
    {
        $this->validator = $validator;
    }

    public function authenticate(ServerRequestInterface $request): bool
    {
        $authorization = $request->getHeaderLine('Authorization');

        if ($authorization === '' || !Str::startsWith($authorization, 'Basic ')) {
            return false;
        }

        $decoded = base64_decode(substr($authorization, 6), true);

        if ($decoded === false || !Str::contains($decoded, ':')) {
            return false;
        }

        [$username, $password] = explode(':', $decoded, 2);

        return ($this->validator)($username, $password);
    }
}
