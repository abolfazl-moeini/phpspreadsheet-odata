# Security Policy

## Supported versions

| Version | Supported |
|---------|-----------|
| 1.x     | Yes       |

## Reporting a vulnerability

If you discover a security issue, please report it privately rather than opening a public issue.

Include:

- A description of the vulnerability
- Steps to reproduce
- Impact assessment (if known)

We will acknowledge receipt and work on a fix as promptly as possible.

## Recommendations for production

- Always enable authentication (`useBearer`, `useApiKey`, or `useBasicAuth`) when exposing feeds publicly.
- Terminate TLS at your reverse proxy; do not serve OData over plain HTTP in production.
- Place the endpoint behind rate limiting and network access controls where appropriate.
- Validate and restrict which `feedId` values your `FeedResolver` returns.
- Keep PhpSpreadsheet and this package updated via Composer.