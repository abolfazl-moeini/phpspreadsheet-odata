# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-06-17

### Added

- OData v4 read-only server over PhpSpreadsheet workbooks (PSR-7)
- Single-feed and multi-feed (`feedId`) routing
- `FeedResolverInterface` with `InMemoryFeedResolver` and `PdoFeedResolver`
- Bearer, API Key, and Basic authentication
- OData query options: `$filter`, `$select`, `$top`, `$skip`, `$count`, `$orderby`
- PhpSpreadsheet v2/v3 API-compatibility helpers (`Support\Str`, `Support\WorksheetCells`)
- PHPUnit test suite, PHPStan configuration, and GitHub Actions CI

### Security

- Query limits for `$top` (1000) and `$skip` (100000)
- OData-shaped error responses; uncaught exceptions return generic 500
- `WWW-Authenticate` challenge headers for Basic/Bearer auth failures