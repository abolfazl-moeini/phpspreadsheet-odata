# phpspreadsheet-odata — Implementation Plan

Framework-agnostic OData v4 read-only HTTP feed over PhpSpreadsheet workbooks.

## Requirements

| Area | Target |
|------|--------|
| PHP | **>= 7.4** |
| HTTP | PSR-7 only (`ODataServer::handle`) |
| OData | v4 read-only; `OData-Version: 4.0` header |
| Methods | GET only |
| Style | PSR-4, PSR-12, `declare(strict_types=1)` |

## Architecture

```
src/
├── Contracts/     AuthenticatorInterface, FeedResolverInterface, QueryHandlerInterface
├── Auth/          Bearer, ApiKey, Basic
├── Feed/          InMemoryFeedResolver, PdoFeedResolver
├── Http/          Router
├── OData/         ODataServer, EntitySetBuilder, MetadataBuilder, QueryProcessor, ResponseFormatter
└── Support/       Str, WorksheetCells (PhpSpreadsheet API shim)
```

## Phases

### Phase 1 — Single spreadsheet (done)

- Pass `Spreadsheet` to `ODataServer` constructor
- Routes: `/odata`, `/odata/$metadata`, `/odata/{EntitySet}`, `/odata/{EntitySet}({key})`
- Query options: `$filter`, `$select`, `$top`, `$skip`, `$count`, `$orderby`
- Optional auth: Bearer, API key, Basic

### Phase 2 — Multi-feed (done)

- `FeedResolverInterface` with `feedId` path segment
- Routes: `/odata/{feedId}/$metadata`, `/odata/{feedId}/{EntitySet}`
- `InMemoryFeedResolver` and `PdoFeedResolver`
- Backward compatible with Phase 1

### Phase 3+ — Future

- Additional query options (`$expand`, `$search`, …) as needed
- Write operations only if explicitly requested
- Optional caching layer (out of core)

## Data mapping

- Entity key: `RowIndex` (1-based data row index)
- Property names: `EntitySetBuilder::normalizeIdentifier()` on header row
- Entity set names: normalized worksheet titles (case-insensitive lookup)
- EDM types: inferred from up to 10 sample data rows
- Formulas: `getCalculatedValue()` with `getValue()` fallback

## Quality gates

```bash
composer validate --strict
composer lint
composer phpstan
composer test
```

## Local demo

```bash
composer install
php -S localhost:8080 -t public
```

Example URLs (see `public/index.php`):

- `http://localhost:8080/odata`
- `http://localhost:8080/odata/employees/$metadata`
- `http://localhost:8080/odata/employees/Employees`