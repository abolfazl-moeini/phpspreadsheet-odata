<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Auth;

use WPDev\PhpSpreadsheetOData\Contracts\AuthenticatorInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ApiKeyAuthenticator implements AuthenticatorInterface
{
    /** @var string */
    private $headerName;

    /** @var callable */
    private $validator;

    /**
     * @param callable(string): bool $validator
     */
    public function __construct(string $headerName, callable $validator)
    {
        $this->headerName = $headerName;
        $this->validator = $validator;
    }

    public function authenticate(ServerRequestInterface $request): bool
    {
        $key = $request->getHeaderLine($this->headerName);

        if ($key === '') {
            return false;
        }

        return ($this->validator)($key);
    }
}
