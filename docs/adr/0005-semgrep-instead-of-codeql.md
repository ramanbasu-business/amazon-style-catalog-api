# ADR 0005: Semgrep and gitleaks for security scanning

Date: 2026-10-01
Status: Accepted

## Problem

The house standard calls for CodeQL or SonarQube in CI. The first CI run failed
with `Did not recognize the following languages: php` — CodeQL has no PHP
analyzer, so that step could never pass on this repository.

## Decision

Use Semgrep with the `p/php`, `p/security-audit` and `p/secrets` rule sets for
static security analysis, and gitleaks over the full git history for committed
secrets. Keep `composer audit` for known advisories in dependencies.

SonarQube was the other option in the standard and does support PHP, but its
useful form here is SonarCloud, which needs an account and a token in repository
secrets. Semgrep's rule sets run from the workflow with nothing to provision,
which keeps a public repository forkable and runnable by anyone.

## Outcome

Three scanners, each with a distinct job: Semgrep reads the code for insecure
patterns, gitleaks reads the history for secrets, `composer audit` reads the lock
file for vulnerable versions. All three fail the build.

gitleaks needs `fetch-depth: 0` on checkout; with the default shallow clone it
would scan only the tip commit and report a clean history that it never read.
