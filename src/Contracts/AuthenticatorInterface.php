<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Contracts;

use Psr\Http\Message\ServerRequestInterface;

interface AuthenticatorInterface
{
    public function authenticate(ServerRequestInterface $request): bool;
}
