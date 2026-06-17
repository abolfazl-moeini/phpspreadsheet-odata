# phpspreadsheet-odata

Framework-agnostic OData v4 read-only feed for [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) workbooks.

Each worksheet becomes an OData entity set. The first row defines property names; remaining rows are exposed as entities with a synthetic `RowIndex` key.

## Requirements

- PHP >= 8.1
- Composer

## Installation

```bash
composer require wpdev/phpspreadsheet-odata
```

## Quick Start

```php
use GuzzleHttp\Psr7\ServerRequest;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use WPDev\PhpSpreadsheetOData\OData\ODataServer;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->fromArray([
    ['Id', 'Name', 'Age'],
    [1, 'Alice', 30],
    [2, 'Bob', 25],
]);

$server = new ODataServer($spreadsheet, 'http://localhost:8080/odata');

// Optional authentication
$server->useBearer(fn (string $token): bool => $token === 'secret');
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
```

Run the included example:

```bash
composer install
php -S localhost:8080 -t public
```

Then open:

- Feed index: `http://localhost:8080/odata`
- Metadata: `http://localhost:8080/odata/employees/$metadata`
- Entity set: `http://localhost:8080/odata/employees/Employees`
- Single entity: `http://localhost:8080/odata/employees/Employees(1)`

## Supported Endpoints

### Single feed (Phase 1 — backward compatible)

Pass a `Spreadsheet` directly to `ODataServer`:

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/` | OData service document (JSON) |
| `GET` | `/$metadata` | EDMX metadata (XML) |
| `GET` | `/{SheetName}` | Entity collection |
| `GET` | `/{SheetName}({key})` | Single entity by `RowIndex` |

### Multi-feed / multi-tenant (Phase 2)

Inject a `FeedResolverInterface` to serve many feeds from one endpoint:

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/` | Lists registered feed IDs |
| `GET` | `/{feedId}/$metadata` | EDMX metadata for that feed |
| `GET` | `/{feedId}/{SheetName}` | Entity collection |
| `GET` | `/{feedId}/{SheetName}({key})` | Single entity by `RowIndex` |

`feedId` must be URL-safe (`[A-Za-z0-9_-]+`).

## Feed Resolution

The core package is database-agnostic. Provide a `FeedResolverInterface` to map `feedId` → `Spreadsheet`:

```php
use WPDev\PhpSpreadsheetOData\Contracts\FeedResolverInterface;
use WPDev\PhpSpreadsheetOData\Feed\InMemoryFeedResolver;
use WPDev\PhpSpreadsheetOData\OData\ODataServer;

$resolver = new InMemoryFeedResolver([
    'tenant-a' => $spreadsheetA,
    'tenant-b' => $spreadsheetB,
]);

$server = new ODataServer($resolver, 'http://localhost:8080/odata');
```

### Optional PDO-backed resolver

For database-backed feeds, use `PdoFeedResolver` with an injected `PDO` and a loader callback. The resolver only looks up a `source_ref` — loading the spreadsheet is your responsibility:

```php
use WPDev\PhpSpreadsheetOData\Feed\PdoFeedResolver;

PdoFeedResolver::createTable($pdo);

$resolver = new PdoFeedResolver(
    $pdo,
    'odata_feeds',
    fn (string $sourceRef): ?Spreadsheet => loadSpreadsheetFromStorage($sourceRef),
);

$server = new ODataServer($resolver, 'http://localhost:8080/odata');
```

Authentication is checked before feed resolution: invalid auth → `401`; valid auth + unknown `feedId` → `404`.

## Query Options

| Option | Example |
|--------|---------|
| `$filter` | `?$filter=Name eq 'Alice'` |
| `$select` | `?$select=Name,Age` |
| `$top` | `?$top=10` |
| `$skip` | `?$skip=20` |
| `$count` | `?$count=true` |
| `$orderby` | `?$orderby=Age desc` |

Supported filter operators: `eq`, `ne`, `gt`, `lt`, `ge`, `le`. Multiple conditions can be combined with `and`.

## Authentication

```php
$server->useBearer(fn ($token) => $token === 'secret');
$server->useApiKey('X-API-Key', fn ($key) => $key === 'my-key');
$server->useBasicAuth(fn ($user, $pass) => $user === 'admin' && $pass === 'pass');
```

Invalid or missing credentials return `401 Unauthorized` with a `WWW-Authenticate` challenge header (`Basic realm="OData"` or `Bearer realm="OData"`).

### Excel credential prompt

When paired with `wpdev/odata-feed`, the workbook stores only the OData URL — no password in the file. On **Data → Refresh All**, Power Query requests `$metadata` first; a `401` with `WWW-Authenticate: Basic` triggers Excel's native username/password dialog. Credentials are saved in the OS credential store (Keychain on macOS), keyed by the data source URL.

If a feed was previously refreshed anonymously, clear or edit stored credentials via **Data → Get Data → Data Source Settings → Edit Permissions**.

Use HTTPS in production when enabling Basic auth.

## Architecture

```
src/
├── Contracts/
├── Auth/
├── Feed/
├── OData/
└── Http/
```

- `ODataServer` — main PSR-7 entry point
- `FeedResolverInterface` — resolves `feedId` to a `Spreadsheet`
- `InMemoryFeedResolver` — array-backed resolver for tests and small setups
- `PdoFeedResolver` — optional PDO example for DB-backed feed lookup
- `EntitySetBuilder` — maps worksheets to entities
- `MetadataBuilder` — generates `$metadata` EDMX
- `QueryProcessor` — applies OData query options
- `ResponseFormatter` — formats OData JSON responses
- `Router` — routes requests to handlers (with optional `feedId` segment)

## Production deployment

- Set `serviceRoot` to the public URL of your OData endpoint (no trailing slash required).
- Enable authentication before exposing feeds to the internet.
- Use HTTPS via your web server or reverse proxy.
- For multi-tenant setups, implement `FeedResolverInterface` to load workbooks from your storage layer.
- Query limits are enforced: `$top` ≤ 1000, `$skip` ≤ 100000.

## Development

```bash
composer install
composer test
composer phpstan
```

## License

MIT — see [LICENSE](LICENSE).