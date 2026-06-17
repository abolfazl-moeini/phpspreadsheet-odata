## Per-feed routing & tenant state

=== TASK ===
Extend the existing OData endpoint package (Package 1) to support a
feedId path segment so that ONE running endpoint can serve many
different feeds/tenants, each resolving to its own data source.

URL format (already decided):
   GET /odata/{feedId}/$metadata
   GET /odata/{feedId}/{EntitySet}
   GET /odata/{feedId}/{EntitySet}({key})

=== CONTEXT ===
Phase 1 already serves OData v4 read-only from a PhpSpreadsheet object,
with Bearer / API Key / Basic auth, and query options
($filter,$select,$top,$skip,$count,$orderby).
This phase ADDS feedId resolution. Do not break Phase 1 behavior.

=== REQUIREMENTS ===
- PHP 8.1+, PSR-4, PSR-7, PSR-12
- Framework-agnostic, database-agnostic
- TDD with PHPUnit
- Backward compatible: routes without feedId still work (optional).

=== CORE NEW CONCEPT: FeedResolver ===
Define an interface:

interface FeedResolverInterface {
    // Return the Spreadsheet (data source) for a given feedId,
    // or null if the feedId is unknown.
    public function resolve(string $feedId): ?\PhpOffice\PhpSpreadsheet\Spreadsheet;
}

The ODataServer takes a FeedResolverInterface via constructor
(Dependency Injection). On each request:
1. Extract feedId from the path segment.
2. Call resolver->resolve(feedId).
3. If null -> 404 with OData error JSON.
4. Otherwise build metadata/entities from THAT spreadsheet.

=== OPTIONAL DB-BACKED RESOLVER ===
Provide an abstract/example resolver that uses an injected PDO instance
(DI again) to look up which data source a feedId maps to. Keep DB usage
OPTIONAL and isolated — the core must NOT require any database.

Provide also a simple InMemoryFeedResolver (array of feedId=>Spreadsheet)
for tests and small use cases.

=== AUTH INTERACTION ===
Auth still applies. Order of checks:
1. Authenticate the request (Phase 1 logic).
2. Then resolve feedId.
A valid auth but unknown feedId -> 404.
Missing/invalid auth -> 401 (regardless of feedId).

=== ROUTER CHANGES ===
Update Http/Router.php to parse:
   /odata/{feedId}/$metadata
   /odata/{feedId}/{EntitySet}
   /odata/{feedId}/{EntitySet}({key})
feedId is any URL-safe string ([A-Za-z0-9_-]+).

=== FILE STRUCTURE (additions) ===
src/
├── Contracts/
│   └── FeedResolverInterface.php        // NEW
├── Feed/
│   ├── InMemoryFeedResolver.php         // NEW
│   └── PdoFeedResolver.php              // NEW (optional DB example)
├── OData/
│   ├── ODataServer.php                  // MODIFIED (accept resolver)
│   └── ... (existing files unchanged)
└── Http/
    └── Router.php                       // MODIFIED (feedId segment)

=== TESTS (write first) ===
1. Router extracts feedId correctly from each route form.
2. Unknown feedId -> 404 OData error body.
3. Known feedId -> correct $metadata for THAT spreadsheet.
4. Known feedId -> correct entity JSON for THAT spreadsheet.
5. Two different feedIds return two different datasets.
6. Auth precedence: bad auth -> 401 even with valid feedId.
7. Valid auth + unknown feedId -> 404.
8. InMemoryFeedResolver resolve hit/miss.
9. (If implemented) PdoFeedResolver with a sqlite :memory: fixture.

=== DELIVERABLES ===
- New/modified src files above
- tests/ covering all cases
- Update README.md:
  * Document the /odata/{feedId}/... routes
  * Explain FeedResolverInterface and how to plug a DB via PDO (DI)
  * Note that core stays database-agnostic
- Update public/index.php example to wire an InMemoryFeedResolver.

