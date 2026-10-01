# ADR 0001: PHP 8.3 and Slim rather than .NET 8

Date: 2026-10-01
Status: Accepted

## Problem

The showcase standard for this author's repositories is C# on .NET 8 with ASP.NET
Core. This project departs from it, and a reviewer seeing PHP where .NET was
expected should find the reason recorded rather than assume it was an accident.

## Decision

Build the service in PHP 8.3 on Slim 4, with Doctrine DBAL for data access and
PHPUnit for tests.

Two reasons. The domain being demonstrated — marketplace catalog reads and batch
feed submissions — comes from PHP systems the author has worked in, so the
patterns are reproduced from genuine experience rather than translated. And the
project is intended to show the design of the integration, which is language
independent; a reader judging the cache, limiter and job-queue decisions can do so
in either language.

Slim rather than Laravel: this is a small API with no need for an ORM, templating
or a bundled auth stack. A thin framework leaves the design decisions visible in
this repository's own code instead of hidden behind framework conventions, which
is the point of a showcase.

## Outcome

The .NET-specific norms of the house standard map onto PHP equivalents:
`Directory.Build.props` and analyzers become `phpcs` with PSR-12 and PHPStan at
level 8; EF Core migrations become plain SQL files applied by `bin/migrate`;
Serilog becomes Monolog with a JSON formatter; xUnit becomes PHPUnit.

What does not map is left out rather than faked. There is no equivalent of
NetArchTest in use here; layering is enforced by review and by the small number of
namespaces, not by a test.
