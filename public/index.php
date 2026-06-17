<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\ServerRequest;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use WPDev\PhpSpreadsheetOData\Feed\InMemoryFeedResolver;
use WPDev\PhpSpreadsheetOData\OData\ODataServer;

require dirname(__DIR__) . '/vendor/autoload.php';

$employees = new Spreadsheet();
$employeesSheet = $employees->getActiveSheet();
$employeesSheet->setTitle('Employees');
$employeesSheet->fromArray([
    ['Id', 'Name', 'Age', 'Department'],
    [1, 'Alice', 30, 'Engineering'],
    [2, 'Bob', 25, 'Sales'],
    [3, 'Charlie', 35, 'Engineering'],
    [4, 'Diana', 28, 'Marketing'],
]);

$products = new Spreadsheet();
$productsSheet = $products->getActiveSheet();
$productsSheet->setTitle('Products');
$productsSheet->fromArray([
    ['Sku', 'Title', 'Price'],
    ['A1', 'Widget', 9.99],
    ['B2', 'Gadget', 14.50],
]);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$serviceRoot = sprintf('%s://%s/odata', $scheme, $host);

$resolver = new InMemoryFeedResolver([
    'employees' => $employees,
    'products' => $products,
]);

$server = new ODataServer($resolver, $serviceRoot);

// Uncomment one of the following authentication strategies:
// $server->useBearer(fn (string $token): bool => $token === 'secret');
// $server->useApiKey('X-API-Key', fn (string $key): bool => $key === 'my-key');
// $server->useBasicAuth(fn (string $user, string $pass): bool => $user === 'admin' && $pass === 'pass');

$request = ServerRequest::fromGlobals();
$response = $server->handle($request);

http_response_code($response->getStatusCode());

foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header(sprintf('%s: %s', $name, $value), false);
    }
}

echo (string) $response->getBody();