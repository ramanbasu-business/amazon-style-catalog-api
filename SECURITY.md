# Security Policy

## Reporting a vulnerability

Report privately through GitHub's [report a vulnerability](https://github.com/ramanbasu-business/amazon-style-catalog-api/security/advisories/new)
form. Please do not open a public issue for a security problem.

Include what you did, what happened, and what you expected. I aim to acknowledge within
three working days.

This is a demonstration project, not a deployed service, so there is no production
system at risk and no bounty.

## What this project does for security

**Input and data access**
- Every SQL statement is parameterised. No request value is concatenated into SQL.
- `LIKE` wildcards in a search keyword are escaped, so `%` is matched literally rather
  than widening a search to every row.
- Identifiers are validated against a character allowlist and pagination against integer
  bounds before any lookup runs. Failures return 422 with per-field messages.

**Secrets**
- Configuration comes from the environment only. `.env` is git-ignored and
  `.env.example` contains placeholders.
- Startup fails on a missing required value, naming the key and never printing a value.
- gitleaks scans the full git history in CI, with `fetch-depth: 0` so it reads past
  commits rather than only the tip.

**Authentication**
- One service API key, sent as `X-Api-Key` and compared with `hash_equals` so it cannot
  be recovered by timing failed requests.
- The key is checked before routing, so an invalid key cannot learn which paths exist.
- `/health` is deliberately unauthenticated so an orchestrator can probe it. It reveals
  only whether the database is reachable.

**Responses**
- Security headers on every response, error responses included: `X-Content-Type-Options:
  nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`,
  `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'`, and
  `Cache-Control: no-store`.
- Errors render as RFC 9457 problem documents. Only an `ApiProblem` carries a message to
  the client; any other throwable is logged and returned as a bare 500, so stack traces
  and SQL cannot escape even if `APP_DEBUG` is set wrongly.

**Rate limiting**
- Calls toward the marketplace pass a token bucket per operation, held in the database
  so every process shares one allowance. A refused call never leaves the process.
- Note the gap: there is no rate limit on *inbound* requests. A caller holding the API
  key can issue unlimited requests, which the cache absorbs but which is not a defence.

**Logging**
- Structured JSON to stdout, carrying identifiers and failure reasons only. Request
  bodies and credentials are never logged.

**Runtime**
- The container runs as a non-root user.
- CI runs Semgrep (`p/php`, `p/security-audit`, `p/secrets`) and `composer audit`
  against the lock file. Both fail the build.

## Known gaps

Honest about what is absent rather than implied:

- No inbound rate limiting or request size cap.
- No TLS termination in this repository; it assumes a proxy in front.
- No CORS policy, because no browser client is expected.
- One shared key means no per-caller authorisation or revocation (see
  [ADR 0004](docs/adr/0004-single-api-key.md)).
- No audit trail of who read what.
