# phpspreadsheet-odata — Agent Context

This document gives AI agents enough background to work on this codebase without re-discovering architecture, conventions, or completed design decisions.

## Project summary

**Package:** `wpdev/phpspreadsheet-odata`  
**Namespace:** `WPDev\PhpSpreadsheetOData`  
**Purpose:** Framework-agnostic, read-only **OData v4** HTTP feed over [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) workbooks.

Core idea: pass a `Spreadsheet` (or resolve one per feed) — each worksheet becomes an OData entity set; row 1 is headers; data rows become entities with synthetic key `RowIndex` (1-based, excluding header).

No framework dependencies (Laravel, Symfony, etc.). Uses **PSR-7** request/response via `ODataServer::handle(ServerRequestInterface): ResponseInterface`.

## Requirements & constraints

| Area | Rule |
|------|------|
| PHP | `>=7.4` — **no PHP 8-only syntax** in `src/` (`readonly`, `match`, union types, `mixed` hints, `str_*` polyfills needed) |
| Style | PSR-4 autoloading, PSR-12, `declare(strict_types=1)` |
| HTTP | PSR-7 only; Guzzle PSR-7 used for responses |
| Database | Core is **database-agnostic**; optional `PdoFeedResolver` is an example only |
| Methods | **GET only** — other methods return `405` |
| OData | v4 read-only; responses include `OData-Version: 4.0` header |

## Architecture

```
src/
├── Contracts/
│   ├── AuthenticatorInterface.php
│   ├── FeedResolverInterface.php
│   └── QueryHandlerInterface.php
├── Auth/
│   ├── BearerAuthenticator.php
│   ├── ApiKeyAuthenticator.php
│   └── BasicAuthenticator.php
├── Feed/
│   ├── InMemoryFeedResolver.php
│   └── PdoFeedResolver.php
├── Http/
│   └── Router.php
├── OData/
│   ├── ODataServer.php          ← main entry point
│   ├── EntitySetBuilder.php     ← worksheet → entities
│   ├── MetadataBuilder.php      ← $metadata EDMX XML
│   ├── QueryProcessor.php       ← $filter, $select, $top, etc.
│   ├── ResponseFormatter.php    ← OData JSON shapes
│   └── FeedContext.php          ← per-request builder bundle
└── Support/
    ├── Str.php                  ← PHP 7.4 string helpers
    └── WorksheetCells.php       ← PhpSpreadsheet 1.x / 2+ cell API shim
```

### Request flow (`ODataServer::handle`)

1. Reject non-GET → `405`
2. Authenticate if configured → `401` on failure
3. `Router::match(path)` → route type + optional `feedId`, `entitySet`, `key`
4. Service document (`GET /odata`) — legacy spreadsheet or feed index
5. Resolve spreadsheet:
   - With `feedId` → `FeedResolverInterface::resolve($feedId)`; `null` → `404`
   - Without `feedId` → legacy `Spreadsheet` from constructor (Phase 1)
6. Build `FeedContext` (scoped `serviceRoot` includes `/{feedId}` when present)
7. Dispatch: metadata / collection / entity
8. `InvalidArgumentException` from query validation → `400`

### Auth order (important)

Authentication runs **before** feed resolution:

- Invalid/missing auth → `401` (even if `feedId` would be valid)
- Valid auth + unknown `feedId` → `404`

## Routing

Base path derived from `serviceRoot` URL (e.g. `http://localhost/odata` → base `/odata`).

### Phase 1 — single feed (backward compatible)

Pass `Spreadsheet` directly to `ODataServer` constructor:

```
GET /odata
GET /odata/$metadata
GET /odata/{EntitySet}
GET /odata/{EntitySet}({key})
```

### Phase 2 — multi-feed / multi-tenant

Pass `FeedResolverInterface` to constructor:

```
GET /odata                              → lists feed IDs (InMemoryFeedResolver)
GET /odata/{feedId}/$metadata
GET /odata/{feedId}/{EntitySet}
GET /odata/{feedId}/{EntitySet}({key})
```

`feedId` pattern: `[A-Za-z0-9_-]+`

Router tries feed-prefixed routes **before** single-segment Phase 1 routes so `/odata/Employees` still resolves as entity set, not feedId.

## Data mapping

- **Entity key:** `RowIndex` (int, 1-based data row index)
- **Property names:** normalized from header row via `EntitySetBuilder::normalizeIdentifier()` (non-alphanumeric → `_`, collision suffixes)
- **Entity set names:** normalized worksheet titles (case-insensitive lookup supported)
- **Types in EDMX:** inferred from first data row sample (`Edm.String`, `Edm.Int32`, `Edm.Double`, `Edm.Boolean`, `Edm.DateTimeOffset`)
- **Formulas:** `getCalculatedValue()` with fallback to `getValue()`

## Query options (`QueryProcessor`)

| Option | Support |
|--------|---------|
| `$filter` | `eq`, `ne`, `gt`, `lt`, `ge`, `le`; multiple conditions with `and` |
| `$select` | Comma-separated property names |
| `$top` / `$skip` | Non-negative integers |
| `$count` | `true` or `false` |
| `$orderby` | `Property asc` or `Property desc` |

Invalid query syntax throws `InvalidArgumentException` → HTTP 400.

## Authentication API

```php
$server->useBearer(fn (string $token): bool => ...);
$server->useApiKey('X-API-Key', fn (string $key): bool => ...);
$server->useBasicAuth(fn (string $user, string $pass): bool => ...);
```

Only one authenticator active at a time (last call wins).

## Feed resolution API

```php
interface FeedResolverInterface {
    public function resolve(string $feedId): ?Spreadsheet;
    public function listFeedIds(): array;
}
```

**Implementations:**

- `InMemoryFeedResolver` — `array<string, Spreadsheet>`; `listFeedIds()` for service document
- `PdoFeedResolver` — PDO lookup `feed_id → source_ref`, then user-provided loader callback

**Constructor flexibility:**

```php
new ODataServer($spreadsheet, $serviceRoot);           // Phase 1 legacy
new ODataServer($feedResolver, $serviceRoot);          // Phase 2 multi-feed
```

## OData response shapes

- Collection: `{ "@odata.context", "value": [...], "@odata.count"? }`
- Entity: `{ "@odata.context", ...properties }`
- Service document: `{ "@odata.context", "value": [{ name, kind, url }] }`
- Errors: `{ "error": { "code", "message" } }` with appropriate status

## Testing

- **Framework:** PHPUnit; use `/** @test */` docblocks (PHP 7.4 compatible), not attributes
- **Run:** `composer install && ./vendor/bin/phpunit`
- **TDD:** write tests before implementation for new behavior
- **Helpers:** `tests/Support/SpreadsheetFactory.php` builds test workbooks

### Test layout

```
tests/
├── Auth/           — Bearer, ApiKey, Basic (valid/invalid/missing)
├── Feed/           — InMemoryFeedResolver, PdoFeedResolver (sqlite :memory:)
├── Http/           — Router, feed routing integration, auth precedence
└── OData/          — MetadataBuilder, EntitySetBuilder, QueryProcessor, ResponseFormatter
```

## PHP 7.4 compatibility notes

- Use `WPDev\PhpSpreadsheetOData\Support\Str` instead of `str_starts_with` / `str_ends_with` / `str_contains`
- Use `WorksheetCells::getCell()` / `setValue()` instead of direct coordinate APIs
- Avoid `readonly`, `match`, union types, `mixed` type hints in `src/`
- Use `switch` instead of `match`; explicit properties instead of constructor promotion with `readonly`
- PhpSpreadsheet: supports `^1.29` through `^5.0`

## Extension guidelines for agents

**Do:**

- Keep changes focused; match existing naming and patterns
- Maintain backward compatibility for Phase 1 routes (spreadsheet constructor)
- Keep core free of framework/DB dependencies
- Add PHPUnit tests for new behavior
- Preserve PSR-7 boundary at `ODataServer::handle()`

**Don't:**

- Add write operations (POST/PATCH/DELETE) unless explicitly requested
- Require a database in core
- Break `feedId`-optional routing for legacy single-spreadsheet usage
- Introduce PHP 8+ syntax in `src/` without explicit approval
- Redefine data separately from PhpSpreadsheet — always read from workbook structure

## Key files for common tasks

| Task | Files |
|------|-------|
| New route / path parsing | `src/Http/Router.php`, `tests/Http/` |
| New query option | `src/OData/QueryProcessor.php`, `tests/OData/QueryProcessorTest.php` |
| Entity/metadata shape | `EntitySetBuilder.php`, `MetadataBuilder.php`, `ResponseFormatter.php` |
| New auth method | `src/Auth/`, `src/Contracts/AuthenticatorInterface.php`, `ODataServer.php` |
| New feed source | `src/Contracts/FeedResolverInterface.php`, `src/Feed/` |
| Standalone demo | `public/index.php` |
| User docs | `README.md` |

## Error handling

- `ODataError` centralizes JSON/XML success and error responses.
- `InvalidArgumentException` (bad query syntax) → HTTP 400.
- Uncaught `\Throwable` → HTTP 500 with generic message (no stack traces leaked).
- `serviceRoot` is normalized (`rtrim` trailing slash) in `ODataServer` constructor.

## Local development

```bash
composer install
composer test
composer phpstan
php -S localhost:8080 -t public
```

Example URLs (see `public/index.php` — uses `InMemoryFeedResolver` with `employees` and `products` feeds):

- `http://localhost:8080/odata`
- `http://localhost:8080/odata/employees/$metadata`
- `http://localhost:8080/odata/employees/Employees`

## Completed phases

| Phase | Status | Summary |
|-------|--------|---------|
| Phase 1 | Done | OData v4 read-only from `Spreadsheet`; auth; query options; PSR-7 server |
| Phase 2 | Done | `feedId` routing; `FeedResolverInterface`; InMemory + PDO resolvers; backward compatible |

When adding Phase 3+ features, update this file and `README.md`; do not add separate `phaseN.md` task files unless the user requests them.