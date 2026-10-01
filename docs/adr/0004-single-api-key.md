# ADR 0004: A single service API key rather than JWT

Date: 2026-10-01
Status: Accepted

## Problem

The API needs authentication. JWT with an issuer and per-user claims is the
default choice for a new HTTP service.

## Decision

Authenticate with one shared service API key, sent as `X-Api-Key` and compared
with `hash_equals` so a caller cannot recover it by timing failed requests.

This service has one kind of caller — the retailer's own internal systems — and no
end users. There are no roles to encode, no per-user data to scope and nobody to
issue a token to. A JWT setup would mean a token issuer, key rotation and claim
validation built to protect a distinction the system does not make.

## Outcome

Authentication is one middleware of about thirty lines that a reviewer can read in
full, and the security story is honest: it protects the service from unauthorised
callers and nothing more.

What this rules out is per-caller authorisation. If a second kind of caller ever
needed different access — a read-only reporting client, say — the key would not
express that, and this decision would have to be revisited rather than extended.
`/health` is deliberately outside the check so an orchestrator can probe it.
