<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Auth;

use WPDev\PhpSpreadsheetOData\Contracts\AuthenticatorInterface;
use WPDev\PhpSpreadsheetOData\Support\Str;
use Psr\Http\Message\ServerRequestInterface;

final class BearerAuthenticator implements AuthenticatorInterface
{
    /** @var callable */
    private $validator;

    /**
     * @param callable(string): bool $validator
     */
    public function __construct(callable $validator)
    {
        $this->validator = $validator;
    }

    public function authenticate(ServerRequestInterface $request): bool
    {
        $authorization = $request->getHeaderLine('Authorization');

        if ($authorization === '' || !Str::startsWith($authorization, 'Bearer ')) {
            return false;
        }

        $token = substr($authorization, 7);

        return ($this->validator)($token);
    }
}
